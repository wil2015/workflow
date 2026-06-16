<?php
namespace App\Modulos\Parametros;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\Database;
use App\Core\BaseController;
use App\Modulos\Parametros\Handlers\CarregarParametrosHandler;
use App\Modulos\Parametros\Handlers\SalvarParametrosHandler;
use Exception;

class ParametrosController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior = null)
    {
        parent::__construct($pdo, $connSenior);

        $service = new ParametrosService($pdo, $connSenior);

        $this->handlers = [
            'carregar' => new CarregarParametrosHandler($service),
            'salvar'   => new SalvarParametrosHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        if (!isset($this->handlers[$acao])) {
            throw new Exception("Acao desconhecida ou nao implementada: " . $acao);
        }

        $payload = $this->params;

        if ($acao === 'salvar') {
            return $this->atomic(fn() => $this->handlers[$acao]->handle($payload));
        }

        return $this->handlers[$acao]->handle($payload);
    }
}

$pdo = Database::getConexao();
$senior = Database::getSenior();
(new ParametrosController($pdo, $senior))->handleRequest();
