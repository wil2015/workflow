<?php
namespace App\Modulos\GradeComparativa;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\GradeComparativa\Handlers\CarregarGradeHandler;
use App\Modulos\GradeComparativa\Handlers\ConsolidarVencedoresHandler;
use Throwable;
use Exception;

class GradeComparativaController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        
        $repo = new GradeComparativaRepo($pdo, $connSenior);
        $service = new GradeComparativaService($repo);

        // Mapeamento limpo das ações
        $this->handlers = [
            'carregar_grade'        => new CarregarGradeHandler($service),
            'consolidar_vencedores' => new ConsolidarVencedoresHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Ação desconhecida ou não implementada: '$acao'");
        }

        // DTO dinâmico com os parâmetros da requisição
        $payload = $this->params;

        // Se for ação de gravação, roda dentro da transação segura (Rollback automático em caso de erro)
        if ($acao === 'consolidar_vencedores') {
            return $this->atomic(fn() => $this->handlers[$acao]->handle($payload));
        }

        // Se for apenas leitura, roda direto
        return $this->handlers[$acao]->handle($payload);
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