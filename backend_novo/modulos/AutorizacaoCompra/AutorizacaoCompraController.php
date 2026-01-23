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
            // Gera os PDFs, Salva no Disco e Retorna os Links
            case 'gerar_autorizacoes':
                return $this->atomic(function() {
                    return $this->service->gerarDocumentosOficiais(
                        $this->params['instance_id'] ?? 0,
                        $this->params['id_usuario'] ?? 1 // ID do usuário logado
                    );
                });

            // Lista os documentos já gerados para este processo
            case 'listar_documentos':
                return $this->service->listarDocumentosGerados($this->params['instance_id'] ?? 0);

            default:
                throw new Exception("Ação desconhecida: $acao");
        }
    }
}

// Inicialização padrão
$controller = new AutorizacaoCompraController($pdo, $connSenior);
$controller->handleRequest();