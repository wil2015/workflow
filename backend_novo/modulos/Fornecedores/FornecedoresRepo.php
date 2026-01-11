<?php
class FornecedoresRepo {
    private $pdo;       // MySQL
    private $connSenior; // SQL Server

    public function __construct($pdo, $connSenior) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- LEITURA (SENIOR) ---
    public function buscarTotalSenior($condicao, $params) {
        $sql = "SELECT COUNT(*) as T FROM Sapiens.sapiens.e095for $condicao";
        $stmt = sqlsrv_query($this->connSenior, $sql, $params);
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)['T'];
    }

    public function buscarFornecedoresSenior($condicao, $orderBy, $offset, $limit, $params) {
        $sql = "SELECT codfor, nomfor, cgccpf, sigufs, cidfor 
                FROM Sapiens.sapiens.e095for $condicao 
                ORDER BY $orderBy 
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        $params[] = $offset;
        $params[] = $limit;

        $stmt = sqlsrv_query($this->connSenior, $sql, $params);
        if ($stmt === false) throw new Exception("Erro Senior: " . print_r(sqlsrv_errors(), true));
        
        $lista = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $lista[] = $row;
        }
        return $lista;
    }

    // --- LEITURA (MYSQL) ---
    public function getIdsVinculados($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // --- ESCRITA (MYSQL) ---
    public function adicionarParticipante($idProcesso, $cod, $nome, $doc) {
        // CORREÇÃO: IGUAL AO LEGADO
        // Removemos 'status_participante' do INSERT para não estourar o limite da coluna.
        // O banco usará o valor padrão na criação.
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
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")
                  ->execute([$idProcesso, $codFornecedor]);

        $this->pdo->prepare("DELETE FROM licitacao_participantes WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")
                  ->execute([$idProcesso, $codFornecedor]);
    }
}