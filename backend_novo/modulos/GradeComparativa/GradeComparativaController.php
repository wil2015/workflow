<?php
namespace App\Modulos\GradeComparativa;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Throwable;
use Exception;

class GradeComparativaController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $repo = new GradeComparativaRepo($pdo, $connSenior);
        $this->service = new GradeComparativaService($repo);
    }

    protected function executarAcao(string $acao)
    {
        try {
            switch ($acao) {
                case 'carregar_grade':
                    return $this->service->montarGradeParaFront($this->params['instance_id'] ?? 0);
                case 'consolidar_vencedores':
                    return $this->service->consolidarProcesso($this->params['id_processo'] ?? 0, $this->params['ofertas'] ?? []);
                default:
                    throw new Exception("Ação desconhecida: '$acao'");
            }
        } catch (Throwable $e) {
            return ['erro' => 'ERRO: ' . $e->getMessage()];
        }
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    $controller = new GradeComparativaController($pdo, $senior);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}