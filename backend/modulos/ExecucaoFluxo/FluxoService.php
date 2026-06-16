<?php
namespace App\Modulos\ExecucaoFluxo;

use App\Core\BaseService;
use App\Core\Utils\Formatador;
use Exception;
use DateTime;

class FluxoService extends BaseService
{
    private $repo;
    private $pathPublic;

    public function __construct(FluxoRepo $repo)
    {
        $this->repo = $repo;
        $this->pathPublic = dirname(__DIR__, 3) . '/public';
    }

    public function carregarPassoAtual($idInstancia)
    {
        $instancia = $this->repo->getInstanciaCompleta($idInstancia);
        if (!$instancia) return ['erro' => "Processo #$idInstancia nao encontrado."];

        $instancia['id_visual'] = $instancia['id'] . '/' . ($instancia['ano_do_processo'] ?? date('Y'));

        $nomeArquivo = !empty($instancia['arquivo_xml']) ? $instancia['arquivo_xml'] : 'compra_direta.xml';
        $caminhoCompleto = $this->pathPublic . '/' . $nomeArquivo;

        $tituloTarefa = 'Visualizacao';
        if (file_exists($caminhoCompleto)) {
            $xml = simplexml_load_file($caminhoCompleto);
            $xml->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
            $passoAtualId = $instancia['etapa_bpmn_atual'];
            $nodes = $xml->xpath("//*[@id='$passoAtualId']");
            if (empty($nodes)) $nodes = $xml->xpath("//bpmn:startEvent");
            if (!empty($nodes)) $tituloTarefa = (string)$nodes[0]['name'];
        }

        return [
            'instancia' => $instancia,
            'fluxo_id' => $instancia['id_fluxo_definicao'],
            'nome_fluxo' => $instancia['nome_do_fluxo'],
            'arquivo_xml' => $nomeArquivo,
            'tarefa' => ['titulo' => $tituloTarefa, 'id_xml' => $instancia['etapa_bpmn_atual']]
        ];
    }

    public function vincularItens($dados)
    {
        $idFluxo = $dados['id_fluxo_definicao'] ?? 1;
        $idProcesso = $dados['id_processo_instancia'] ?? null;
        if ($idProcesso === 'null' || empty($idProcesso)) $idProcesso = null;

        $selecionados = $dados['selecionados'] ?? [];
        if (empty($selecionados)) throw new Exception("Nenhum item selecionado.");

        $mapaOrdensCompra = [];
        foreach ($selecionados as $itemKey) {
            $parts = explode('-', $itemKey);
            if (count($parts) < 3) continue;
            $mapaOrdensCompra[$parts[1]][] = $parts[2];
        }

        $itensProcessados = 0;
        foreach ($mapaOrdensCompra as $numeroOc => $listaSeqs) {
            if (!$idProcesso) {
                $existente = $this->repo->buscarIdPorOrdemCompra($numeroOc);
                $idProcesso = $existente ?: $this->repo->criarProcessoPorOrdemCompra($numeroOc, $idFluxo);
            }
            foreach ($listaSeqs as $sequenciaOc) {
                $qtdSenior = $this->repo->buscarQuantidadeOrdemCompra($numeroOc, $sequenciaOc);
                if ($this->repo->adicionarItemOrdemCompra($idProcesso, $numeroOc, $sequenciaOc, $qtdSenior)) {
                    $this->repo->inicializarCotacao($idProcesso, $numeroOc, $sequenciaOc);
                    $itensProcessados++;
                }
            }
        }
        return ['sucesso' => true, 'msg' => "$itensProcessados itens vinculados!", 'id_processo' => $idProcesso];
    }

    public function listarOrdensCompraSenior($params)
    {
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $instance_id = (int)($params['instance_id'] ?? 0);

        $colMap = [1 => 'data_geracao', 2 => 'numero_oc', 3 => 'tipo_item', 4 => 'descricao_item', 5 => 'preco_unitario', 6 => 'peso_ordenacao'];
        $campoOrdenacao = $colMap[$params['order'][0]['column'] ?? 2] ?? 'numero_oc';
        $dirSQL = ($params['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $ocupados = $this->repo->buscarItensOcupados();
        $meusItens = [];
        $bloqueados = [];
        $mapaDonos = [];

        foreach ($ocupados as $row) {
            $n = (int)$row['num_solicitacao'];
            $s = (int)$row['seq_solicitacao'];
            $sqlCond = "(oc.numero_oc = $n AND oc.sequencia_workflow = $s)";
            $mapaDonos["$n-$s"] = $row['id_processo_instancia'];
            if ($instance_id && $row['id_processo_instancia'] == $instance_id) $meusItens[] = $sqlCond;
            else $bloqueados[] = $sqlCond;
        }

        $resultado = $this->repo->buscarOrdensCompraSeniorRaw($start, $length, $search, $campoOrdenacao, $dirSQL, $meusItens, $bloqueados);
        $data = [];
        foreach ($resultado['dados'] as $row) {
            $numeroOc = (int)$row['numero_oc'];
            $sequenciaOc = (int)$row['sequencia_workflow'];
            $peso = (int)$row['peso_ordenacao'];
            $status = ($peso === 2) ? 'vinculado' : (($peso === 1) ? 'bloqueado' : 'disponivel');
            $dataGeracao = $row['data_geracao'] ?? null;
            $dataGeracaoIso = null;
            if ($dataGeracao instanceof DateTime) {
                $dataGeracaoIso = $dataGeracao->format('Y-m-d');
            } elseif (is_string($dataGeracao)) {
                $timestamp = strtotime($dataGeracao);
                $dataGeracaoIso = $timestamp ? date('Y-m-d', $timestamp) : null;
            }

            $tipoItem = strtoupper((string)($row['tipo_item'] ?? ''));
            $tipoItemLabel = $tipoItem === 'SERVICO' ? 'Servico' : 'Produto';
            $sequenciaOriginal = (int)($row['sequencia_original'] ?? $sequenciaOc);

            $data[] = [
                'id_unico' => $row['codemp'] . '-' . $numeroOc . '-' . $sequenciaOc,
                'projeto' => trim((string)$row['tipo_item']),
                'tipo_item' => Formatador::utf8($row['tipo_item'] ?? ''),
                'codigo_item' => trim((string)($row['codigo_item'] ?? '')),
                'sequencia_original' => $sequenciaOriginal,
                'data_ordem_compra' => $dataGeracaoIso,
                'numero_oc' => $numeroOc,
                'sequencia_oc' => $sequenciaOc,
                'ordem_compra_label' => (string)$numeroOc,
                'item_ordem_label' => "$tipoItemLabel $sequenciaOriginal",
                'descricao_item' => Formatador::utf8($row['descricao_item']),
                'quantidade' => (float)$row['quantidade'],
                'preco_unitario' => (float)$row['preco_unitario'],
                'status' => $status,
                'proc_bloqueador' => ($status === 'bloqueado') ? ($mapaDonos["$numeroOc-$sequenciaOc"] ?? '?') : ''
            ];
        }
        return ["draw" => (int)($params['draw'] ?? 1), "recordsTotal" => $resultado['total'], "recordsFiltered" => $resultado['total'], "data" => $data];
    }

    public function removerItem($id, $num, $seq) {
        $this->repo->removerItemOrdemCompra($id, $num, $seq);
        return ['sucesso' => true];
    }

    public function cancelarProcesso($id) {
        $this->repo->excluirProcesso($id);
        return ['sucesso' => true];
    }

    public function carregarDadosDashboard() {
        $fluxos = $this->repo->listarFluxosDisponiveis();
        $rawProc = $this->repo->listarTodosProcessos();
        $tarefas = [];

        foreach($rawProc as $r) {
            $tarefas[] = [
                'id' => $r['id'],
                'nome_do_fluxo' => Formatador::utf8($r['nome_do_fluxo']),
                'id_processo_senior' => $r['id_processo_senior'],
                'data_formatada' => Formatador::dataHora($r['data_inicio']),
                'status_atual' => $r['status_atual']
            ];
        }
        return ['fluxos' => $fluxos, 'tarefas' => $tarefas];
    }
}
