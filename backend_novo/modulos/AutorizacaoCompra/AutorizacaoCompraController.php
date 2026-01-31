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
        // 1. INICIA O BUFFER (Segura qualquer output do PHP)
        ob_start();

        try {
            $idProcesso = $this->params['instance_id'] ?? 0;
            $resultado = null;

            switch ($acao) {
                case 'gerar_autorizacoes':
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
                    $resultado = $this->service->listarDocumentosGerados($idProcesso);
                    break;

                default:
                    throw new Exception("Ação desconhecida: '$acao'");
            }

            // 2. LIMPA A SUJEIRA E ENTREGA O JSON PURO
            ob_end_clean(); // Joga fora Warnings e HTML quebrados
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit; // Mata o script aqui para garantir

        } catch (\Throwable $e) {
            // 3. SE DER ERRO, LIMPA TAMBÉM E ENTREGA JSON DE ERRO
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