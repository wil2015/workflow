<?php
// backend/infra/BaseController.php

abstract class BaseController {
    
    protected $pdo;       
    protected $connSenior; 

    public function __construct() {
        // 1. Garante que a resposta seja sempre JSON (resolve bugs de acentuação e formato)
        header('Content-Type: application/json; charset=utf-8');

        // 2. Conecta usando seus arquivos JÁ EXISTENTES
        $this->conectarViaArquivos();
    }

    private function conectarViaArquivos() {
        // Truque: Ao fazer o require aqui dentro, as variáveis $pdo e $connSenior 
        // criadas nesses arquivos ficam disponíveis no escopo desta função.
        
        try {
            // Ajuste o caminho relativo conforme onde está o BaseController
            require __DIR__ . '/../../db_conexao.php'; // Cria a var $pdo
            
            if (isset($pdo)) {
                $this->pdo = $pdo;
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else {
                throw new Exception("Variável \$pdo não foi criada pelo db_conexao.php");
            }

        } catch (Exception $e) {
            $this->jsonError("Erro Crítico MySQL: " . $e->getMessage(), 500);
        }

        try {
            require __DIR__ . '/../../db_senior.php'; // Cria a var $connSenior
            
            if (isset($connSenior)) {
                $this->connSenior = $connSenior;
                // Garante configurações vitais para o Service funcionar bem
                $this->connSenior->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } 
            // Não lançamos erro aqui se falhar, pois o sistema pode funcionar offline do Senior às vezes

        } catch (Exception $e) {
            // Apenas loga ou ignora, dependendo da sua regra de negócio
             $this->jsonError("Erro Crítico Senior: " . $e->getMessage(), 500);
        }
    }

    // --- MÉTODOS AUXILIARES QUE VOCÊ GOSTOU ---

    protected function getParams() {
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
        return array_merge($_REQUEST, $json);
    }

    protected function jsonResponse($data = [], $msg = 'Sucesso') {
        echo json_encode(['sucesso' => true, 'msg' => $msg, 'data' => $data]);
        exit;
    }

    protected function jsonError($msg, $code = 400) {
        http_response_code($code);
        echo json_encode(['erro' => $msg, 'sucesso' => false]); // Padrão que seu Vue já espera
        exit;
    }
}
?>