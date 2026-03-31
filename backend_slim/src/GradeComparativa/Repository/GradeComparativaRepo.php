<?php

declare(strict_types=1);

namespace GradeComparativa\Repository;

use Shared\Repository\BaseRepository;
use PDO;
use PDOException;

class GradeComparativaRepo extends BaseRepository
{
    public function buscarParticipantes($idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior as id, nome_do_fornecedor as nome, cnpj_cpf FROM licitacao_participantes WHERE id_processo_instancia = ? ORDER BY nome_do_fornecedor ASC");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensBasicos($idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT id AS id_item, num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDescricaoSenior($num, $seq): ?string
    {
        if (!$this->connSenior) return null;
        try {
            $stmt = $this->connSenior->prepare("SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?");
            $stmt->execute([$num, $seq]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['cplpro'] : null;
        } catch (PDOException $e) { return null; }
    }

    public function buscarTodasOfertas($idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT id, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario, atende, justificativa_da_recusa FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa): void
    {
        $stmt = $this->pdo->prepare("UPDATE licitacao_itens_ofertados SET atende = ?, justificativa_da_recusa = ? WHERE id = ?");
        $stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
    }

    public function executarProcedureConsolidacao($idProcesso): void
    {
        $stmt = $this->pdo->prepare("CALL fundunesp_workflow.sp_consolidar_grade_custos_completa(?)");
        $stmt->execute([$idProcesso]);
    }

    public function buscarValorFinalProcesso($idProcesso)
    {
        $stmt = $this->pdo->prepare("SELECT valor_final_processo FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn();
    }

    public function contarItensGrade($idProcesso)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grade_de_custos WHERE id_instancia_processo = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn();
    }
}
