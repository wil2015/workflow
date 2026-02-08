<?php
namespace App\Modulos\AutorizacaoCompra;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($pdo, $connSenior);
    }

    // Implementação do método abstrato do pai
    protected function executarAcao(string $acao)
    {
        // 1. Validação de Entrada usando Helpers
        $idProcesso = $this->getParam('instance_id', 0, 'int');
        $idUsuario  = $this->getParam('id_usuario', 1, 'int');

        if ($idProcesso === 0) {
            throw new Exception("ID do processo (instance_id) é obrigatório.");
        }

        // 2. Despacho (Switch)
        switch ($acao) {
            case 'gerar_autorizacoes':
                // Nota Arquitetural:
                // Não usamos $this->atomic() aqui propositalmente.
                // A Procedure já gerencia a integridade dos dados, e rodar fora de
                // transação PHP garante que o SELECT subsequente "enxergue" os dados.
                return $this->service->gerarDocumentosOficiais($idProcesso, $idUsuario);

            case 'enviar_emails':
                // Aqui usamos atomic() pois são várias operações PHP (update status, envio email)
                return $this->atomic(fn() => 
                    $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario)
                );

            case 'listar_documentos':
                return $this->service->listarDocumentosGerados($idProcesso);

            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
        
        // O retorno daqui (array) será automaticamente convertido para JSON pelo BaseController
    }
}

// --- Bootstrap do Módulo ---
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    
    // Inicia o Controller e dispara o Template Method
    (new AutorizacaoCompraController($pdo, $senior))->handleRequest();

} catch (Exception $e) {
    // Fallback apenas para erro crítico de conexão inicial
    http_response_code(500);
    echo json_encode(['erro' => 'Erro crítico de inicialização: ' . $e->getMessage()]);
}