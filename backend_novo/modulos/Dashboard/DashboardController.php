<?php
namespace App\Modulos\Dashboard;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Throwable;
use Exception;

class DashboardController extends BaseController
{
    public function __construct($pdo)
    {
        parent::__construct($pdo, null);
        $repo = new DashboardRepo($pdo);
        $this->service = new DashboardService($repo);
    }

    protected function getAcaoPadrao() { return 'home'; }

    protected function executarAcao(string $acao)
    {
        if ($acao === 'home') return $this->service->carregarHome();
        throw new Exception("Ação inválida: $acao");
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $controller = new DashboardController($pdo);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}