<?php
namespace App\Modulos\Dashboard;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\Dashboard\Handlers\HomeHandler;
use Throwable;
use Exception;

class DashboardController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo)
    {
        // O Dashboard local não acessa o Senior diretamente, então passamos null
        parent::__construct($pdo, null); 
        
        $repo = new DashboardRepo($pdo);
        $service = new DashboardService($repo);

        // Mapeamento limpo da rota para a classe de ação
        $this->handlers = [
            'home' => new HomeHandler($service),
        ];
    }

    // Mantém a excelente sacada de ter uma ação padrão caso o Vue não envie o '?acao='
    protected function getAcaoPadrao() { 
        return 'home'; 
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Ação desconhecida ou não implementada no Dashboard: " . $acao);
        }

        // DTO dinâmico: empacota tudo que a ação possa precisar no futuro
        $payload = $this->params;

        // Dispara a classe correta
        return $this->handlers[$acao]->handle($payload);
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