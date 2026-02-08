<?php
namespace App\Modulos\Cotacao;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class CotacaoController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new CotacaoService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar_itens':
                return $this->service->listarItensComDetalhes($this->params['instance_id'] ?? 0);
            case 'listar_cotacoes':
                return $this->service->buscarCotacoesDoItem($this->params);
            case 'salvar_lote':
                return $this->atomic(function() {
                    return $this->service->salvarLote($this->params);
                });
            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

// --- EXECUÇÃO ---
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    
    $controller = new CotacaoController($pdo, $senior);
    $controller->handleRequest();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $e->getMessage()]);
    exit;
}