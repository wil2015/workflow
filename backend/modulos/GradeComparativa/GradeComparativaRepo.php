<?php
namespace App\Modulos\GradeComparativa;

use App\Core\BaseRepository;
use App\Modulos\ExecucaoFluxo\Query\ListarOrdensCompraQuery;
use PDO;

class GradeComparativaRepo extends BaseRepository
{
    // --- NOVO: Chama a Procedure de Leitura ---
    public function buscarGradeSimulada($idProcesso) {
        $stmt = $this->pdo->prepare("CALL sp_simular_grade(?)");
        $stmt->execute([$idProcesso]);
        
        // O fetchAll retorna a lista plana ordenada
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // Libera para próximas chamadas
        return $dados;
    }

    // --- MÉTODOS DE APOIO (Mantidos para detalhes específicos) ---
    public function buscarDescricaoSenior($num, $seq) {
        if (!$this->connSenior) return null;
        try {
            $stmt = $this->connSenior->prepare(ListarOrdensCompraQuery::detalheSql());
            $stmt->execute([$num, $seq]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['cplpro'] : null;
        } catch (\Exception $e) { return null; }
    }

    // --- MÉTODOS DE ESCRITA (Mantidos) ---
    public function atualizarAtendeJustificativa($ofertaId, $atende, $justificativa) {
        $stmt = $this->pdo->prepare("UPDATE licitacao_itens_ofertados SET atende = ?, justificativa_da_recusa = ? WHERE id = ?");
        $stmt->execute([$atende ? 1 : 0, $justificativa, $ofertaId]);
    }

    public function executarProcedureConsolidacao($idProcesso) {
        $this->callProcedure("CALL fundunesp_workflow.sp_consolidar_grade_custos_completa(?)", [$idProcesso]);
    }

    public function buscarValorFinalProcesso($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT valor_final_processo FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn(); 
    }
    
    public function contarItensGrade($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grade_de_custos WHERE id_instancia_processo = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchColumn();
    }
}
