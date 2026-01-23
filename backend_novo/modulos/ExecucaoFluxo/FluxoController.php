<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/FluxoService.php';

class FluxoController extends BaseController
{
    /** @var FluxoService */
    protected $service;

    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        
        // Simples: Instancia direto, sem Factory
        $this->service = new FluxoService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'ler_tarefa':
                $id = $this->params['id_instancia'] ?? null;
                if (!$id) throw new Exception("ID não informado");
                return $this->service->carregarPassoAtual($id);

            case 'salvar_datas':
                return $this->atomic(function() {
                    return $this->service->salvarDatasPrevisao($this->params);
                });

            case 'vincular':
                return $this->atomic(function() {
                    return $this->service->vincularItens($this->params);
                });
            
            // Aceita os dois nomes para evitar erro no front
            case 'listar_solicitacoes':
            case 'listar_itens_senior':
                return $this->service->listarSolicitacoesSenior($_REQUEST);

            case 'remover_item':
                return $this->atomic(function() {
                    $id = $this->params['id'] ?? 0;
                    $num = $this->params['num'] ?? 0;
                    $seq = $this->params['seq'] ?? 0;
                    return $this->service->removerItem($id, $num, $seq);
                });

            case 'cancelar_processo':
                return $this->atomic(function() {
                    $id = $this->params['id'] ?? 0;
                    return $this->service->cancelarProcesso($id);
                });

            case 'dashboard_data':
                return $this->service->carregarDadosDashboard();

            default:
                throw new Exception("Ação desconhecida: " . $acao);
        }
    }
}

// Inicialização
try {
    $controller = new FluxoController($pdo, $connSenior);
    $controller->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}