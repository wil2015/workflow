<?php
namespace App\Core;

use PDO;
use Exception;

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

    /**
     * Executa uma Procedure e limpa o cursor imediatamente.
     * Isso resolve o erro de "Packets out of order" ou trava de SELECTs subsequentes.
     */
    protected function callProcedure($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        
        // Executa com os parâmetros
        $stmt->execute($params);
        
        // Loop para consumir todos os rowsets (resultados extras que a procedure retorna)
        // Isso é o que "destrava" o PDO para a próxima consulta
        try {
            do {
                // Consumindo resultados pendentes...
            } while ($stmt->nextRowset());
        } catch (Exception $e) {
            // Ignora se não houver mais rowsets
        }

        $stmt->closeCursor(); 
        return true;
    }
}