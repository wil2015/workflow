<?php
namespace App\Modulos\EmailFornecedores;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Throwable;
use Exception;

class EmailFornecedoresController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $repo = new EmailFornecedoresRepo($pdo, $connSenior);
        $this->service = new EmailFornecedoresService($repo);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar':
                return $this->service->carregarEmails($this->params['instance_id'] ?? 0);
            case 'salvar':
                return $this->atomic(fn() => $this->service->salvarEmailsSelecionados($this->params['instance_id'] ?? 0, $this->params['emails_selecionados'] ?? []));
            case 'add_email':
                return $this->service->adicionarEmailManual($this->params['id_fornecedor_senior'] ?? 0, $this->params['email'] ?? '');
            default:
                throw new Exception("Ação desconhecida: $acao");
        }
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    $controller = new EmailFornecedoresController($pdo, $senior);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}