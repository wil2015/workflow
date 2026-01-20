<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/FluxoService.php';

class FluxoController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new FluxoService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'ler_tarefa':
                $id = $this->params['id_instancia'] ?? null;
                if (!$id) throw new Exception("ID não informado");
                return $this->service->carregarPassoAtual($id);

            case 'listar_solicitacoes':
                return $this->service->listarSolicitacoesSenior($this->params);

            case 'vincular':
                return $this->atomic(function() {
                    return $this->service->vincularItens($this->params);
                });

            case 'remover_item':
                return $this->atomic(function() {
                    return $this->service->removerItem(
                        $this->params['id_processo'], 
                        $this->params['num_solicitacao'], 
                        $this->params['seq_solicitacao']
                    );
                });

            case 'cancelar_processo':
                return $this->atomic(function() {
                    $id = $this->params['id_processo'] ?? null;
                    if (!$id) throw new Exception("ID processo ausente");
                    return $this->service->cancelarProcesso($id);
                });

            default:
                throw new Exception("Ação desconhecida: " . $acao);
        }
    }
}

$controller = new FluxoController($pdo, $connSenior);
$controller->handleRequest();