<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
require_once __DIR__ . '/../../core/Documentos/Engine/DocumentoEngine.php';
require_once __DIR__ . '/Documentos/AutorizacaoDoc.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

class AutorizacaoCompraService extends BaseService
{
    private $repo;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new AutorizacaoCompraRepo($pdo, $connSenior);
    }

    public function listarDocumentosGerados($idProcesso) {
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        // Lógica de verificação física (mantida da correção anterior)
        foreach ($docs as &$doc) {
            $pathLimpo = str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo']);
            $pathLimpo = ltrim($pathLimpo, '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);
        }
        return $docs;
    }

    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        $idProcesso = (int)$idProcesso;

        // 1. EXECUTA O SNAPSHOT via Procedure
        $this->repo->executarSnapshotDados($idProcesso, $idUsuario);

        // 2. Busca os dados já mastigados
        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);

        if (empty($autorizacoes)) {
            // Se vazio, a procedure não achou itens na grade_de_custos
            throw new Exception("Nenhuma autorização gerada. Verifique se a cotação tem vencedores.");
        }

        // Inicializa Engine
        if (!class_exists('DocumentoEngine')) {
            throw new Exception("Classe DocumentoEngine não encontrada.");
        }
        $engine = new DocumentoEngine(new Mailer(Transport::fromDsn('smtp://null:null@localhost')));
        
        // Limpa registros de arquivos antigos
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        // 3. Itera sobre as Autorizações (tabela autorizacao_compra)
        foreach ($autorizacoes as $auth) {
            try {
                // Busca itens congelados desta autorização
                $itensSnapshot = $this->repo->buscarItensDoSnapshot($auth['id']);
                
                // Busca dados cadastrais (Senior) que não ficam no snapshot
                $fornecedorDados = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);

                // Mapeia para o formato do PDF
                $itensParaPdf = [];
                foreach($itensSnapshot as $item) {
                    $itensParaPdf[] = [
                        'descricao' => $item['descricao_item_snapshot'],
                        'quantidade' => $item['quantidade'],
                        'valor' => $item['valor_unitario_congelado'],
                        'valor_total' => $item['valor_total_item']
                    ];
                }

                $doc = new AutorizacaoDoc([
                    'id_autorizacao' => $auth['id'],
                    'numero_processo' => "$idProcesso/2026",
                    'fornecedor_nome' => $fornecedorDados['nomfor'], 
                    'fornecedor_cnpj' => $fornecedorDados['cgccpf'], 
                    'itens' => $itensParaPdf,
                    'valor_total_pedido' => $auth['valor_total_pedido']
                ]);

                // Gera arquivo
                $pathAbs = $engine->processar($doc, $idProcesso, null, false);
                
                // Trata caminho relativo
                $parts = explode('/storage/', $pathAbs);
                $pathRel = (count($parts) > 1) ? 'storage/' . $parts[1] : ltrim(str_replace('/var/www/html/', '', $pathAbs), '/');

                $meta = [
                    'caminho_relativo' => $pathRel, 
                    'nome_arquivo' => basename($pathAbs),
                    'hash_sha256' => file_exists($pathAbs) ? hash_file('sha256', $pathAbs) : ''
                ];
                
                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
                $logs[] = "Gerado para: {$fornecedorDados['nomfor']}";

            } catch (Exception $e) { 
                $logs[] = "Erro (Auth {$auth['id']}): " . $e->getMessage(); 
            }
        }
        return ['sucesso' => true, 'logs' => $logs];
    }

    public function enviarEmailsEConcluir($idProcesso, $idUsuario) {
        $mailer = new Mailer(Transport::fromDsn('smtp://usuario:senha@smtp.mailtrap.io:2525'));
        
        // Agora iteramos pelas autorizações geradas, não pela grade bruta
        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        $logs = []; $enviados = 0;

        foreach ($autorizacoes as $auth) {
            $dados = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);
            if (empty($dados['intnet'])) continue;

            // Busca o PDF pelo nome parcial (auth_{id})
            // Nota: O ID no nome do arquivo pode ser o ID da autorização ou do fornecedor dependendo de como o AutorizacaoDoc foi configurado. 
            // Assumindo padrão "auth_{id_autorizacao}" para garantir unicidade
            $doc = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, "auth_" . $auth['id']);
            
            if (!$doc) {
                // Fallback: Tenta buscar pelo ID do Fornecedor caso o sistema antigo usasse isso
                $doc = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, "auth_" . $auth['id_fornecedor']);
            }

            $pathFisico = '/var/www/html/' . ltrim(str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? ''), '/');

            if ($doc && file_exists($pathFisico)) {
                try {
                    $email = (new Email())
                        ->from('compras@unesp.br')
                        ->to($dados['intnet'])
                        ->subject("Autorização de Compra #$idProcesso")
                        ->html("<p>Segue em anexo a autorização de compra.</p>")
                        ->attachFromPath($pathFisico, 'Autorizacao.pdf');
                    
                    $mailer->send($email);
                    $enviados++; 
                    $logs[] = "Enviado para: {$dados['intnet']}";
                } catch (Exception $e) { 
                    $logs[] = "Falha email {$dados['intnet']}: " . $e->getMessage(); 
                }
            }
        }
        return ['sucesso' => $enviados > 0, 'mensagem' => "$enviados emails enviados.", 'logs' => $logs];
    }
}