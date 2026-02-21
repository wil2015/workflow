<?php

declare(strict_types=1);

namespace Cotacao\Repository;

use PDO;

class CotacaoRepo
{
    private PDO $pdo;
    private ?PDO $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    public function buscarItensDoProcesso($idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDetalheSenior($num, $seq): ?array
    {
        if (!$this->connSenior) return null;
        $stmt = $this->connSenior->prepare("SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?");
        $stmt->execute([$num, $seq]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarFornecedoresEValores($idProcesso, $num, $seq): array
    {
        $sql = "SELECT p.id_fornecedor_senior, p.nome_do_fornecedor, o.valor_unitario
                FROM licitacao_participantes p
                LEFT JOIN licitacao_itens_ofertados o ON p.id_processo_instancia = o.id_processo_instancia AND p.id_fornecedor_senior = o.id_fornecedor_senior AND o.num_solicitacao = ? AND o.seq_solicitacao = ?
                WHERE p.id_processo_instancia = ?
                ORDER BY p.nome_do_fornecedor";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$num, $seq, $idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarValorUnitario($idProcesso, $num, $seq, $codForn, $valorFloat): void
    {
        $sql = "INSERT INTO licitacao_itens_ofertados (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) VALUES (:id, :num, :seq, :cod, :val) ON DUPLICATE KEY UPDATE valor_unitario = :val2";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idProcesso, ':num' => $num, ':seq' => $seq, ':cod' => $codForn, ':val' => $valorFloat, ':val2' => $valorFloat]);
    }
}
