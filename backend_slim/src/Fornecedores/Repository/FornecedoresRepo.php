<?php

declare(strict_types=1);

namespace Fornecedores\Repository;

use Shared\Repository\BaseRepository;
use Fornecedores\Query\ListarFornecedoresQuery;
use PDO;

class FornecedoresRepo extends BaseRepository
{
    public function buscarFornecedoresComFiltros($start, $length, $search, $idsVinculados): array
    {
        $this->checkSenior();
        $query = new ListarFornecedoresQuery($this->connSenior);
        $query->priorizarVinculados($idsVinculados);
        $query->aplicarBusca($search);
        return $query->executar($start, $length);
    }

    public function getIdsVinculados($idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function adicionarParticipante($idProcesso, $cod, $nome, $doc): void
    {
        $sql = "INSERT INTO licitacao_participantes (id_processo_instancia, id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf)
                VALUES (:id, :cod, :nome, :doc)
                ON DUPLICATE KEY UPDATE status_participante = 'Selecionado'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':cod' => $cod, ':nome' => $nome, ':doc' => $doc]);
    }

    public function gerarMatrizCotas($idProcesso, $codFornecedor): void
    {
        $sql = "INSERT IGNORE INTO licitacao_itens_ofertados (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                SELECT id_processo_instancia, num_solicitacao, seq_solicitacao, :cod_forn, NULL
                FROM processos_itens WHERE id_processo_instancia = :id_proc";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cod_forn' => $codFornecedor, ':id_proc' => $idProcesso]);
    }

    public function removerParticipante($idProcesso, $codFornecedor): void
    {
        $this->pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
        $this->pdo->prepare("DELETE FROM licitacao_participantes WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?")->execute([$idProcesso, $codFornecedor]);
    }
}
