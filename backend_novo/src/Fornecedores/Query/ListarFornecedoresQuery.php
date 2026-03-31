<?php

declare(strict_types=1);

namespace Fornecedores\Query;

use PDO;

class ListarFornecedoresQuery
{
    private PDO $pdo;
    private array $params = [];
    private array $whereClauses = ["sitfor = 'A'"];
    private string $colunaOrdenacaoVinculados = '0';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function priorizarVinculados(array $idsVinculados): void
    {
        if (!empty($idsVinculados)) {
            $idsSafe = implode(',', array_map('intval', $idsVinculados));
            $this->colunaOrdenacaoVinculados = "CASE WHEN codfor IN ($idsSafe) THEN 1 ELSE 0 END";
        }
    }

    public function aplicarBusca(?string $search): void
    {
        if ($search) {
            $this->whereClauses[] = "(nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
            $termo = "%$search%";
            $this->params[] = $termo;
            $this->params[] = $termo;
            $this->params[] = $termo;
        }
    }

    public function executar($start, $length): array
    {
        $whereSql = 'WHERE ' . implode(' AND ', $this->whereClauses);

        $sql = "SELECT codfor, nomfor, cgccpf, sigufs, cidfor, 
                {$this->colunaOrdenacaoVinculados} as is_vinculado
                FROM Sapiens.sapiens.e095for 
                $whereSql
                ORDER BY is_vinculado DESC, nomfor ASC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($this->params as $val) $stmt->bindValue($i++, $val);
        $stmt->bindValue($i++, (int)$start, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) as T FROM Sapiens.sapiens.e095for $whereSql");
        $i = 1;
        foreach ($this->params as $val) $stmtTotal->bindValue($i++, $val);
        $stmtTotal->execute();
        $total = $stmtTotal->fetchColumn();

        return ['dados' => $dados, 'total' => $total];
    }
}
