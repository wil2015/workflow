<?php
require_once 'FluxoRepo.php';

class FluxoService {
    private $repo;
    private $pathPublic;

    public function __construct($pdo, $connSenior) {
        // Passa as conexões para o Repo, que é quem vai usar
        $this->repo = new FluxoRepo($pdo, $connSenior);
        
        $raizProjeto = dirname(__DIR__, 3); 
        $this->pathPublic = $raizProjeto . '/public';
    }

    // 1. LEITURA DO FLUXO (BPMN)
    public function carregarPassoAtual($idInstancia) {
        $instancia = $this->repo->getInstanciaCompleta($idInstancia);
        
        if (!$instancia) {
            return ['erro' => "Processo #$idInstancia não encontrado ou foi excluído."];
        }

        $nomeArquivo = $instancia['arquivo_xml'];
        if (empty($nomeArquivo)) $nomeArquivo = 'compra_direta.xml';

        $caminhoCompleto = $this->pathPublic . '/' . $nomeArquivo;
        
        if (!file_exists($caminhoCompleto)) {
             throw new Exception("Arquivo BPMN não encontrado: '$nomeArquivo'");
        }

        $xml = simplexml_load_file($caminhoCompleto);
        $xml->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

        $passoAtualId = $instancia['etapa_bpmn_atual']; 
        $nodes = $xml->xpath("//bpmn:userTask[@id='$passoAtualId']");
        if (empty($nodes)) $nodes = $xml->xpath("//bpmn:startEvent");
        
        $tituloTarefa = !empty($nodes) ? (string)$nodes[0]['name'] : 'Visualização';

        return [
            'instancia' => $instancia,
            'fluxo_id' => $instancia['id_fluxo_definicao'],
            'nome_fluxo' => $instancia['nome_do_fluxo'],
            'arquivo_xml' => $nomeArquivo, 
            'tarefa' => ['titulo' => $tituloTarefa, 'id_xml' => $passoAtualId]
        ];
    }

    // 2. VINCULAR ITENS
    public function vincularItens($dados) {
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
                // CHAMA O REPO AGORA, SEM SQL AQUI
                $qtdSenior = $this->repo->buscarQuantidadeSenior($numsol, $seq);

                if ($this->repo->adicionarItem($idProcesso, $numsol, $seq, $qtdSenior)) {
                    $this->repo->inicializarCotacao($idProcesso, $numsol, $seq);
                    $itensProcessados++;
                }
            }
        }
        
        return ['sucesso' => true, 'msg' => "$itensProcessados itens vinculados!", 'id_processo' => $idProcesso];
    }

    // 3. LISTAGEM DO SENIOR (DataTables)
    public function listarSolicitacoesSenior($params) {
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';
        $instance_id = (int)($params['instance_id'] ?? 0);
        
        // Ordenação
        $colIdx = $params['order'][0]['column'] ?? 1;
        $colDir = $params['order'][0]['dir'] ?? 'desc';
        $dirSQL = ($colDir === 'asc') ? 'ASC' : 'DESC';
        $colMap = [1 => 'numprj', 2 => 'datsol', 3 => 'numsol', 4 => 'cplpro', 5 => 'presol', 6 => 'peso_ordenacao'];
        $campoOrdenacao = $colMap[$colIdx] ?? 'numprj';

        // 1. Identifica Bloqueios (Regra de Negócio) - Consulta MySQL
        $meusItens = []; $bloqueados = []; $mapaDonos = [];
        $ocupados = $this->repo->buscarItensOcupados(); 
        
        foreach ($ocupados as $row) {
            $n = (int)$row['num_solicitacao']; $s = (int)$row['seq_solicitacao']; $chave = "$n-$s";
            $sqlCond = "(CAST(numsol AS INT) = $n AND CAST(seqsol AS INT) = $s)";
            $mapaDonos[$chave] = $row['id_processo_instancia'];

            if ($instance_id && $row['id_processo_instancia'] == $instance_id) {
                $meusItens[] = $sqlCond;
            } else {
                $bloqueados[] = $sqlCond;
            }
        }

        // 2. Chama o Repo para buscar no SQL Server (Sem montar SQL aqui)
        $resultado = $this->repo->buscarSolicitacoesSeniorRaw(
            $start, $length, $search, $campoOrdenacao, $dirSQL, $meusItens, $bloqueados
        );

        // 3. Formata os dados para o Front (Visualização)
        $data = [];
        foreach ($resultado['dados'] as $row) {
            $n = (int)$row['numsol']; $s = (int)$row['seqsol']; $chave = "$n-$s";
            $peso = (int)$row['peso_ordenacao'];
            
            $status = 'disponivel'; $bloqueador = '';
            if ($peso === 2) $status = 'vinculado';
            elseif ($peso === 1) { $status = 'bloqueado'; $bloqueador = $mapaDonos[$chave] ?? '?'; }

            $dt = ($row['datsol'] instanceof DateTime) ? $row['datsol']->format('Y-m-d') : null;
            $desc = $row['cplpro']; 
            if(mb_detect_encoding($desc, 'UTF-8', true) === false) $desc = utf8_encode($desc);

            $data[] = [
                'id_unico' => $row['codemp'] . '-' . $n . '-' . $s,
                'projeto' => trim((string)$row['numprj']),
                'data_solicitacao' => $dt,
                'id_solicitacao_senior' => "$n-$s",
                'descricao_produto' => $desc,
                'quantidade' => (float)$row['qtdsol'],
                'preco_unitario' => (float)$row['presol'],
                'unidade' => trim($row['unimed']),
                'status' => $status,
                'proc_bloqueador' => $bloqueador
            ];
        }

        return [
            "draw" => (int)($params['draw'] ?? 1),
            "recordsTotal" => $resultado['total'],
            "recordsFiltered" => $resultado['total'],
            "data" => $data
        ];
    }

    public function removerItem($id, $num, $seq) {
        $this->repo->removerItemCompleto($id, $num, $seq);
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
            $dt = new DateTime($r['data_inicio']);
            $tarefas[] = [
                'id' => $r['id'],
                'nome_do_fluxo' => utf8_encode($r['nome_do_fluxo']),
                'id_processo_senior' => $r['id_processo_senior'],
                'data_formatada' => $dt->format('d/m/Y H:i'),
                'status_atual' => $r['status_atual']
            ];
        }
        return ['fluxos' => $fluxos, 'tarefas' => $tarefas];
    }
}