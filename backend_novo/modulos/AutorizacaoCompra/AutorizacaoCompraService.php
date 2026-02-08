<?php
namespace App\Modulos\AutorizacaoCompra;

use App\Core\BaseService;
// O use garante que o PHP encontre a classe. Não precisa de if manual.
use App\Core\Documentos\Engine\DocumentoEngine;
use App\Modulos\AutorizacaoCompra\Documentos\AutorizacaoDoc;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Exception;

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
        foreach ($docs as &$doc) {
            $pathLimpo = str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo'] ?? '');
            $pathLimpo = ltrim($pathLimpo, '/');
            $doc['existe_fisicamente'] = file_exists('/var/www/html/' . $pathLimpo);
        }
        return $docs;
    }

    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        $idProcesso = (int)$idProcesso;

        // 1. Snapshot
        $this->repo->executarSnapshotDados($idProcesso, $idUsuario);

        // 2. Busca dados
        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);

        if (empty($autorizacoes)) {
            throw new Exception("Nenhuma autorização gerada. Verifique se a cotação tem vencedores.");
        }

        // --- CORREÇÃO AQUI ---
        // Removemos o 'if (!class_exists)'. O autoloader resolve sozinho.
        
        // Configure seu DSN real aqui (ou use null para testes)
        $transport = Transport::fromDsn('smtp://null:null@localhost'); 
        $mailer = new Mailer($transport);
        
        $engine = new DocumentoEngine($mailer);
        
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        foreach ($autorizacoes as $auth) {
            try {
                $itensSnapshot = $this->repo->buscarItensDoSnapshot($auth['id']);
                $fornecedorDados = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);

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
                    'numero_processo' => "$idProcesso/" . date('Y'),
                    'fornecedor_nome' => $fornecedorDados['nomfor'], 
                    'fornecedor_cnpj' => $fornecedorDados['cgccpf'], 
                    'itens' => $itensParaPdf,
                    'valor_total_pedido' => $auth['valor_total_pedido']
                ]);

                // Gera PDF
                $pathAbs = $engine->processar($doc, $idProcesso);
                
                // Trata caminho relativo para salvar no banco
                $pathRel = str_replace('/var/www/html/', '', $pathAbs);
                $pathRel = ltrim($pathRel, '/');

                $meta = [
                    'caminho_relativo' => $pathRel, 
                    'nome_arquivo' => basename($pathAbs),
                    'hash_sha256' => file_exists($pathAbs) ? hash_file('sha256', $pathAbs) : ''
                ];
                
                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
                $logs[] = "Gerado: " . $meta['nome_arquivo'];

            } catch (Exception $e) { 
                $logs[] = "Erro (Auth {$auth['id']}): " . $e->getMessage(); 
            }
        }
        return ['sucesso' => true, 'logs' => $logs];
    }

    // --- SEU MÉTODO DE EMAIL RESTAURADO ---
    public function enviarEmailsEConcluir($idProcesso, $idUsuario) {
        // Configure o transporte correto (Mailtrap, Gmail, Postfix, etc)
        // Dica: Para produção, evite colocar senha no código. Use variáveis de ambiente (getenv).
        $transport = Transport::fromDsn('smtp://usuario:senha@smtp.mailtrap.io:2525');
        $mailer = new Mailer($transport);
        
        $autorizacoes = $this->repo->buscarAutorizacoesGeradas($idProcesso);
        $logs = []; 
        $enviados = 0;

        foreach ($autorizacoes as $auth) {
            $dados = $this->repo->buscarDadosFornecedorSenior($auth['id_fornecedor']);
            if (empty($dados['intnet'])) {
                $logs[] = "Fornecedor {$dados['nomfor']} sem email.";
                continue;
            }

            // Tenta achar o documento pelo padrão de nome
            $doc = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, "auth_" . $auth['id']);
            
            // Corrige caminho físico
            $pathFisico = '';
            if ($doc) {
                $relativo = ltrim(str_replace(['/public/', 'public/'], '', $doc['caminho_arquivo']), '/');
                $pathFisico = '/var/www/html/' . $relativo;
            }

            if ($doc && file_exists($pathFisico)) {
                try {
                    $email = (new Email())
                        ->from('compras@unesp.br')
                        ->to($dados['intnet'])
                        ->subject("Autorização de Compra #$idProcesso")
                        ->html("<p>Prezado fornecedor, segue em anexo a autorização de compra referente ao processo $idProcesso.</p>")
                        ->attachFromPath($pathFisico, 'Autorizacao_Compra.pdf');
                    
                    $mailer->send($email);
                    $enviados++; 
                    $logs[] = "Enviado para: {$dados['intnet']}";
                } catch (Exception $e) { 
                    $logs[] = "Falha email {$dados['intnet']}: " . $e->getMessage(); 
                }
            } else {
                $logs[] = "Arquivo não encontrado para envio: " . ($doc['nome_arquivo'] ?? 'Sem registro');
            }
        }
        return ['sucesso' => $enviados > 0, 'mensagem' => "$enviados emails enviados.", 'logs' => $logs];
    }
}