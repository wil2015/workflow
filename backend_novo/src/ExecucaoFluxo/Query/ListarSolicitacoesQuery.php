<?php

declare(strict_types=1);

namespace ExecucaoFluxo\Query;

use PDO;

class ListarSolicitacoesQuery
{
    private PDO $pdo;
    private array $params = [];
    private array $whereClauses = [];
    private string $colunaPeso = '0';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function configurarRegras(array $meusItens, array $bloqueados): void
    {
        $caseParts = [];
        if (!empty($meusItens)) $caseParts[] = "WHEN (" . implode(" OR ", $meusItens) . ") THEN 2";
        if (!empty($bloqueados)) $caseParts[] = "WHEN (" . implode(" OR ", $bloqueados) . ") THEN 1";

        $this->colunaPeso = empty($caseParts) ? '0' : 'CASE ' . implode(' ', $caseParts) . ' ELSE 0 END';

        $filtroStatus = "sitsol IN (1, 2)";
        $todosEmUso = array_merge($meusItens, $bloqueados);
        if (!empty($todosEmUso)) {
            $filtroStatus = "($filtroStatus OR (" . implode(" OR ", $todosEmUso) . "))";
        }
        $this->whereClauses[] = $filtroStatus;
    }

    public function aplicarBusca(?string $search): void
    {
        if ($search) {
            $this->whereClauses[] = "(cplpro LIKE ? OR CAST(numsol AS VARCHAR(20)) LIKE ? OR numprj LIKE ?)";
            $termo = "%$search%";
            $this->params[] = $termo;
            $this->params[] = $termo;
            $this->params[] = $termo;
        }
    }

    public function executar($start, $length, $campoOrdenacao, $dirSQL): array
    {
        $whereSql = '';
        if (!empty($this->whereClauses)) {
            $whereSql = 'WHERE ' . implode(' AND ', $this->whereClauses);
        }

        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol, 
                {$this->colunaPeso} as peso_ordenacao
                FROM Sapiens.sapiens.e405sol $whereSql 
                ORDER BY $campoOrdenacao $dirSQL
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($this->params as $val) $stmt->bindValue($i++, $val);
        $stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) as T FROM Sapiens.sapiens.e405sol $whereSql");
        $i = 1;
        foreach ($this->params as $val) $stmtTotal->bindValue($i++, $val);
        $stmtTotal->execute();
        $total = $stmtTotal->fetchColumn();

        return ['dados' => $dados, 'total' => $total];
    }
}
