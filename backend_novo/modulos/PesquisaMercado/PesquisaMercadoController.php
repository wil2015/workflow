<?php
namespace App\Modulos\PesquisaMercado;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use App\Modulos\PesquisaMercado\Handlers\ListarDocumentosHandler;
use App\Modulos\PesquisaMercado\Handlers\GerarPdfHandler;
use Exception;

class PesquisaMercadoController extends BaseController
{
    private array $handlers = [];

    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);

        $service = new PesquisaMercadoService($pdo, $connSenior);

        // Mapeamento limpo das rotas para as classes de ação
        $this->handlers = [
            'listar_documentos' => new ListarDocumentosHandler($service),
            'gerar_pdf'         => new GerarPdfHandler($service),
        ];
    }

    protected function executarAcao(string $acao)
    {
        $idProcesso = $this->getParam('instance_id', 0, 'int');
        
        if ($idProcesso === 0) {
            throw new Exception("Processo inválido.");
        }

        if (!isset($this->handlers[$acao])) {
            throw new Exception("Ação não permitida ou não implementada.");
        }

        // DTO dinâmico: empacota tudo que a ação pode precisar
        $payload = [
            'id_processo' => $idProcesso,
            'id_usuario'  => $this->getParam('id_usuario', 1, 'int'),
        ];

        // Dispara a classe correta sem usar IFs ou Switches
        return $this->handlers[$acao]->handle($payload);
    }
}

// Bootstrap
$pdo = Database::getConexao();
$senior = Database::getSenior();
(new PesquisaMercadoController($pdo, $senior))->handleRequest();