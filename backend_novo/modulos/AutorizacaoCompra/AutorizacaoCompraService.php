<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';
// Importa o Motor de Documentos (Core)
require_once __DIR__ . '/../../core/Documentos/Engine/PdfArchiver.php'; 
// Importa o Template Específico deste Módulo
require_once __DIR__ . '/Templates/AutorizacaoCompraDoc.php';

class AutorizacaoCompraService extends BaseService
{
    private $repo;
    private $archiver;

    public function __construct($pdo, $connSenior) {
        parent::__construct($pdo, $connSenior);
        $this->repo = new AutorizacaoCompraRepo($pdo, $connSenior);
        $this->archiver = new PdfArchiver(); // O Arquivista
    }

    public function listarDocumentosGerados($idProcesso) {
        return $this->repo->listarDocumentosPorProcesso($idProcesso);
    }

    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        // 1. Busca Dados Brutos
        $itensVencedores = $this->repo->buscarItensVencedores($idProcesso);
        $dadosProcesso = $this->repo->buscarDadosProcesso($idProcesso);

        if (empty($itensVencedores)) {
            throw new Exception("Nenhum item vencedor encontrado. Verifique a Grade Comparativa.");
        }

        // 2. Agrupa por Fornecedor (Um PDF por fornecedor)
        $porFornecedor = [];
        foreach ($itensVencedores as $item) {
            $cod = $item['id_fornecedor_senior'];
            if (!isset($porFornecedor[$cod])) {
                $porFornecedor[$cod] = ['itens' => [], 'total' => 0.0];
            }
            $porFornecedor[$cod]['itens'][] = $item;
            $porFornecedor[$cod]['total'] += ($item['valor_cotado'] * $item['quantidade']);
        }

        $documentosGerados = [];

        // 3. Loop de Geração
        foreach ($porFornecedor as $codForn => $dados) {
            
            // A. Prepara os Dados para o Template
            $dadosView = $this->prepararDadosParaTemplate($codForn, $dados, $dadosProcesso);

            // B. Instancia o Template e Gera o Binário do PDF
            $docEngine = new AutorizacaoCompraDoc($dadosView);
            $pdfBinario = $docEngine->renderizar("auth_temp.pdf", 'S'); // 'S' = String Return

            // C. Arquiva no Disco (Fisicamente)
            $meta = $this->archiver->arquivar($pdfBinario, 'autorizacao_compra', $idProcesso);

            // D. Registra no Banco (Logicamente)
            $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);

            $documentosGerados[] = $meta['nome_arquivo'];
        }

        return [
            'sucesso' => true, 
            'msg' => count($documentosGerados) . " autorizações geradas e arquivadas com sucesso.",
            'arquivos' => $documentosGerados
        ];
    }

    // Método auxiliar para limpar a sujeira de preparação de dados
    private function prepararDadosParaTemplate($codForn, $dadosForn, $proc) {
        $infoSenior = $this->repo->buscarDadosFornecedorSenior($codForn);
        
        // Formata Itens
        $itensFormatados = [];
        foreach ($dadosForn['itens'] as $i) {
            $detalhe = $this->repo->buscarDetalheItemSenior($i['num_solicitacao'], $i['seq_solicitacao']);
            
            $itensFormatados[] = [
                'seq' => count($itensFormatados) + 1,
                'descricao' => $detalhe ? $this->utf8($detalhe['cplpro']) : "Item {$i['num_solicitacao']}",
                'unidade' => $detalhe ? trim($detalhe['unimed']) : 'UN',
                'quantidade' => $i['quantidade'],
                'valor_unitario' => $i['valor_cotado'],
                'valor_total' => $i['quantidade'] * $i['valor_cotado']
            ];
        }

        // Retorna DTO visual (Array)
        return [
            'numero_autorizacao' => $proc['id'] . '/' . date('Y'),
            'data_emissao_extenso' => $this->dataPorExtenso(date('Y-m-d')),
            'processo_numero' => $proc['id_processo_senior'],
            'fluxo_nome' => $this->utf8($proc['nome_do_fluxo']),
            
            'fornecedor_nome' => $this->utf8($infoSenior['nomfor'] ?? "Fornecedor $codForn"),
            'fornecedor_endereco' => $this->utf8(trim(($infoSenior['endfor']??'') . ', ' . ($infoSenior['nroend']??''))),
            'fornecedor_cnpj' => $infoSenior['cgccpf'] ?? '',
            'fornecedor_fone' => $infoSenior['fonfor'] ?? '',
            
            'itens' => $itensFormatados,
            'total_geral' => $dadosForn['total'],
            
            // Fixo ou parametrizável
            'local_entrega_completo' => "UNESP - FACULDADE DE CIÊNCIAS FARMACÊUTICAS<br>Rodovia Araraquara-Jaú, Km 01..."
        ];
    }

    private function dataPorExtenso($data) {
        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
        return strftime('%d de %B de %Y', strtotime($data));
    }
}