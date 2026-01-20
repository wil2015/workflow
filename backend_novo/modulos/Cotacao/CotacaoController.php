<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/CotacaoService.php';

class CotacaoController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new CotacaoService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar_itens':
                return $this->service->listarItensComDetalhes($this->params['instance_id'] ?? 0);

            case 'listar_cotacoes':
                return $this->service->buscarCotacoesDoItem($this->params);

            case 'salvar_lote':
                return $this->atomic(function() {
                    return $this->service->salvarLote($this->params);
                });

            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

$controller = new CotacaoController($pdo, $connSenior);
$controller->handleRequest();