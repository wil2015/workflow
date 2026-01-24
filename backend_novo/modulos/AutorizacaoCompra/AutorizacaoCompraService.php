<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/AutorizacaoCompraRepo.php';
require_once __DIR__ . '/../../core/Documentos/Engine/PdfArchiver.php'; 
require_once __DIR__ . '/../../core/Documentos/Templates/AutorizacaoCompraDoc.php';

class AutorizacaoCompraService extends BaseService
{
    private $repo;
    private $archiver;

    public function __construct($pdo, $connSenior) {
        parent::__construct($pdo, $connSenior);
        $this->repo = new AutorizacaoCompraRepo($pdo, $connSenior);
        $this->archiver = new PdfArchiver(); 
    }

    // --- MUDANÇA: Agora retorna metadados sobre o processo ---
    public function listarDocumentosGerados($idProcesso) {
        
        // 1. Busca os documentos existentes
        $docs = $this->repo->listarDocumentosPorProcesso($idProcesso);
        
        // Validação física
        foreach ($docs as &$doc) {
            $caminhoFisico = '/var/www/html/' . $doc['caminho_arquivo'];
            $doc['existe_fisicamente'] = file_exists($caminhoFisico);
            if (!$doc['existe_fisicamente']) {
                $doc['status_erro'] = "Arquivo não encontrado no disco.";
            }
        }

        // 2. Verifica se é POSSÍVEL gerar (se tem cotação)
        $temCotacao = $this->repo->temItensVencedores($idProcesso);

        // Retorna um array estruturado para o Frontend
        return [
            'documentos' => $docs,
            'tem_cotacao' => $temCotacao
        ];
    }

    public function gerarDocumentosOficiais($idProcesso, $idUsuario) {
        // Validação dupla (Security Check)
        $itensVencedores = $this->repo->buscarItensVencedores($idProcesso);
        if (empty($itensVencedores)) {
            throw new Exception("Não existe cotação de fornecedores disponível.");
        }

        // Faxina de arquivos antigos
        $docsAntigos = $this->repo->listarDocumentosPorProcesso($idProcesso);
        foreach ($docsAntigos as $doc) {
            $caminhoFisico = '/var/www/html/' . $doc['caminho_arquivo'];
            if (file_exists($caminhoFisico)) @unlink($caminhoFisico); 
        }
        $this->repo->limparDocumentosAnteriores($idProcesso, 'AUTORIZACAO_COMPRA');

        // Geração (Mesma lógica de antes)
        $dadosProcesso = $this->repo->buscarDadosProcesso($idProcesso);
        $porFornecedor = [];
        foreach ($itensVencedores as $item) {
            $cod = $item['id_fornecedor_senior'];
            if (!isset($porFornecedor[$cod])) $porFornecedor[$cod] = ['itens' => [], 'total' => 0.0];
            $porFornecedor[$cod]['itens'][] = $item;
            $porFornecedor[$cod]['total'] += ($item['valor_cotado'] * $item['quantidade']);
        }

        $docsGerados = [];
        foreach ($porFornecedor as $codForn => $dados) {
            $dadosView = $this->prepararDadosVisuais($codForn, $dados, $dadosProcesso);
            $template = new AutorizacaoCompraDoc($dadosView);
            $pdfBinario = $template->renderizar("auth_temp.pdf", 'S'); 
            $meta = $this->archiver->arquivar($pdfBinario, 'autorizacao_compra', $idProcesso);
            $this->repo->registrarDocumento($idProcesso, 'AUTORIZACAO_COMPRA', $meta, $idUsuario);
            $docsGerados[] = $meta['nome_arquivo'];
        }

        return [
            'sucesso' => true, 
            'msg' => count($docsGerados) . " autorizações geradas.",
            'arquivos' => $docsGerados
        ];
    }

    // ... (Mantenha o método prepararDadosVisuais igual ao anterior) ...
    private function prepararDadosVisuais($codForn, $dadosForn, $proc) {
        $infoSenior = $this->repo->buscarDadosFornecedorSenior($codForn);
        $itensFormatados = [];
        $seq = 1;
        foreach ($dadosForn['itens'] as $i) {
            $detalhe = $this->repo->buscarDetalheItemSenior($i['num_solicitacao'], $i['seq_solicitacao']);
            $itensFormatados[] = [
                'seq' => $seq++,
                'descricao' => $detalhe ? $this->utf8($detalhe['cplpro']) : "Item {$i['num_solicitacao']}",
                'unidade' => $detalhe ? trim($detalhe['unimed']) : 'UN',
                'quantidade' => $i['quantidade'],
                'valor_unitario' => $i['valor_cotado'],
                'valor_total' => $i['quantidade'] * $i['valor_cotado']
            ];
        }
        $enderecoCompleto = trim($infoSenior['endfor'] ?? '');
        $num = $infoSenior['nenfor'] ?? $infoSenior['numero'] ?? '';
        if (!empty($num)) $enderecoCompleto .= ', ' . trim($num);
        if (!empty($infoSenior['cplend'])) $enderecoCompleto .= ' - ' . trim($infoSenior['cplend']);

        return [
            'numero_autorizacao' => $proc['id'] . '/' . date('Y'),
            'data_emissao_extenso' => date('d/m/Y'), 
            'processo_numero' => $proc['id_processo_senior'],
            'fluxo_nome' => $this->utf8($proc['nome_do_fluxo']),
            'fornecedor_nome' => $this->utf8($infoSenior['nomfor'] ?? "Fornecedor $codForn"),
            'fornecedor_endereco' => $this->utf8($enderecoCompleto),
            'fornecedor_cnpj' => $infoSenior['cgccpf'] ?? '',
            'fornecedor_fone' => $infoSenior['fonfor'] ?? '',
            'itens' => $itensFormatados,
            'total_geral' => $dadosForn['total'],
            'local_entrega_completo' => "UNESP - FACULDADE DE CIÊNCIAS FARMACÊUTICAS..."
        ];
    }
}