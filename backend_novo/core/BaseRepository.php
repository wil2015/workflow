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
}