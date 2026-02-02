<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';
// Carrega autoload se existir (segurança para Mailer)
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
        // 1. Busca os dados brutos do banco
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        
        // 2. Itera para verificar a existência física de cada um
        foreach ($docs as &$doc) {
            $pathBanco = $doc['caminho_arquivo'];
            
            // Tenta higienizar o caminho para encontrar o arquivo físico em /var/www/html/
            // Remove '/public/' inicial se houver, pois no container o root costuma ser /var/www/html/
            // e a pasta storage está na raiz do projeto, não dentro de public (na estrutura física padrão).
            $pathLimpo = str_replace(['/public/', 'public/'], '', $pathBanco);
            $pathLimpo = ltrim($pathLimpo, '/');
            
            // Monta o caminho absoluto do servidor
            $pathFisico = '/var/www/html/' . $pathLimpo;

            // 3. Injeta a propriedade que o Vue espera
            $doc['existe_fisicamente'] = file_exists($pathFisico);
            
            // Opcional: Debug para ver onde ele está procurando se der erro
            // $doc['debug_path'] = $pathFisico; 
        }
        
        return $docs;
    }
    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        $idProcesso = (int)$idProcesso;
        $itens = $this->repo->buscarItensVencedores($idProcesso);

        if (empty($itens)) {
            throw new Exception("Nenhum item vencedor encontrado (ID: $idProcesso).");
        }

        $agrupado = $this->agruparDados($itens);
        
        // Verifica se a Engine existe
        if (!class_exists('DocumentoEngine')) {
            throw new Exception("Erro: Classe DocumentoEngine não encontrada.");
        }

        $engine = new DocumentoEngine(new Mailer(Transport::fromDsn('smtp://null:null@localhost')));
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        foreach ($agrupado as $forn) {
            $doc = new AutorizacaoDoc([
                'id_autorizacao' => $forn['id'], 
                'numero_processo' => "$idProcesso/2026",
                'fornecedor_nome' => $forn['nome'], 
                'fornecedor_cnpj' => $forn['cnpj'], 
                'itens' => $forn['itens']
            ]);

            try {
                // Gera o arquivo físico absoluto
                $pathAbs = $engine->processar($doc, $idProcesso, null, false);
                
                // --- CORREÇÃO INFALÍVEL DO CAMINHO ---
                // Divide o caminho na palavra "storage"
                $parts = explode('/storage/', $pathAbs);
                
                if (count($parts) > 1) {
                    // Pega a segunda parte e adiciona "storage/" no começo
                    // Resultado: "storage/docs/2026/..." (Sem /var/www)
                    $pathRel = 'storage/' . $parts[1];
                } else {
                    // Fallback: Tenta remover a raiz padrão na força bruta
                    $pathRel = str_replace('/var/www/html/', '', $pathAbs);
                    $pathRel = ltrim($pathRel, '/');
                }

                $meta = [
                    'caminho_relativo' => $pathRel, 
                    'nome_arquivo' => basename($pathAbs),
                    'hash_sha256' => file_exists($pathAbs) ? hash_file('sha256', $pathAbs) : ''
                ];
                
                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
                $logs[] = "Gerado: {$forn['nome']}";

            } catch (Exception $e) { 
                $logs[] = "Erro {$forn['nome']}: " . $e->getMessage(); 
            }
        }
        return ['sucesso' => true, 'logs' => $logs];
    }

    public function enviarEmailsEConcluir($idProcesso, $idUsuario) {
        $mailer = new Mailer(Transport::fromDsn('smtp://usuario:senha@smtp.mailtrap.io:2525'));
        $itens = $this->repo->buscarItensVencedores($idProcesso);
        $agrupado = $this->agruparDados($itens);
        $logs = []; $enviados = 0;

        foreach ($agrupado as $forn) {
            if (empty($forn['email'])) continue;
            
            $doc = $this->repo->buscarDocumentoPorNomeParcial($idProcesso, "auth_" . $forn['id']);
            $pathNoBanco = $doc['caminho_arquivo'] ?? '';
            // Reconstrói caminho absoluto para o PHP ler
            $pathFisico = '/var/www/html/' . ltrim($pathNoBanco, '/');

            if ($doc && file_exists($pathFisico)) {
                try {
                    $email = (new Email())->from('compras@unesp.br')->to($forn['email'])
                        ->subject("Autorização #$idProcesso")->html("<p>Anexo.</p>")
                        ->attachFromPath($pathFisico, 'Autorizacao.pdf');
                    $mailer->send($email);
                    $enviados++; $logs[] = "Enviado: {$forn['email']}";
                } catch (Exception $e) { $logs[] = "Falha: " . $e->getMessage(); }
            }
        }
        return ['sucesso' => $enviados > 0, 'mensagem' => "$enviados emails", 'logs' => $logs];
    }

    private function agruparDados($itens) {
        $res = [];
        foreach ($itens as $row) {
            $fid = (int)($row['id_fornecedor_senior'] ?? $row['id_fornecedor_vencedor'] ?? 0);
            if (!$fid) continue;
            
            if (!isset($res[$fid])) {
                $dados = $this->repo->buscarDadosFornecedorSenior($fid);
                $res[$fid] = ['id' => $fid, 'nome' => $dados['nomfor'], 'cnpj' => $dados['cgccpf'], 'email' => $dados['intnet'], 'itens' => []];
            }
            
            $detalhe = !empty($row['id_item']) ? $this->repo->buscarDetalhesItem($row['id_item']) : $row;
            $num = $detalhe['num_solicitacao'] ?? 0;
            $seq = $detalhe['seq_solicitacao'] ?? 0;
            $desc = $this->repo->buscarDescricaoItemSenior($num, $seq);
            
            $res[$fid]['itens'][] = [
                'descricao' => $desc,
                'quantidade' => $row['quantidade'], 
                'valor' => $row['valor_cotado'], 
                'valor_total' => $row['quantidade'] * $row['valor_cotado']
            ];
        }
        return $res;
    }
}