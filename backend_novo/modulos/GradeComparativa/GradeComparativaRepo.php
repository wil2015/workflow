<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class GradeComparativaRepo extends BaseRepository
{
    // =========================================================================
    // MÉTODOS DE LEITURA (Estes eram os que estavam faltando)
    // =========================================================================

    public function buscarParticipantes($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior as id, nome_do_fornecedor as nome, cnpj_cpf FROM licitacao_participantes WHERE id_processo_instancia = ? ORDER BY nome_do_fornecedor ASC");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensBasicos($idProcesso) {
        // Traz a quantidade do banco para o cálculo correto
        $stmt = $this->pdo->prepare("SELECT id AS id_item, num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDescricaoSenior($num, $seq) {
        if (!$this->connSenior) return null;
        try {
            $stmt = $this->connSenior->prepare("SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?");
            $stmt->execute([$num, $seq]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['cplpro'] : null;
        } catch (PDOException $e) { return null; }
    }

    public function buscarTodasOfertas($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario, atende, justificativa_da_recusa FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // MÉTODOS DE ESCRITA (Consolidação e Bulk Insert)
    // =========================================================================

    public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa) {
        $stmt = $this->pdo->prepare("UPDATE licitacao_itens_ofertados SET atende = ?, justificativa_da_recusa = ? WHERE id = ?");
        $stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
    }

    public function limparEAtualizarTotal($idProcesso, $valorFinal) {
        // Limpa a grade antiga
        $stmtDel = $this->pdo->prepare("DELETE FROM grade_de_custos WHERE id_instancia_processo = ?");
        $stmtDel->execute([$idProcesso]);

        // Atualiza o total no cabeçalho
        $stmtUpd = $this->pdo->prepare("UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?");
        $stmtUpd->execute([$valorFinal, $idProcesso]);
    }

    public function inserirLote($idProcesso, $rows) {
        if (empty($rows)) return 0;

        $campos = [];
        $valores = [];
        foreach ($rows as $r) {
            $campos[] = "(?, ?, ?, ?, ?, ?, ?)";
            array_push($valores, 
                $idProcesso, 
                (int)$r['id_fornecedor_senior'], 
                (int)$r['id_item'], 
                (float)$r['quantidade'], 
                (float)$r['valor_cotado'], 
                (float)$r['valor_total'], 
                (int)$r['vencedor']
            );
        }

        $sql = "INSERT INTO grade_de_custos 
                (id_instancia_processo, id_fornecedor_senior, id_item, quantidade, valor_cotado, valor_total, vencedor) 
                VALUES " . implode(',', $campos);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($valores);
        
        return count($rows);
    }
}