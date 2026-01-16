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
		$sql = "SELECT id AS id_item, num_solicitacao, seq_solicitacao, quantidade
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
		$sql = "SELECT id, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario, atende, justificativa_da_recusa
		        FROM licitacao_itens_ofertados
		        WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

	public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa) {
		$sql = "UPDATE licitacao_itens_ofertados
		        SET atende = ?, justificativa_da_recusa = ?
		        WHERE id = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
	}

	public function limparGradeDeCustos($idProcesso) {
		$sql = "DELETE FROM grade_de_custos WHERE id_instancia_processo = ?";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([$idProcesso]);
	}

	/**
	 * @param array<int, array{ id_fornecedor_senior:int, id_item:int, valor_cotado:float }>
	 */
	public function inserirGradeDeCustos($idProcesso, $rows) {
		if (empty($rows)) return 0;

			// Garante IDs únicos mesmo se a tabela não tiver AUTO_INCREMENT.
			// (Usa lock na última linha para evitar concorrência em transações.)
			$maxStmt = $this->pdo->query("SELECT id FROM grade_de_custos ORDER BY id DESC LIMIT 1 FOR UPDATE");
			$row = $maxStmt ? $maxStmt->fetch(PDO::FETCH_ASSOC) : null;
			$max = $row ? (int)$row['id'] : 0;
			$nextId = $max + 1;

		$sql = "INSERT INTO grade_de_custos (id, id_instancia_processo, id_fornecedor_senior, id_item, valor_cotado)
		        VALUES (?, ?, ?, ?, ?)";
		$stmt = $this->pdo->prepare($sql);

		$count = 0;
		foreach ($rows as $r) {
			$stmt->execute([
				$nextId++,
				$idProcesso,
				(int)$r['id_fornecedor_senior'],
				(int)$r['id_item'],
				$r['valor_cotado'],
			]);
			$count++;
		}

		return $count;
	}

    // --- ESCRITA (MYSQL - MANTIDO) ---

    public function salvarValorFinal($idProcesso, $valorTotal) {
        $sql = "UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$valorTotal, $idProcesso]);
    }
}
?>