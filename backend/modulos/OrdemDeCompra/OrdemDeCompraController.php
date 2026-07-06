<?php
namespace App\Modulos\OrdemDeCompra;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\Database;
use App\Core\BaseController;
use App\Modulos\OrdemDeCompra\Integracao\OrdemCompraSeniorClient;
use Exception;
use Throwable;

class OrdemDeCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);

        $repo = new OrdemDeCompraRepo($pdo, $connSenior);
        $integracao = new OrdemCompraSeniorClient();
        $this->service = new OrdemDeCompraService($repo, $integracao);
    }

    protected function executarAcao(string $acao)
    {
        $idProcesso = $this->getParam('instance_id', 0, 'int');
        if ($idProcesso === 0) {
            throw new Exception('ID do processo obrigatorio.');
        }

        switch ($acao) {
            case 'listar_autorizacoes':
                return $this->service->listarAutorizacoes($idProcesso);

            case 'gerar_ordem_compra':
                $idAutorizacao = $this->getParam('id_autorizacao', '', 'string');
                $idFornecedorSenior = $this->getParam('id_fornecedor_senior', 0, 'int');

                return $this->service->gerarOrdemCompra($idProcesso, $idAutorizacao, $idFornecedorSenior);

            default:
                throw new Exception("Acao desconhecida: '{$acao}'");
        }
    }
}

try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    (new OrdemDeCompraController($pdo, $senior))->handleRequest();
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
    exit;
}
