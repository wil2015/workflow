<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/FluxoRepo.php';

class FluxoService extends BaseService
{
    private $repo;
    private $pathPublic;

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->repo = new FluxoRepo($pdo, $connSenior);
        
        // Define caminho para ler XMLs
        $this->pathPublic = dirname(__DIR__, 3) . '/public';
    }

    public function carregarPassoAtual($idInstancia)
    {
        $instancia = $this->repo->getInstanciaCompleta($idInstancia);
        if (!$instancia) return ['erro' => "Processo #$idInstancia não encontrado."];

        $nomeArquivo = !empty($instancia['arquivo_xml']) ? $instancia['arquivo_xml'] : 'compra_direta.xml';
        $caminhoCompleto = $this->pathPublic . '/' . $nomeArquivo;
        
        if (!file_exists($caminhoCompleto)) throw new Exception("Arquivo BPMN não encontrado: '$nomeArquivo'");

        $xml = simplexml_load_file($caminhoCompleto);
        $xml->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

        $passoAtualId = $instancia['etapa_bpmn_atual']; 
        $nodes = $xml->xpath("//bpmn:userTask[@id='$passoAtualId']");
        if (empty($nodes)) $nodes = $xml->xpath("//bpmn:startEvent");
        
        return [
            'instancia' => $instancia,
            'fluxo_id' => $instancia['id_fluxo_definicao'],
            'nome_fluxo' => $instancia['nome_do_fluxo'],
            'arquivo_xml' => $nomeArquivo, 
            'tarefa' => ['titulo' => !empty($nodes) ? (string)$nodes[0]['name'] : 'Visualização', 'id_xml' => $passoAtualId]
        ];
    }

    public function vincularItens($dados)
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
                $idProcesso = $existente ? $existente : $this->repo->criarProcesso($numsol, $idFluxo);
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

    public function listarSolicitacoesSenior($params)
    {
        // (Mantive a lógica original, apenas resumida para caber aqui)
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
            
            // HERANÇA: Uso do método utf8() da BaseService
            $desc = $this->utf8($row['cplpro']);

            $data[] = [
                'id_unico' => $row['codemp'] . '-' . $n . '-' . $s,
                'projeto' => trim((string)$row['numprj']),
                'data_solicitacao' => ($row['datsol'] instanceof DateTime) ? $row['datsol']->format('Y-m-d') : null,
                'id_solicitacao_senior' => "$n-$s",
                'descricao_produto' => $desc,
                'quantidade' => (float)$row['qtdsol'],
                'preco_unitario' => (float)$row['presol'],
                'unidade' => trim($row['unimed']),
                'status' => $status,
                'proc_bloqueador' => ($status === 'bloqueado') ? ($mapaDonos["$n-$s"] ?? '?') : ''
            ];
        }

        return ["draw" => (int)($params['draw'] ?? 1), "recordsTotal" => $resultado['total'], "recordsFiltered" => $resultado['total'], "data" => $data];
    }

    public function removerItem($id, $num, $seq) {
        $this->repo->removerItemCompleto($id, $num, $seq);
        return ['sucesso' => true];
    }
    
    public function cancelarProcesso($id) {
        $this->repo->excluirProcesso($id);
        return ['sucesso' => true];
    }
}