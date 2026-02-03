<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/AutorizacaoCompraService.php';

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        ob_start();

        try {
            $idProcesso = $this->params['instance_id'] ?? 0;
            $resultado = null;

            switch ($acao) {
                case 'gerar_autorizacoes':
                    // A Service agora encapsula a chamada da Procedure + Geração de PDF
                    $resultado = $this->atomic(function() use ($idProcesso) {
                        return $this->service->gerarDocumentosOficiais(
                            $idProcesso, 
                            $this->params['id_usuario'] ?? 1
                        );
                    });
                    break;

                case 'enviar_email_concluir':
                    $resultado = $this->atomic(function() use ($idProcesso) {
                        return $this->service->enviarEmailsEConcluir(
                            $idProcesso, 
                            $this->params['id_usuario'] ?? 1
                        );
                    });
                    break;

                case 'listar_documentos':
                    // Inclui a verificação física que corrigimos anteriormente
                    $resultado = $this->service->listarDocumentosGerados($idProcesso);
                    break;

                default:
                    throw new Exception("Ação desconhecida: '$acao'");
            }

            ob_end_clean(); 
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit;

        } catch (\Throwable $e) {
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
// Inicialização
$controller = new AutorizacaoCompraController($pdo, $connSenior);
$controller->handleRequest();