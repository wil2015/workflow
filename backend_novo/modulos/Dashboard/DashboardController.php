<?php
namespace App\Modulos\Dashboard;

// 1. O Autoload sempre vem depois do namespace
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class DashboardController extends BaseController
{
    public function __construct($pdo)
    {
        // Passa NULL para o connSenior, pois o Dashboard não usa
        parent::__construct($pdo, null);
        $this->service = new DashboardService($pdo);
    }

    // Sobrescreve método opcional para definir ação padrão caso venha vazia
    protected function getAcaoPadrao() {
        return 'home';
    }

    protected function executarAcao(string $acao)
    {
        if ($acao === 'home') {
            return $this->service->carregarHome();
        }
        
        throw new Exception("Ação inválida: $acao");
    }
}

// --- ÁREA DE EXECUÇÃO ---
try {
    // CORREÇÃO AQUI: Criamos a conexão explicitamente
    $pdo = Database::getConexao();
    
    // Agora passamos o $pdo válido (e não null)
    $controller = new DashboardController($pdo);
    $controller->handleRequest();

} catch (Exception $e) {
    // Tratamento de erro fatal para devolver JSON
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Erro fatal no Dashboard: ' . $e->getMessage()]);
    exit;
}