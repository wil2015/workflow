<?php
namespace App\Modulos\AutorizacaoCompra;

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;
use Throwable;

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        // Limpa qualquer saída anterior para garantir que o JSON saia limpo
        if (ob_get_length()) ob_clean(); 
        ob_start();

        try {
            $idProcesso = (int)($this->params['instance_id'] ?? 0);
            $idUsuario  = (int)($this->params['id_usuario'] ?? 1);
            
            // TRAVA DE SEGURANÇA: Se não vier ID, para aqui.
            if ($idProcesso === 0) {
                throw new Exception("ID do processo (instance_id) inválido ou não recebido.");
            }

            $resultado = null;

            switch ($acao) {
                case 'gerar_autorizacoes':
                    // --- ALTERAÇÃO CRÍTICA AQUI ---
                    // Removemos o $this->atomic(). 
                    // Motivo: A Procedure gerencia sua própria consistência. 
                    // Rodar fora da transação do PHP garante que o SELECT seguinte enxergue os dados.
                    $resultado = $this->service->gerarDocumentosOficiais($idProcesso, $idUsuario);
                    break;

                case 'enviar_emails':
                    // Aqui mantemos o atomic pois envolve apenas UPDATEs simples e controle de estado
                    $resultado = $this->atomic(function() use ($idProcesso, $idUsuario) {
                        return $this->service->enviarEmailsEConcluir($idProcesso, $idUsuario);
                    });
                    break;

                case 'listar_documentos':
                    $resultado = $this->service->listarDocumentosGerados($idProcesso);
                    break;

                default:
                    throw new Exception("Ação desconhecida: '$acao'");
            }

            $output = ob_get_clean(); 
            if ($output) error_log("Output inesperado no buffer: $output");
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit;

        } catch (Throwable $e) {
            ob_end_clean(); 
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'sucesso' => false, 
                'erro' => $e->getMessage(),
                'local' => basename($e->getFile()) . ':' . $e->getLine()
            ]);
            exit;
        }
    }
}

// --- INICIALIZAÇÃO ---
try {
    $pdo = Database::getConexao();
    $senior = Database::getSenior();

    $controller = new AutorizacaoCompraController($pdo, $senior);
    $controller->handleRequest();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro fatal na inicialização: ' . $e->getMessage()]);
    exit;
}