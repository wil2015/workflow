<?php
namespace App\Modulos\ExecucaoFluxo;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Throwable;
use Exception;

class FluxoController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $repo = new FluxoRepo($pdo, $connSenior);
        $this->service = new FluxoService($repo);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'ler_tarefa':
                return $this->service->carregarPassoAtual($this->params['id_instancia'] ?? null);
            case 'salvar_datas':
                return $this->atomic(fn() => $this->service->salvarDatasPrevisao($this->params));
            case 'vincular':
                return $this->atomic(fn() => $this->service->vincularItens($this->params));
            case 'listar_solicitacoes':
                return $this->service->listarSolicitacoesSenior($_REQUEST);
            case 'remover_item':
                return $this->atomic(fn() => $this->service->removerItem($this->params['id'] ?? 0, $this->params['num'] ?? 0, $this->params['seq'] ?? 0));
            case 'cancelar_processo':
                return $this->atomic(fn() => $this->service->cancelarProcesso($this->params['id'] ?? 0));
            case 'dashboard_data':
                return $this->service->carregarDadosDashboard();
            default:
                throw new Exception("Ação desconhecida: " . $acao);
        }
    }
}

// Bootstrap
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    $controller = new FluxoController($pdo, $senior);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}