<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/AutorizacaoCompraService.php';

class AutorizacaoCompraController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new AutorizacaoCompraService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            // Caso de Uso: O usuário clica em "Gerar Autorização" na tarefa do BPMN
            case 'gerar_autorizacoes':
                return $this->atomic(function() {
                    return $this->service->gerarDocumentosOficiais(
                        $this->params['instance_id'] ?? 0,
                        $this->params['id_usuario'] ?? 0
                    );
                });

            // Caso de Uso: O visualizador busca a lista de documentos já gerados para exibir na tela
            case 'listar_documentos':
                return $this->service->listarDocumentosGerados($this->params['instance_id'] ?? 0);

            default:
                throw new Exception("Ação desconhecida: $acao");
        }
    }
}

$controller = new AutorizacaoCompraController($pdo, $connSenior);
$controller->handleRequest();