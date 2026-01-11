<?php
require_once 'FluxoRepo.php';

class FluxoService {
    private $repo;
    private $connSenior;
    private $pathPublic;

    public function __construct($pdo, $connSenior) {
        $this->repo = new FluxoRepo($pdo);
        $this->connSenior = $connSenior;
        
        // --- CONFIGURAÇÃO DE CAMINHO (DOCKER) ---
        // Calcula a raiz do projeto (sobe 3 níveis a partir deste arquivo)
        $raizProjeto = dirname(__DIR__, 3); 
        // Aponta para a pasta public mapeada no Docker
        $this->pathPublic = $raizProjeto . '/public';
    }

    // =========================================================================
    // 1. LEITURA DO FLUXO (BPMN)
    // =========================================================================
    public function carregarPassoAtual($idInstancia) {
        $instancia = $this->repo->getInstanciaCompleta($idInstancia);
        
        // --- CORREÇÃO: RETORNO SUAVE PARA EVITAR POP-UP DE ERRO ---
        if (!$instancia) {
            // Retorna um JSON de erro controlado. O Vue lerá isso e redirecionará para a Home.
            return ['erro' => "Processo #$idInstancia não encontrado ou foi excluído."];
        }

        $nomeArquivo = $instancia['arquivo_xml'];
        if (empty($nomeArquivo)) $nomeArquivo = 'compra_direta.xml';

        $caminhoCompleto = $this->pathPublic . '/' . $nomeArquivo;
        
        // Diagnóstico de Arquivo
        if (!file_exists($caminhoCompleto)) {
             $status = is_dir($this->pathPublic) ? "A pasta existe." : "A pasta 'public' NÃO existe no container.";
             // Aqui mantemos Exception pois é erro de configuração do servidor/docker
             throw new Exception(
                 "ERRO DE ARQUIVO:\nO PHP buscou em: '$caminhoCompleto'\nDiagnóstico: $status"
             );
        }

        $xml = simplexml_load_file($caminhoCompleto);
        // OBRIGATÓRIO: Registra o namespace para o XPath funcionar em arquivos BPMN padrão
        $xml->registerXPathNamespace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

        $passoAtualId = $instancia['etapa_bpmn_atual']; 

        // Busca a tarefa usando XPath (funciona mesmo em sub-processos)
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

    // =========================================================================
    // 2. LÓGICA DE NEGÓCIO (Vincular Itens)
    // =========================================================================
    public function vincularItens($dados) {
        $idFluxo = $dados['id_fluxo_definicao'] ?? 1;
        $idProcesso = $dados['id_processo_instancia'] ?? null;
        
        // Corrige bug do javascript enviando string "null"
        if ($idProcesso === 'null' || empty($idProcesso)) $idProcesso = null;
        
        $selecionados = $dados['selecionados'] ?? [];
        if (empty($selecionados)) throw new Exception("Nenhum item selecionado.");

        // Agrupa por solicitação para otimizar criação
        $mapaSolicitacoes = [];
        foreach ($selecionados as $itemKey) {
            // Esperado: "codemp-numsol-seqsol"
            $parts = explode('-', $itemKey);
            if (count($parts) < 3) continue;
            $mapaSolicitacoes[$parts[1]][] = $parts[2];
        }

        $itensProcessados = 0;
        foreach ($mapaSolicitacoes as $numsol => $listaSeqs) {
            // Se não tem processo, cria ou busca um existente para esta solicitação
            if (!$idProcesso) {
                $existente = $this->repo->buscarIdPorSolicitacao($numsol);
                if ($existente) {
                    $idProcesso = $existente;
                } else {
                    $idProcesso = $this->repo->criarProcesso($numsol, $idFluxo);
                }
            }

            foreach ($listaSeqs as $seq) {
                // Consulta Qtd no Senior (Sapiens) para garantir integridade
                $qtdSenior = 1.0;
                if ($this->connSenior) {
                    $sqlS = "SELECT qtdsol FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
                    $stmtS = sqlsrv_query($this->connSenior, $sqlS, [$numsol, $seq]);
                    if ($stmtS && $row = sqlsrv_fetch_array($stmtS, SQLSRV_FETCH_ASSOC)) {
                        $qtdSenior = (float)$row['qtdsol'];
                    }
                }

                // Manda o Repo salvar o item
                if ($this->repo->adicionarItem($idProcesso, $numsol, $seq, $qtdSenior)) {
                    // Inicializa cotações vazias para fornecedores existentes
                    $this->repo->inicializarCotacao($idProcesso, $numsol, $seq);
                    $itensProcessados++;
                }
            }
        }
        
        return ['sucesso' => true, 'msg' => "$itensProcessados itens vinculados!", 'id_processo' => $idProcesso];
    }

    // =========================================================================
    // 3. LISTAGEM DO SENIOR (Tabela DataTables)
    // =========================================================================
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

        // Verifica itens bloqueados no MySQL
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

        // Monta Query SQL Server
        $caseParts = [];
        if (!empty($meusItens))  $caseParts[] = "WHEN (" . implode(" OR ", $meusItens) . ") THEN 2";
        if (!empty($bloqueados)) $caseParts[] = "WHEN (" . implode(" OR ", $bloqueados) . ") THEN 1";
        
        $colunaPeso = empty($caseParts) ? "0" : "CASE " . implode(" ", $caseParts) . " ELSE 0 END";
        
        $tabela = "Sapiens.sapiens.e405sol";
        $filtroStatus = "sitsol IN (1, 2)";
        
        $todosEmUso = array_merge($meusItens, $bloqueados);
        if (!empty($todosEmUso)) {
            $filtroStatus = "($filtroStatus OR (" . implode(" OR ", $todosEmUso) . "))";
        }

        $where = "WHERE $filtroStatus";
        $sqlParams = [];

        if ($search) {
            $where .= " AND (cplpro LIKE ? OR CAST(numsol AS VARCHAR(20)) LIKE ? OR numprj LIKE ?)";
            $termo = "%$search%";
            $sqlParams = [$termo, $termo, $termo];
        }

        // Query Principal
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol, 
                $colunaPeso as peso_ordenacao
                FROM $tabela $where 
                ORDER BY $campoOrdenacao $dirSQL
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        $sqlParams[] = $start;
        $sqlParams[] = $length;

        $stmt = sqlsrv_query($this->connSenior, $sql, $sqlParams);
        if ($stmt === false) throw new Exception("Erro SQL Senior: " . print_r(sqlsrv_errors(), true));

        // Query Total
        $sqlTotal = "SELECT COUNT(*) as T FROM $tabela WHERE $filtroStatus";
        $tTotal = sqlsrv_fetch_array(sqlsrv_query($this->connSenior, $sqlTotal), SQLSRV_FETCH_ASSOC)['T'];
        
        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
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
            "recordsTotal" => $tTotal,
            "recordsFiltered" => $tTotal,
            "data" => $data
        ];
    }

    // =========================================================================
    // 4. MÉTODOS AUXILIARES (Remover/Cancelar)
    // =========================================================================
    public function removerItem($id, $num, $seq) {
        $this->repo->removerItemCompleto($id, $num, $seq);
        return ['sucesso' => true];
    }
    
    public function cancelarProcesso($id) {
        $this->repo->excluirProcesso($id);
        return ['sucesso' => true];
    }
}