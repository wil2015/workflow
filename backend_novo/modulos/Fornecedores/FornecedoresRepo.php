<?php
class FornecedoresRepo {
    private $pdo;       
    private $connSenior; 

    public function __construct(PDO $pdo, ?PDO $connSenior = null) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- LEITURA (SENIOR) ---
    public function buscarTotalSenior($condicao, $params) {
        // MUDANÇA: Em vez de retornar 0, lança erro!
        if (!$this->connSenior) {
            throw new Exception("Repo: Conexão com Senior não está ativa.");
        }

        $sql = "SELECT COUNT(*) as T FROM Sapiens.sapiens.e095for $condicao";
        
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }

    public function buscarFornecedoresSenior($condicao, $orderBy, $offset, $limit, $params) {
        // MUDANÇA: Em vez de retornar [], lança erro!
        if (!$this->connSenior) {
            throw new Exception("Repo: Conexão com Senior não está ativa.");
        }

        $sql = "SELECT codfor, nomfor, cgccpf, sigufs, cidfor 
                FROM Sapiens.sapiens.e095for $condicao 
                ORDER BY $orderBy 
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        
        $stmt = $this->connSenior->prepare($sql);

        $i = 1;
        foreach ($params as $valor) {
            $stmt->bindValue($i++, $valor);
        }

        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- MÉTODOS MYSQL (Mantidos) ---
    public function getIdsVinculados($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function adicionarParticipante($idProcesso, $cod, $nome, $doc) {
        $sql = "INSERT INTO licitacao_participantes 
                (id_processo_instancia, id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf) 
                VALUES (:id, :cod, :nome, :doc)
                ON DUPLICATE KEY UPDATE status_participante = 'Selecionado'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':cod' => $cod, ':nome' => $nome, ':doc' => $doc]);
    }

    public function gerarMatrizCotas($idProcesso, $codFornecedor) {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                SELECT id_processo_instancia, num_solicitacao, seq_solicitacao, :cod_forn, NULL 
                FROM processos_itens 
                WHERE id_processo_instancia = :id_proc";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cod_forn' => $codFornecedor, ':id_proc' => $idProcesso]);
    }

    public function removerParticipante($idProcesso, $codFornecedor) {
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
        $this->pdo->prepare("DELETE FROM licitacao_participantes WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
    }
}
?>