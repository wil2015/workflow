<?php
namespace App\Modulos\Fornecedores;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class FornecedoresController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new FornecedoresService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar':
                return $this->service->listarParaDatatable($this->params);
            case 'salvar_lote':
                return $this->atomic(fn() => $this->service->salvarLote($this->params));
            case 'remover':
                return $this->atomic(fn() => $this->service->remover($this->params['id_processo'] ?? '', $this->params['cod_fornecedor'] ?? ''));
            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

// --- EXECUÇÃO ---
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();

    $controller = new FornecedoresController($pdo, $senior);
    $controller->handleRequest();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}