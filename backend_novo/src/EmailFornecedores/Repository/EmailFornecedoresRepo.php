<?php

declare(strict_types=1);

namespace EmailFornecedores\Repository;

use Exception;
use PDO;

class EmailFornecedoresRepo
{
    private PDO $pdo;
    private ?PDO $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    public function buscarParticipantes(int $idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT id, id_fornecedor_senior, nome_do_fornecedor FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarEmailsNoSenior($codFornecedor): array
    {
        if (!$this->connSenior) return [];
        $emails = [];
        try {
            $stmt = $this->connSenior->prepare("SELECT intnet as email FROM Sapiens.sapiens.e095for WHERE codfor = ?");
            $stmt->execute([$codFornecedor]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['email'])) {
                    $partes = explode(';', $row['email']);
                    foreach ($partes as $p) $emails[] = strtolower(trim($p));
                }
            }
        } catch (Exception $e) { }
        return array_unique($emails);
    }

    public function upsertEmailMestre($codFornecedor, $email): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO email_fornecedor (id_fornecedor_senior, email_fornecedor) VALUES (?, ?)");
        $stmt->execute([$codFornecedor, $email]);
    }

    public function buscarEmailsParaSelecao(int $idProcesso): array
    {
        $sql = "SELECT lp.id as id_participante, lp.nome_do_fornecedor, lp.id_fornecedor_senior, ef.email_fornecedor, (CASE WHEN ei.id IS NOT NULL THEN 1 ELSE 0 END) as selecionado
                FROM licitacao_participantes lp
                INNER JOIN email_fornecedor ef ON lp.id_fornecedor_senior = ef.id_fornecedor_senior
                LEFT JOIN email_instancia ei ON ei.id_licitacao_participante = lp.id AND ei.email_fornecedor = ef.email_fornecedor AND ei.id_processo_instancia = ?
                WHERE lp.id_processo_instancia = ?
                ORDER BY lp.nome_do_fornecedor, ef.email_fornecedor";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function limparSelecaoAnterior(int $idProcesso): void
    {
        $this->pdo->prepare("DELETE FROM email_instancia WHERE id_processo_instancia = ?")->execute([$idProcesso]);
    }

    public function salvarEmailInstancia($idProcesso, $idParticipante, $idFornSenior, $email): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO email_instancia (id_processo_instancia, id_licitacao_participante, id_fornecedor_senior, email_fornecedor, flag_ativo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$idProcesso, $idParticipante, $idFornSenior, $email]);
    }
}
