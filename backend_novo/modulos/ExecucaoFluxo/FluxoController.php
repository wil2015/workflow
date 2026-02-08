<?php
namespace App\Modulos\ExecucaoFluxo;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class FluxoController extends BaseController
{
    protected $service;

    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new FluxoService($pdo, $connSenior);
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
                return $this->atomic(function() {
                    $id = $this->params['id'] ?? 0;
                    $num = $this->params['num'] ?? 0;
                    $seq = $this->params['seq'] ?? 0;
                    return $this->service->removerItem($id, $num, $seq);
                });
            case 'cancelar_processo':
                return $this->atomic(function() {
                    return $this->service->cancelarProcesso($this->params['id'] ?? 0);
                });
            case 'dashboard_data':
                return $this->service->carregarDadosDashboard();

            default:
                throw new Exception("Ação desconhecida: " . $acao);
        }
    }
}

// --- EXECUÇÃO ---
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();

    $controller = new FluxoController($pdo, $senior);
    $controller->handleRequest();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}