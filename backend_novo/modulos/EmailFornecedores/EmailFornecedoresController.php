<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/EmailFornecedoresService.php';

class EmailFornecedoresController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        if (!isset($connSenior)) throw new Exception("Conexão Senior necessária.");
        parent::__construct($pdo, $connSenior);
        $this->service = new EmailFornecedoresService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar':
                return $this->service->carregarEmails($this->params['instance_id'] ?? 0);

            case 'salvar':
                return $this->atomic(function() {
                    return $this->service->salvarEmailsSelecionados(
                        $this->params['instance_id'] ?? 0,
                        $this->params['emails_selecionados'] ?? []
                    );
                });

            case 'add_email':
                // Não precisa de atomic pois é inserção simples, mas pode usar se quiser
                return $this->service->adicionarEmailManual(
                    $this->params['id_fornecedor_senior'] ?? 0,
                    $this->params['email'] ?? ''
                );

            default:
                throw new Exception("Ação desconhecida: $acao");
        }
    }
}

$controller = new EmailFornecedoresController($pdo, $connSenior);
$controller->handleRequest();