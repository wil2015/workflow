<?php
namespace App\Core;

use Exception;
use Throwable;

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
    }

    // Método Utilitário: Unifica $_GET, $_POST e JSON body
    private function parseInput()
    {
        $this->params = $_REQUEST; 
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (is_array($input)) {
            $this->params = array_merge($this->params, $input);
        }
    }

    // --- TEMPLATE METHOD (O Esqueleto) ---
    // Este método controla o fluxo da vida da requisição
    final public function handleRequest()
    {
        // 1. Limpeza preventiva de buffer (evita lixo antes do JSON)
        if (ob_get_length()) ob_clean();
        ob_start();

        try {
            // 2. Determina qual ação executar
            $acao = $this->params['acao'] ?? '';
            
            if (empty($acao)) {
                 if (method_exists($this, 'getAcaoPadrao')) {
                     $acao = $this->getAcaoPadrao();
                 } else {
                     throw new Exception("Nenhuma ação fornecida.");
                 }
            }

            // 3. HOOK METHOD: Chama a implementação específica do filho
            $response = $this->executarAcao($acao);

            // 4. Finaliza com sucesso
            $this->enviarResposta($response);

        } catch (Throwable $e) {
            // 5. Tratamento centralizado de erro
            $this->tratarErro($e);
        }
    }

    // Contrato que todo Controller filho deve assinar
    abstract protected function executarAcao(string $acao);

    // --- HELPERS PARA OS FILHOS ---

    protected function getParam($key, $default = null, $type = 'string') {
        $val = $this->params[$key] ?? $default;
        if ($type === 'int') return (int)$val;
        return $val;
    }

    // Wrapper para Transações Atômicas
    protected function atomic(callable $function)
    {
        if ($this->pdo->inTransaction()) {
            return $function();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $function();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    // --- RESPOSTAS HTTP ---

    private function enviarResposta($dados) {
        $lixo = ob_get_clean(); // Pega warnings se houver
        if ($lixo) error_log("Lixo no buffer: $lixo");

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados);
        exit;
    }

    private function tratarErro(Throwable $e)
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        ob_end_clean(); 
        
        http_response_code(400); 
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => false,
            'erro' => $e->getMessage(),
            'local' => basename($e->getFile()) . ':' . $e->getLine()
        ]);
        exit;
    }
}