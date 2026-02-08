<?php
// Carrega as configurações de banco (caminho relativo à pasta 'core')

namespace App\Core;
use Exception; // Classes nativas do PHP precisam de "use" ou barra invertida \Exceptionabstract class BaseController
abstract class BaseController
{
    protected $pdo;
    protected $connSenior;
    protected $service;
    protected $params = [];

    public function __construct($pdo, $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
        
        $this->parseInput();
        
        // Define JSON como padrão
        header('Content-Type: application/json; charset=utf-8');
    }

    private function parseInput()
    {
        $this->params = $_REQUEST; // Pega GET e POST
        
        // Pega JSON (útil para Vue.js/Axios)
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        
        if (is_array($input)) {
            $this->params = array_merge($this->params, $input);
            $_POST = array_merge($_POST, $input); // Retrocompatibilidade
        }
    }

    public function handleRequest()
    {
        try {
            $acao = $this->params['acao'] ?? '';
            
            // Se vazio, tenta pegar de um parametro padrão ou lança erro
            if (empty($acao)) {
                 // Tratamento especial para controllers que usam 'home' como default
                 if (method_exists($this, 'getAcaoPadrao')) {
                     $acao = $this->getAcaoPadrao();
                 } else {
                     throw new Exception("Nenhuma ação fornecida.");
                 }
            }

            $response = $this->executarAcao($acao);

            if ($response !== null) {
                echo json_encode($response);
            }

        } catch (Exception $e) {
            $this->tratarErro($e);
        }
    }

    abstract protected function executarAcao(string $acao);

    // Helper para transações seguras
    protected function atomic(callable $function)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $function();
            $this->pdo->commit();
            return $result;
        } catch (Exception $ex) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $ex;
        }
    }

    protected function tratarErro(Exception $e)
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
        exit;
    }
}