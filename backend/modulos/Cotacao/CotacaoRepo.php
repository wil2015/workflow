<?php
/*require_once __DIR__ . '/../../core/BaseRepository.php'; */
namespace App\Modulos\Cotacao;

use App\Core\BaseRepository;
use App\Modulos\ExecucaoFluxo\Query\ListarOrdensCompraQuery;
use PDO;
class CotacaoRepo extends BaseRepository
{
    public function buscarItensDoProcesso($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_item, num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDetalheSenior($num, $seq) {
        if (!$this->connSenior) return null;
        $stmt = $this->connSenior->prepare(ListarOrdensCompraQuery::detalheSql());
        $stmt->execute([$num, $seq]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarFornecedoresEValores($idProcesso, $num, $seq) {
        $sql = "SELECT p.id_fornecedor_senior, p.nome_do_fornecedor, o.valor_unitario
                FROM licitacao_participantes p
                LEFT JOIN licitacao_itens_ofertados o ON p.id_processo_instancia = o.id_processo_instancia AND p.id_fornecedor_senior = o.id_fornecedor_senior AND o.num_solicitacao = ? AND o.seq_solicitacao = ?
                WHERE p.id_processo_instancia = ?
                ORDER BY p.nome_do_fornecedor";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$num, $seq, $idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarValorUnitario($idProcesso, $idItem, $num, $seq, $codForn, $valorFloat) {
      
        $sql = "INSERT INTO licitacao_itens_ofertados (id_processo_instancia, id_item, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) VALUES (:id, :id_item, :num, :seq, :cod, :val) ON DUPLICATE KEY UPDATE valor_unitario = :val";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':id_item' => $idItem, ':num' => $num, ':seq' => $seq, ':cod' => $codForn, ':val' => $valorFloat]);
    }
}
