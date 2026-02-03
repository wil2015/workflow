<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class GradeComparativaRepo extends BaseRepository
{
    // =========================================================================
    // MÉTODOS DE LEITURA 
    // =========================================================================

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
    // NOVOS MÉTODOS (Arquitetura via Stored Procedure)
    // =========================================================================

    public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa) {
        // Mantemos este pois precisamos salvar a intenção do usuário antes de rodar a SP
        $stmt = $this->pdo->prepare("UPDATE licitacao_itens_ofertados SET atende = ?, justificativa_da_recusa = ? WHERE id = ?");
        $stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
    }

    /**
     * Dispara a procedure que limpa a grade antiga, recalcula vencedores
     * baseada nas flags 'atende' e insere os novos dados.
     */
    public function executarProcedureConsolidacao($idProcesso) {
        // Chamada direta à procedure fornecida
        $stmt = $this->pdo->prepare("CALL fundunesp_workflow.sp_consolidar_grade_custos_completa(?)");
        $stmt->execute([$idProcesso]);
    }

    /**
     * Busca o valor final já calculado pela procedure na tabela processos_instancia
     */
    public function buscarValorFinalProcesso($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT valor_final_processo FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn(); 
    }
    
    /**
     * Conta quantos itens foram gerados na grade (para feedback visual)
     */
    public function contarItensGrade($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grade_de_custos WHERE id_instancia_processo = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn();
    }
}