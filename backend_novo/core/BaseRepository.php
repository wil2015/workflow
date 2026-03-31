<?php
namespace App\Core;

use PDO;
use Exception;
use Throwable;

abstract class BaseRepository
{
    /** @var PDO */
    protected $pdo;

    /** @var PDO|null */
    protected $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    protected function checkSenior() {
        if (!$this->connSenior) {
            throw new Exception("Conexão com o banco Senior não está ativa.");
        }
    }

    protected function callProcedure($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        try {
            do {
                // Consumindo rowsets pendentes para liberar o PDO
            } while ($stmt->nextRowset());
        } catch (Exception $e) { }
        $stmt->closeCursor(); 
        return true;
    }

    /**
     * --- CORREÇÃO DO ERRO ---
     * Adicionamos este método para permitir transações dentro dos Repositórios/Services
     */
    public function executarEmTransacao(callable $funcao) {
        // Se já houver transação aberta, apenas executa
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