<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/DashboardService.php';

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

// Repare que só passamos o $pdo aqui
$controller = new DashboardController($pdo);
$controller->handleRequest();