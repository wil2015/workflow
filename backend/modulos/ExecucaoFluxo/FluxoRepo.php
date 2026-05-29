<?php
/*require_once __DIR__ . '/../../core/BaseRepository.php'; */


namespace App\Modulos\ExecucaoFluxo;

use App\Core\BaseRepository;
use App\Modulos\ExecucaoFluxo\Query\ListarOrdensCompraQuery;
use PDO;
class FluxoRepo extends BaseRepository
{
    // =========================================================================
    //  MÉTODOS SQL SERVER (Senior Sapiens)
    // =========================================================================

    public function buscarQuantidadeOrdemCompra($numeroOc, $sequenciaOc) {
        if (!$this->connSenior) return 1.0;
        $sql = ListarOrdensCompraQuery::detalheSql();
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$numeroOc, $sequenciaOc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['qtdsol'] : 1.0;
    }

    // --- USO DO QUERY OBJECT ---
    public function buscarOrdensCompraSeniorRaw($start, $length, $search, $campoOrdenacao, $dirSQL, $meusItens, $bloqueados) {
        $this->checkSenior();

        $query = new ListarOrdensCompraQuery($this->connSenior);
        $query->configurarRegras($meusItens, $bloqueados);
        $query->aplicarBusca($search);

        return $query->executar($start, $length, $campoOrdenacao, $dirSQL);
    }

    // =========================================================================
    //  MÉTODOS MYSQL (Aplicação)
    // =========================================================================

    public function getInstanciaCompleta($id) {
        $sql = "SELECT p.*, d.arquivo_xml, d.nome_do_fluxo, d.id_fluxo_definicao 
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                WHERE p.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarIdPorOrdemCompra($numeroOc) {
        $stmt = $this->pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
        $stmt->execute([$numeroOc]);
        return $stmt->fetchColumn();
    }
    
    public function buscarItensOcupados() {
        return $this->pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function listarFluxosDisponiveis() {
        return $this->pdo->query("SELECT id_fluxo_definicao as id, nome_do_fluxo, 'blue' as cor_ui FROM nome_do_fluxo")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTodosProcessos() {
        $sql = "SELECT p.id, p.data_inicio, p.status_atual, p.id_processo_senior, d.nome_do_fluxo 
                FROM processos_instancia p 
                LEFT JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao 
                ORDER BY p.id DESC LIMIT 50";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA ---

    public function criarProcessoPorOrdemCompra($numeroOc, $idFluxo) {
        $sql = "INSERT INTO processos_instancia (id_processo_senior, id_processo_instancia, id_fluxo_definicao, data_inicio, status_atual, etapa_bpmn_atual) 
                VALUES (:num, :num, :fluxo, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':num' => $numeroOc, ':fluxo' => $idFluxo]);
        return $this->pdo->lastInsertId();
    }

    public function adicionarItemOrdemCompra($idProcesso, $numeroOc, $sequenciaOc, $qtd) {
        $sql = "INSERT IGNORE INTO processos_itens (id_processo_instancia, num_solicitacao, seq_solicitacao, quantidade) 
                VALUES (:id, :num, :seq, :qtd)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':num' => $numeroOc, ':seq' => $sequenciaOc, ':qtd' => $qtd]);
        return $stmt->rowCount() > 0;
    }

    public function inicializarCotacao($idProcesso, $numeroOc, $sequenciaOc) {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) 
                SELECT ?, ?, ?, id_fornecedor_senior, NULL FROM licitacao_participantes WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $numeroOc, $sequenciaOc, $idProcesso]);
    }

    public function removerItemOrdemCompra($idProcesso, $numeroOc, $sequenciaOc) {
        $params = [':id' => $idProcesso, ':num' => $numeroOc, ':seq' => $sequenciaOc];
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = :id AND num_solicitacao = :num AND seq_solicitacao = :seq")->execute($params);
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = :id AND num_solicitacao = :num AND seq_solicitacao = :seq")->execute($params);
    }

    public function excluirProcesso($idProcesso) {
        $params = [':id' => $idProcesso];
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = :id")->execute($params);
        $this->pdo->prepare("DELETE FROM processos_itens WHERE id_processo_instancia = :id")->execute($params);
        $this->pdo->prepare("DELETE FROM processos_instancia WHERE id = :id")->execute($params);
    }

    public function atualizarDatasPrevisao($idProcesso, $dtCotacao, $dtRecebimento) {
        $sql = "UPDATE processos_instancia 
                SET 
                    data_esperada_da_cotacao = :dt_cot,
                    data_esperada_do_recebimento = :dt_rec
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':dt_cot' => $dtCotacao,
            ':dt_rec' => $dtRecebimento,
            ':id'     => $idProcesso
        ]);
    }
}
