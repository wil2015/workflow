<?php
class CotacaoRepo {
    private $pdo;       // MySQL (PDO)
    private $connSenior; // SQL Server (PDO)

    public function __construct(PDO $pdo, ?PDO $connSenior = null) {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    // --- LEITURA ---

    public function buscarItensDoProcesso($idProcesso) {
        // Busca a lista básica de itens vinculados no MySQL
        $sql = "SELECT num_solicitacao, seq_solicitacao 
                FROM processos_itens 
                WHERE id_processo_instancia = ? 
                ORDER BY num_solicitacao, seq_solicitacao";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDetalheSenior($num, $seq) {
        // Busca descrição, unidade e quantidade no Senior (Sapiens) via PDO
        if (!$this->connSenior) return null;
        
        $sql = "SELECT cplpro, qtdsol, unimed 
                FROM Sapiens.sapiens.e405sol 
                WHERE numsol = ? AND seqsol = ?";
        
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$num, $seq]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function buscarFornecedoresEValores($idProcesso, $num, $seq) {
        // Busca todos os fornecedores do processo e, se houver, o preço lançado
        $sql = "SELECT 
                    p.id_fornecedor_senior, 
                    p.nome_do_fornecedor,
                    o.valor_unitario
                FROM licitacao_participantes p
                LEFT JOIN licitacao_itens_ofertados o 
                    ON p.id_processo_instancia = o.id_processo_instancia
                    AND p.id_fornecedor_senior = o.id_fornecedor_senior
                    AND o.num_solicitacao = ? 
                    AND o.seq_solicitacao = ?
                WHERE p.id_processo_instancia = ?
                ORDER BY p.nome_do_fornecedor";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$num, $seq, $idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA ---

    public function salvarValorUnitario($idProcesso, $num, $seq, $codForn, $valorFloat) {
        // UPSERT: Insere ou Atualiza se já existir
        $sql = "INSERT INTO licitacao_itens_ofertados 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) 
                VALUES (:id, :num, :seq, :cod, :val)
                ON DUPLICATE KEY UPDATE valor_unitario = :val";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idProcesso,
            ':num' => $num,
            ':seq' => $seq,
            ':cod' => $codForn,
            ':val' => $valorFloat
        ]);
    }
}
?>