<?php
class GradeComparativaRepo {
    private $pdo;       // MySQL
    private $connSenior; // SQL Server

    public function __construct($pdo, $connSenior) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- LEITURA ---

    public function buscarParticipantes($idProcesso) {
        $sql = "SELECT id_fornecedor_senior as id, nome_do_fornecedor as nome, cnpj_cpf 
                FROM licitacao_participantes 
                WHERE id_processo_instancia = ? 
                ORDER BY nome_do_fornecedor ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensBasicos($idProcesso) {
        $sql = "SELECT num_solicitacao, seq_solicitacao, quantidade 
                FROM processos_itens 
                WHERE id_processo_instancia = ? 
                ORDER BY num_solicitacao, seq_solicitacao";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDescricaoSenior($num, $seq) {
        if (!$this->connSenior) return null;
        $sql = "SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        $stmt = sqlsrv_query($this->connSenior, $sql, [$num, $seq]);
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            return $row['cplpro']; // Retorna a descrição do produto
        }
        return null;
    }

    public function buscarTodasOfertas($idProcesso) {
        // Retorna tabela bruta de preços: [num-seq-codFornecedor] => valor
        $sql = "SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                FROM licitacao_itens_ofertados 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA ---

    public function salvarValorFinal($idProcesso, $valorTotal) {
        $sql = "UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$valorTotal, $idProcesso]);
    }
}