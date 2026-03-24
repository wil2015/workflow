<?php
namespace App\Modulos\ExecucaoFluxo;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\ExecucaoFluxo\Handlers\LerTarefaHandler;
use App\Modulos\ExecucaoFluxo\Handlers\SalvarDatasHandler;
use App\Modulos\ExecucaoFluxo\Handlers\VincularItensHandler;
use App\Modulos\ExecucaoFluxo\Handlers\ListarSolicitacoesHandler;
use App\Modulos\ExecucaoFluxo\Handlers\RemoverItemHandler;
use App\Modulos\ExecucaoFluxo\Handlers\CancelarProcessoHandler;
use App\Modulos\ExecucaoFluxo\Handlers\DashboardDataHandler;
use Exception;

class FluxoController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        
        $repo = new FluxoRepo($pdo, $connSenior);
        $service = new FluxoService($repo);

        // Mapeamento das Ações para os Handlers
        $this->handlers = [
            'ler_tarefa'          => new LerTarefaHandler($service),
            'salvar_datas'        => new SalvarDatasHandler($service),
            'vincular'            => new VincularItensHandler($service),
            'listar_solicitacoes' => new ListarSolicitacoesHandler($service),
            'remover_item'        => new RemoverItemHandler($service),
            'cancelar_processo'   => new CancelarProcessoHandler($service),
            'dashboard_data'      => new DashboardDataHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Ação desconhecida ou não implementada: " . $acao);
        }

        // Monta o DTO dinâmico com tudo o que as ações podem precisar
        $payload = $this->params; 
        $payload['request'] = $_REQUEST; // Necessário para o DataTables

        // Lista de ações que fazem INSERT/UPDATE/DELETE e precisam de transação (Rollback em caso de erro)
        $acoesAtomicas = ['salvar_datas', 'vincular', 'remover_item', 'cancelar_processo'];

        // Se a ação for de gravação, roda dentro do atomic()
        if (in_array($acao, $acoesAtomicas)) {
            return $this->atomic(fn() => $this->handlers[$acao]->handle($payload));
        }

        // Se for apenas leitura (SELECT), roda direto
        return $this->handlers[$acao]->handle($payload);
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    (new FluxoController($pdo, $senior))->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}