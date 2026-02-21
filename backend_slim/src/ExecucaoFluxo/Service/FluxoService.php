<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Service;

use ExecucaoFluxo\Repository\FluxoRepo;
use Shared\Utils\Formatador;
use Exception;
use DateTime;

class FluxoService
{
    private FluxoRepo $repo;
    private string $pathPublic;

    public function __construct(FluxoRepo $repo)
    {
        $this->repo = $repo;
        $this->pathPublic = dirname(__DIR__, 3) . '/public';
    }

    public function carregarPassoAtual($idInstancia): array
    {
        $instancia = $this->repo->getInstanciaCompleta($idInstancia);
        if (!$instancia) return ['erro' => "Processo #$idInstancia nao encontrado."];

        $instancia['id_visual'] = $instancia['id'] . '/' . ($instancia['ano_do_processo'] ?? date('Y'));

        $dtCot = $instancia['data_esperada_da_cotacao'];
        $dtRec = $instancia['data_esperada_do_recebimento'];

        $instancia['datas_editaveis'] = [
            'cotacao_iso' => $dtCot,
            'recebimento_iso' => $dtRec,
            'cotacao_fmt' => Formatador::data($dtCot),
            'recebimento_fmt' => Formatador::data($dtRec),
        ];

        $nomeArquivo = !empty($instancia['arquivo_xml']) ? $instancia['arquivo_xml'] : 'compra_direta.xml';
        $caminhoCompleto = $this->pathPublic . '/' . $nomeArquivo;

        $tituloTarefa = 'Visualizacao';
        if (file_exists($caminhoCompleto)) {
            $xml = simplexml_load_file($caminhoCompleto);
            $xml->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
            $passoAtualId = $instancia['etapa_bpmn_atual'];
            $nodes = $xml->xpath("//bpmn:userTask[@id='$passoAtualId']");
            if (empty($nodes)) $nodes = $xml->xpath("//bpmn:startEvent");
            if (!empty($nodes)) $tituloTarefa = (string)$nodes[0]['name'];
        }

        return [
            'instancia' => $instancia,
            'fluxo_id' => $instancia['id_fluxo_definicao'],
            'nome_fluxo' => $instancia['nome_do_fluxo'],
            'arquivo_xml' => $nomeArquivo,
            'tarefa' => ['titulo' => $tituloTarefa, 'id_xml' => $instancia['etapa_bpmn_atual']],
        ];
    }

    public function salvarDatasPrevisao(array $dados): array
    {
        $id = $dados['id_processo'] ?? null;
        $dtCot = $dados['data_cotacao'] ?? null;
        $dtRec = $dados['data_recebimento'] ?? null;

        if (!$id) throw new Exception("ID obrigatorio.");
        $hoje = date('Y-m-d');
        if ($dtCot && $dtCot < $hoje) throw new Exception("Cotacao menor que hoje.");
        if ($dtRec && $dtRec < $hoje) throw new Exception("Entrega menor que hoje.");
        if ($dtCot && $dtRec && $dtRec < $dtCot) throw new Exception("Entrega menor que Cotacao.");

        $this->repo->atualizarDatasPrevisao($id, $dtCot, $dtRec);
        return ['sucesso' => true, 'msg' => 'Datas atualizadas!'];
    }

    public function vincularItens(array $dados): array
    {
        $idFluxo = $dados['id_fluxo_definicao'] ?? 1;
        $idProcesso = $dados['id_processo_instancia'] ?? null;
        if ($idProcesso === 'null' || empty($idProcesso)) $idProcesso = null;

        $selecionados = $dados['selecionados'] ?? [];
        if (empty($selecionados)) throw new Exception("Nenhum item selecionado.");

        $mapaSolicitacoes = [];
        foreach ($selecionados as $itemKey) {
            $parts = explode('-', $itemKey);
            if (count($parts) < 3) continue;
            $mapaSolicitacoes[$parts[1]][] = $parts[2];
        }

        $itensProcessados = 0;
        foreach ($mapaSolicitacoes as $numsol => $listaSeqs) {
            if (!$idProcesso) {
                $existente = $this->repo->buscarIdPorSolicitacao($numsol);
                $idProcesso = $existente ?: $this->repo->criarProcesso($numsol, $idFluxo);
            }
            foreach ($listaSeqs as $seq) {
                $qtdSenior = $this->repo->buscarQuantidadeSenior($numsol, $seq);
                if ($this->repo->adicionarItem($idProcesso, $numsol, $seq, $qtdSenior)) {
                    $this->repo->inicializarCotacao($idProcesso, $numsol, $seq);
                    $itensProcessados++;
                }
            }
        }
        return ['sucesso' => true, 'msg' => "$itensProcessados itens vinculados!", 'id_processo' => $idProcesso];
    }

    public function listarSolicitacoesSenior(array $params): array
    {
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $instance_id = (int)($params['instance_id'] ?? 0);

        $colMap = [1 => 'numprj', 2 => 'datsol', 3 => 'numsol', 4 => 'cplpro', 5 => 'presol', 6 => 'peso_ordenacao'];
        $campoOrdenacao = $colMap[$params['order'][0]['column'] ?? 1] ?? 'numprj';
        $dirSQL = ($params['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $ocupados = $this->repo->buscarItensOcupados();
        $meusItens = []; $bloqueados = []; $mapaDonos = [];

        foreach ($ocupados as $row) {
            $n = (int)$row['num_solicitacao']; $s = (int)$row['seq_solicitacao'];
            $sqlCond = "(CAST(numsol AS INT) = $n AND CAST(seqsol AS INT) = $s)";
            $mapaDonos["$n-$s"] = $row['id_processo_instancia'];
            if ($instance_id && $row['id_processo_instancia'] == $instance_id) $meusItens[] = $sqlCond;
            else $bloqueados[] = $sqlCond;
        }

        $resultado = $this->repo->buscarSolicitacoesSeniorRaw($start, $length, $search, $campoOrdenacao, $dirSQL, $meusItens, $bloqueados);
        $data = [];
        foreach ($resultado['dados'] as $row) {
            $n = (int)$row['numsol']; $s = (int)$row['seqsol'];
            $peso = (int)$row['peso_ordenacao'];
            $status = ($peso === 2) ? 'vinculado' : (($peso === 1) ? 'bloqueado' : 'disponivel');

            $data[] = [
                'id_unico' => $row['codemp'] . '-' . $n . '-' . $s,
                'projeto' => trim((string)$row['numprj']),
                'data_solicitacao' => ($row['datsol'] instanceof DateTime) ? $row['datsol']->format('Y-m-d') : null,
                'id_solicitacao_senior' => "$n-$s",
                'descricao_produto' => Formatador::utf8($row['cplpro']),
                'quantidade' => (float)$row['qtdsol'],
                'preco_unitario' => (float)$row['presol'],
                'unidade' => trim($row['unimed']),
                'status' => $status,
                'proc_bloqueador' => ($status === 'bloqueado') ? ($mapaDonos["$n-$s"] ?? '?') : '',
            ];
        }
        return ["draw" => (int)($params['draw'] ?? 1), "recordsTotal" => $resultado['total'], "recordsFiltered" => $resultado['total'], "data" => $data];
    }

    public function removerItem($id, $num, $seq): array
    {
        $this->repo->removerItemCompleto($id, $num, $seq);
        return ['sucesso' => true];
    }

    public function cancelarProcesso($id): array
    {
        $this->repo->excluirProcesso($id);
        return ['sucesso' => true];
    }

    public function carregarDadosDashboard(): array
    {
        $fluxos = $this->repo->listarFluxosDisponiveis();
        $rawProc = $this->repo->listarTodosProcessos();
        $tarefas = [];

        foreach ($rawProc as $r) {
            $tarefas[] = [
                'id' => $r['id'],
                'nome_do_fluxo' => Formatador::utf8($r['nome_do_fluxo']),
                'id_processo_senior' => $r['id_processo_senior'],
                'data_formatada' => Formatador::dataHora($r['data_inicio']),
                'status_atual' => $r['status_atual'],
            ];
        }
        return ['fluxos' => $fluxos, 'tarefas' => $tarefas];
    }
}
