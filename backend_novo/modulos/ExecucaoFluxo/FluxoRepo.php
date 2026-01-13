<?php
class FluxoRepo {
    private $pdo;        // Conexão MySQL (PDO)
    private $connSenior; // Conexão SQL Server (PDO)

    public function __construct(PDO $pdo, ?PDO $connSenior = null) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // =========================================================================
    //  MÉTODOS SQL SERVER (Senior Sapiens) - AGORA COM PDO BLINDADO
    // =========================================================================

    public function buscarQuantidadeSenior($numSol, $seqSol) {
        if (!$this->connSenior) return 1.0;

        $sql = "SELECT qtdsol FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$numSol, $seqSol]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (float)$row['qtdsol'];
        }
        return 1.0;
    }

    public function buscarSolicitacoesSeniorRaw($start, $length, $search, $campoOrdenacao, $dirSQL, $meusItens, $bloqueados) {
        if (!$this->connSenior) throw new Exception("Conexão com Senior não configurada.");

        // Monta a lógica de PESO para ordenação (Case When)
        $caseParts = [];
        if (!empty($meusItens))  $caseParts[] = "WHEN (" . implode(" OR ", $meusItens) . ") THEN 2";
        if (!empty($bloqueados)) $caseParts[] = "WHEN (" . implode(" OR ", $bloqueados) . ") THEN 1";
        
        $colunaPeso = empty($caseParts) ? "0" : "CASE " . implode(" ", $caseParts) . " ELSE 0 END";
        
        $tabela = "Sapiens.sapiens.e405sol";
        $filtroStatus = "sitsol IN (1, 2)"; // Abertas ou em Cotação
        
        // Filtro complexo (Status OR (Bloqueados))
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

        // --- Query Principal (Paginação SQL Server 2012+) ---
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol, 
                $colunaPeso as peso_ordenacao
                FROM $tabela $where 
                ORDER BY $campoOrdenacao $dirSQL
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        // --- CORREÇÃO AQUI: Bind Manual para forçar INT ---
        $stmt = $this->connSenior->prepare($sql);

        // 1. Vincula parâmetros de busca (Strings)
        $i = 1;
        foreach ($sqlParams as $val) {
            $stmt->bindValue($i++, $val);
        }

        // 2. Vincula Paginação OBRIGATORIAMENTE como INTEIRO
        $stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);

        $stmt->execute();
        // --------------------------------------------------

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Query Total (Count) ---
        $sqlTotal = "SELECT COUNT(*) as T FROM $tabela WHERE $filtroStatus";
        
        $stmtTotal = $this->connSenior->prepare($sqlTotal);
        $stmtTotal->execute($sqlParams); // Usa apenas os params do WHERE
        $total = $stmtTotal->fetchColumn();

        return ['dados' => $dados, 'total' => $total];
    }

    // =========================================================================
    //  MÉTODOS MYSQL (Processo Interno) - MANTIDOS (JÁ ERAM PDO)
    // =========================================================================

    public function getInstanciaCompleta($id) {
        $sql = "SELECT p.*, d.arquivo_xml, d.nome_do_fluxo, d.id_fluxo_definicao 
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                WHERE p.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarIdPorSolicitacao($numSol) {
        $stmt = $this->pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
        $stmt->execute([$numSol]);
        return $stmt->fetchColumn();
    }
    
    public function buscarItensOcupados() {
        $sql = "SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarProcesso($numSol, $idFluxo) {
        $sql = "INSERT INTO processos_instancia 
                (id_processo_senior, id_processo_instancia, id_fluxo_definicao, data_inicio, status_atual, etapa_bpmn_atual) 
                VALUES (:numsol, :numsol, :fluxo, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':numsol' => $numSol, ':fluxo' => $idFluxo]);
        return $this->pdo->lastInsertId();
    }

    public function adicionarItem($idProcesso, $numSol, $seqSol, $qtd) {
        $sql = "INSERT IGNORE INTO processos_itens 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, quantidade) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $numSol, $seqSol, $qtd]);
        return $stmt->rowCount() > 0;
    }

    public function inicializarCotacao($idProcesso, $numSol, $seqSol) {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                SELECT ?, ?, ?, id_fornecedor_senior, NULL 
                FROM licitacao_participantes 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $numSol, $seqSol, $idProcesso]);
    }

    public function removerItemCompleto($idProcesso, $numSol, $seqSol) {
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND num_solicitacao = ? AND seq_solicitacao = ?")->execute([$idProcesso, $numSol, $seqSol]);
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = ? AND num_solicitacao = ? AND seq_solicitacao = ?")->execute([$idProcesso, $numSol, $seqSol]);
    }

    public function excluirProcesso($idProcesso) {
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?")->execute([$idProcesso]);
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = ?")->execute([$idProcesso]);
        $this->pdo->prepare("DELETE FROM processos_instancia WHERE id = ?")->execute([$idProcesso]);
    }

    // --- DASHBOARD ---
    public function listarFluxosDisponiveis() {
        $sql = "SELECT id_fluxo_definicao as id, nome_do_fluxo, 'blue' as cor_ui FROM nome_do_fluxo";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTodosProcessos() {
        $sql = "SELECT 
                    p.id, p.data_inicio, p.status_atual, p.id_processo_senior, d.nome_do_fluxo
                FROM processos_instancia p
                LEFT JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                ORDER BY p.id DESC LIMIT 50";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>