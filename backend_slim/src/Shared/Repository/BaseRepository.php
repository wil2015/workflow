<?php

declare(strict_types=1);

namespace Shared\Repository;

use PDO;
use Exception;
use Throwable;

abstract class BaseRepository
{
    protected PDO $pdo;
    protected ?PDO $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    protected function checkSenior(): void
    {
        if (!$this->connSenior) {
            throw new Exception("Conexao com o banco Senior nao esta ativa.");
        }
    }

    protected function callProcedure(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        try {
            do { /* consume rowsets */ } while ($stmt->nextRowset());
        } catch (Exception $e) { }
        $stmt->closeCursor();
        return true;
    }

    public function executarEmTransacao(callable $funcao): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $funcao();
        }

        $this->pdo->beginTransaction();
        try {
            $resultado = $funcao();
            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
