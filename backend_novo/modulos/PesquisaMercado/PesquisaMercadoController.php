<?php
namespace App\Modulos\PesquisaMercado;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class PesquisaMercadoController extends BaseController
{
    public function __construct($pdo)
    {
        parent::__construct($pdo);
        $this->service = new PesquisaMercadoService($pdo);
    }

    protected function executarAcao(string $acao)
    {
        $idProcesso = $this->getParam('instance_id', 0, 'int');
        if ($idProcesso === 0) throw new Exception("Processo inválido.");

        switch ($acao) {
            case 'listar_documentos': // <-- ADICIONADO AQUI
                return $this->service->listarDocumentosGerados($idProcesso);
                
            case 'gerar_pdf':
                return $this->service->gerarRelatorioPesquisa($idProcesso, $this->getParam('id_usuario', 1, 'int'));
            default:
                throw new Exception("Ação não permitida.");
        }
    }
    
}

// Inicialização
$pdo = Database::getConexao();
(new PesquisaMercadoController($pdo))->handleRequest();