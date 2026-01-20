<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class GradeComparativaRepo extends BaseRepository
{
    // --- LEITURA (MYSQL) ---

    public function buscarParticipantes($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior as id, nome_do_fornecedor as nome, cnpj_cpf FROM licitacao_participantes WHERE id_processo_instancia = ? ORDER BY nome_do_fornecedor ASC");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensBasicos($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id AS id_item, num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- LEITURA (SENIOR) ---

    public function buscarDescricaoSenior($num, $seq) {
        if (!$this->connSenior) return null;
        try {
            $stmt = $this->connSenior->prepare("SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?");
            $stmt->execute([$num, $seq]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['cplpro'] : null;
        } catch (PDOException $e) { return null; }
    }

    // --- MISTO (MYSQL) ---

    public function buscarTodasOfertas($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario, atende, justificativa_da_recusa FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

	public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa) {
		$stmt = $this->pdo->prepare("UPDATE licitacao_itens_ofertados SET atende = ?, justificativa_da_recusa = ? WHERE id = ?");
		$stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
	}

	public function limparGradeDeCustos($idProcesso) {
		$this->pdo->prepare("DELETE FROM grade_de_custos WHERE id_instancia_processo = ?")->execute([$idProcesso]);
	}

	public function inserirGradeDeCustos($idProcesso, $rows) {
		if (empty($rows)) return 0;
        $maxStmt = $this->pdo->query("SELECT id FROM grade_de_custos ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $row = $maxStmt ? $maxStmt->fetch(PDO::FETCH_ASSOC) : null;
        $nextId = ($row ? (int)$row['id'] : 0) + 1;

		$sql = "INSERT INTO grade_de_custos (id, id_instancia_processo, id_fornecedor_senior, id_item, valor_cotado) VALUES (?, ?, ?, ?, ?)";
		$stmt = $this->pdo->prepare($sql);
		$count = 0;
		foreach ($rows as $r) {
			$stmt->execute([$nextId++, $idProcesso, (int)$r['id_fornecedor_senior'], (int)$r['id_item'], $r['valor_cotado']]);
			$count++;
		}
		return $count;
	}

    public function salvarValorFinal($idProcesso, $valorTotal) {
        $stmt = $this->pdo->prepare("UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?");
        $stmt->execute([$valorTotal, $idProcesso]);
    }
}