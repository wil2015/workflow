<?php
class GradeComparativaRepo {
    private $pdo;       // MySQL (PDO)
    private $connSenior; // SQL Server (PDO)

    public function __construct(PDO $pdo, ?PDO $connSenior = null) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- LEITURA (MYSQL - MANTIDO) ---

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

    // --- LEITURA (SENIOR - MIGRADO PARA PDO) ---

    public function buscarDescricaoSenior($num, $seq) {
        // Verifica se a conexão existe antes de tentar preparar
        if (!$this->connSenior) return null;

        $sql = "SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        
        try {
            $stmt = $this->connSenior->prepare($sql);
            $stmt->execute([$num, $seq]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Sapiens costuma usar codificação antiga (ISO-8859-1), 
                // o Service geralmente trata o UTF-8, mas aqui retornamos bruto.
                return $row['cplpro']; 
            }
        } catch (PDOException $e) {
            // Log silencioso ou retorno nulo para não quebrar a grade inteira por um produto
            return null;
        }

        return null;
    }

    // --- MISTO (MYSQL - MANTIDO) ---

    public function buscarTodasOfertas($idProcesso) {
        // Retorna tabela bruta de preços: [num-seq-codFornecedor] => valor
        $sql = "SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                FROM licitacao_itens_ofertados 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA (MYSQL - MANTIDO) ---

    public function salvarValorFinal($idProcesso, $valorTotal) {
        $sql = "UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$valorTotal, $idProcesso]);
    }
}
?>