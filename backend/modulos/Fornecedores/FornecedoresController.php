<?php
namespace App\Modulos\Fornecedores;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\Fornecedores\Handlers\ListarHandler;
use App\Modulos\Fornecedores\Handlers\SalvarLoteHandler;
use App\Modulos\Fornecedores\Handlers\RemoverHandler;
use Throwable;
use Exception;

class FornecedoresController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        
        $repo = new FornecedoresRepo($pdo, $connSenior);
        $service = new FornecedoresService($repo);

        // Mapeamento limpo das ações
        $this->handlers = [
            'listar'      => new ListarHandler($service),
            'salvar_lote' => new SalvarLoteHandler($service),
            'remover'     => new RemoverHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Ação desconhecida ou não implementada: '$acao'");
        }

        // DTO dinâmico com os parâmetros da requisição
        $payload = $this->params;

        // Lista de ações que fazem INSERT/UPDATE/DELETE e precisam de rollback em caso de erro
        $acoesAtomicas = ['salvar_lote', 'remover'];

        // Se for ação de gravação, roda dentro da transação segura
        if (in_array($acao, $acoesAtomicas)) {
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
    $controller = new FornecedoresController($pdo, $senior);
    $controller->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}