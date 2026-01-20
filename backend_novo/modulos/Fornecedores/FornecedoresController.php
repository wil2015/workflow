<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/FornecedoresService.php';

class FornecedoresController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new FornecedoresService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar':
                return $this->service->listarParaDatatable($this->params);

            case 'salvar_lote':
                return $this->atomic(function() {
                    return $this->service->salvarLote($this->params);
                });

            case 'remover':
                return $this->atomic(function() {
                    return $this->service->remover(
                        $this->params['id_processo'] ?? '', 
                        $this->params['cod_fornecedor'] ?? ''
                    );
                });

            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

// Inicialização
$controller = new FornecedoresController($pdo, $connSenior);
$controller->handleRequest();