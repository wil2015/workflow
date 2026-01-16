<?php
class GradeComparativaRepo {
    private $pdo;
    private $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null) {
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
        // [CORREÇÃO AQUI]: A tabela processos_itens tem a coluna 'id', não 'id_item'.
        $sql = "SELECT num_solicitacao, seq_solicitacao, quantidade, id 
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
        try {
            $stmt = $this->connSenior->prepare($sql);
            $stmt->execute([$num, $seq]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['cplpro'] : null;
        } catch (PDOException $e) { return null; }
    }

    public function buscarTodasOfertas($idProcesso) {
        $sql = "SELECT id, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario, 
                       atende, justificativa_da_recusa 
                FROM licitacao_itens_ofertados 
                WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA ---

    public function atualizarStatusOferta($idOferta, $atende, $justificativa) {
        $sql = "UPDATE licitacao_itens_ofertados 
                SET atende = ?, justificativa_da_recusa = ? 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$atende, $justificativa, $idOferta]);
    }

    public function limparGradeCustos($idProcesso) {
        $sql = "DELETE FROM grade_de_custos WHERE id_instancia_processo = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
    }

    public function inserirItemGrade($idProcesso, $idForn, $idItem, $valor) {
        // Insere na tabela final mapeando o ID recuperado de processos_itens
        $sql = "INSERT INTO grade_de_custos 
                (id_instancia_processo, id_fornecedor_senior, id_item, valor_cotado)
                VALUES (?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $idForn, $idItem, $valor]); 
    }

    public function salvarValorFinal($idProcesso, $valorTotal) {
        $sql = "UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$valorTotal, $idProcesso]);
    }
}
?>