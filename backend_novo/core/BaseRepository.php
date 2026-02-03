<?php
// /core/BaseRepository.php

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

    // Opcional: Helper para garantir que o Senior está conectado antes de rodar queries
    protected function checkSenior() {
        if (!$this->connSenior) {
            throw new Exception("Conexão com o banco Senior não está ativa.");
        }
    }

    public function executarEmTransacao(callable $funcao) {
        // Verifica se já existe uma transação ativa para evitar erro de aninhamento
        $jaEmTransacao = $this->pdo->inTransaction();

        if (!$jaEmTransacao) {
            $this->pdo->beginTransaction();
        }

        try {
            // Executa a lógica de negócio (Service)
            $resultado = $funcao(); 

            if (!$jaEmTransacao) {
                $this->pdo->commit();
            }
            
            return $resultado;
        } catch (Exception $e) {
            if (!$jaEmTransacao) {
                $this->pdo->rollBack();
            }
            throw $e; // Joga o erro para cima (para o Controller tratar)
        }
    }
}