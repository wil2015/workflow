<?php
namespace App\Modulos\ExecucaoFluxo;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\ExecucaoFluxo\Handlers\LerTarefaHandler;
use App\Modulos\ExecucaoFluxo\Handlers\VincularItensHandler;
use App\Modulos\ExecucaoFluxo\Handlers\ListarOrdensCompraHandler;
use App\Modulos\ExecucaoFluxo\Handlers\RemoverItemHandler;
use App\Modulos\ExecucaoFluxo\Handlers\CancelarProcessoHandler;
use App\Modulos\ExecucaoFluxo\Handlers\DashboardDataHandler;
use Exception;

class FluxoController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexao Senior necessaria.");
        parent::__construct($pdo, $connSenior);

        $repo = new FluxoRepo($pdo, $connSenior);
        $service = new FluxoService($repo);

        $this->handlers = [
            'ler_tarefa'           => new LerTarefaHandler($service),
            'vincular'             => new VincularItensHandler($service),
            'listar_ordens_compra' => new ListarOrdensCompraHandler($service),
            'remover_item'         => new RemoverItemHandler($service),
            'cancelar_processo'    => new CancelarProcessoHandler($service),
            'dashboard_data'       => new DashboardDataHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Acao desconhecida ou nao implementada: " . $acao);
        }

        $payload = $this->params;
        $payload['request'] = $_REQUEST;

        $acoesAtomicas = ['vincular', 'remover_item', 'cancelar_processo'];

        if (in_array($acao, $acoesAtomicas)) {
            return $this->atomic(fn() => $this->handlers[$acao]->handle($payload));
        }

        return $this->handlers[$acao]->handle($payload);
    }
}

try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    (new FluxoController($pdo, $senior))->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
