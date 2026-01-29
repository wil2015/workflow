<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php'; // Seu Repo Original

// Engine e Documento
require_once __DIR__ . '/../../core/Documentos/Engine/DocumentoEngine.php';
require_once __DIR__ . '/Documentos/AutorizacaoDoc.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;

class AutorizacaoCompraService extends BaseService {
    private $repo;

    public function __construct($pdo, $connSenior) {
        parent::__construct($pdo, $connSenior);
        $this->repo = new AutorizacaoCompraRepo($pdo, $connSenior);
    }

    // Este método é chamado pelo Controller
    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        
        // 1. Usa o método ANTIGO que retorna dados crus da grade
        $itensRaw = $this->repo->buscarItensVencedores($idProcesso);

        if (empty($itensRaw)) {
             throw new Exception("Não existe cotação de fornecedores (Grade vazia).");
        }

        // 2. Agrupa os dados aqui no Service (para não mexer no Repo)
        $agrupado = [];
        foreach ($itensRaw as $row) {
            $codForn = $row['id_fornecedor_senior'];

            if (!isset($agrupado[$codForn])) {
                // Usa método ANTIGO de buscar dados do Senior
                $dadosSenior = $this->repo->buscarDadosFornecedorSenior($codForn);
                
                $agrupado[$codForn] = [
                    'id_vencedor' => $codForn,
                    'nome'        => $dadosSenior['nomfor'] ?? "Fornecedor $codForn",
                    'cnpj'        => $dadosSenior['cgccpf'] ?? '',
                    'email'       => $dadosSenior['intnet'] ?? null, 
                    'itens'       => []
                ];
            }

            // Usa método ANTIGO de detalhe do item
            $detalhe = $this->repo->buscarDetalheItemSenior($row['num_solicitacao'], $row['seq_solicitacao']);
            
            $agrupado[$codForn]['itens'][] = [
                'descricao'  => $detalhe['cplpro'] ?? 'Item Diverso',
                'quantidade' => $row['quantidade'],
                'unidade'    => $detalhe['unimed'] ?? 'UN',
                'valor'      => $row['valor_cotado'],
                'valor_total'=> $row['quantidade'] * $row['valor_cotado']
            ];
        }

        // 3. Configura a Engine (Dummy Mailer)
        $dsn = 'smtp://null:null@localhost'; 
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        $engine = new DocumentoEngine($mailer);

        // Limpa anteriores
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        // 4. Loop de Geração
        foreach ($agrupado as $fornecedor) {
            
            $dadosParaTemplate = [
                'id_autorizacao'  => $fornecedor['id_vencedor'],
                'numero_processo' => $idProcesso . '/2026',
                'fornecedor_nome' => $fornecedor['nome'],
                'fornecedor_cnpj' => $fornecedor['cnpj'],
                'itens'           => $fornecedor['itens']
            ];

            $doc = new AutorizacaoDoc($dadosParaTemplate);
            
            try {
                // False = Não envia email, só gera PDF e Salva
                $caminhoPdf = $engine->processar($doc, $idProcesso, $fornecedor['email'], false);
                
                // Registra no banco para aparecer na tela
                $meta = [
                    'caminho_relativo' => str_replace('/var/www/html', '', $caminhoPdf),
                    'nome_arquivo'     => basename($caminhoPdf),
                    'hash_sha256'      => hash_file('sha256', $caminhoPdf)
                ];
                $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);

                $logs[] = "Gerado: {$fornecedor['nome']}";
            } catch (Exception $e) {
                $logs[] = "Erro {$fornecedor['nome']}: " . $e->getMessage();
            }
        }

        return ['sucesso' => true, 'logs' => $logs];
    }
    
    // Método para listar (usado pelo Controller)
    public function listarDocumentosGerados($idProcesso) {
        return $this->repo->listarDocumentosPorProcesso($idProcesso);
    }
}