<?php
// Carrega o Autoload (Importante para o erro Class not found)
require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';

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

    // Este método usa as funções ANTIGAS do Repo para preparar a NOVA Engine
    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        
        // 1. Busca os itens "crus" (Função que sempre existiu)
        $itensRaw = $this->repo->buscarItensVencedores($idProcesso);

        if (empty($itensRaw)) {
             throw new Exception("Não existe cotação de fornecedores (Processo $idProcesso sem itens na grade).");
        }

        // 2. Agrupa os dados aqui no Service (Como era feito antes)
        $agrupado = [];
        foreach ($itensRaw as $row) {
            $codForn = $row['id_fornecedor_senior'];

            if (!isset($agrupado[$codForn])) {
                // Busca dados do fornecedor no Senior (Função que sempre existiu)
                $dadosSenior = $this->repo->buscarDadosFornecedorSenior($codForn);
                
                $agrupado[$codForn] = [
                    'id_vencedor' => $codForn,
                    'nome'        => $dadosSenior['nomfor'] ?? "Fornecedor $codForn",
                    'cnpj'        => $dadosSenior['cgccpf'] ?? '',
                    'email'       => $dadosSenior['intnet'] ?? '', 
                    'itens'       => []
                ];
            }

            // Busca detalhe do item no Senior (Função que sempre existiu)
            $detalhe = $this->repo->buscarDetalheItemSenior($row['num_solicitacao'], $row['seq_solicitacao']);
            
            $agrupado[$codForn]['itens'][] = [
                'descricao'  => $detalhe['cplpro'] ?? 'Item Diverso',
                'quantidade' => $row['quantidade'],
                'unidade'    => $detalhe['unimed'] ?? 'UN',
                'valor'      => $row['valor_cotado'],
                'valor_total'=> $row['quantidade'] * $row['valor_cotado']
            ];
        }

        // 3. Configura a Engine (A novidade entra aqui)
        $dsn = 'smtp://usuario:senha@smtp.mailtrap.io:2525'; 
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        $engine = new DocumentoEngine($mailer);

        // Limpa anteriores
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');
        $logs = [];

        // 4. Gera os documentos usando os dados agrupados
        foreach ($agrupado as $fornecedor) {
            
            // Prepara dados para o Template
            $dadosParaTemplate = [
                'id_autorizacao'  => $fornecedor['id_vencedor'],
                'numero_processo' => $idProcesso . '/2026',
                'fornecedor_nome' => $fornecedor['nome'],
                'fornecedor_cnpj' => $fornecedor['cnpj'],
                'itens'           => $fornecedor['itens']
            ];

            $doc = new AutorizacaoDoc($dadosParaTemplate);
            
            try {
                // Engine gera PDF
                $caminhoPdf = $engine->processar($doc, $idProcesso, $fornecedor['email']);
                
                // Registra no banco (Função que sempre existiu)
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
    
    public function listarDocumentosGerados($idProcesso) {
        return $this->repo->listarDocumentosPorProcesso($idProcesso);
    }
}