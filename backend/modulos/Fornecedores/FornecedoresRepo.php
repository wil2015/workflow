<?php
/*require_once __DIR__ . '/../../core/BaseRepository.php';
// Importa o Query Object
require_once __DIR__ . '/Query/ListarFornecedoresQuery.php'; */


namespace App\Modulos\Fornecedores;

use App\Core\BaseRepository;
use App\Modulos\Fornecedores\Query\ListarFornecedoresQuery; // Importa Query 
use PDO;
class FornecedoresRepo extends BaseRepository
{
    // --- USO DO QUERY OBJECT (SENIOR) ---
    public function buscarFornecedoresComFiltros($start, $length, $search, $idsVinculados) {
        $this->checkSenior();

        // 1. Instancia
        $query = new ListarFornecedoresQuery($this->connSenior);

        // 2. Configura Prioridade
        $query->priorizarVinculados($idsVinculados);

        // 3. Configura Busca
        $query->aplicarBusca($search);

        // 4. Executa
        return $query->executar($start, $length);
    }

    // --- MÉTODOS MYSQL (MANTIDOS IGUAIS) ---
    
    public function getIdsVinculados($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function adicionarParticipante($idProcesso, $cod, $nome, $doc) {
        $sql = "INSERT INTO licitacao_participantes (id_processo_instancia, id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf) 
                VALUES (:id, :cod, :nome, :doc) 
                ON DUPLICATE KEY UPDATE status_participante = 'Selecionado'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':cod' => $cod, ':nome' => $nome, ':doc' => $doc]);
    }

    public function gerarMatrizCotas($idProcesso, $codFornecedor) {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados (id_processo_instancia, id_item, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) 
                SELECT id_processo_instancia, id_item, num_solicitacao, seq_solicitacao, :cod_forn, NULL 
                FROM processos_itens WHERE id_processo_instancia = :id_proc";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cod_forn' => $codFornecedor, ':id_proc' => $idProcesso]);
    }

    public function removerParticipante($idProcesso, $codFornecedor) {
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
        $this->pdo->prepare("DELETE FROM licitacao_participantes WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
    }
}
