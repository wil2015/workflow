<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/Factory/FluxoFactory.php';
require_once __DIR__ . '/DTO/SalvarDatasDTO.php';

class FluxoController extends BaseController
{
    /** @var FluxoService */
    protected $service;

    // Construtor limpo, delega a criação para a Factory
    public function __construct($pdo, $connSenior)
    {
        // Passa para o pai apenas para manter compatibilidade de sistema legado
        parent::__construct($pdo, $connSenior);
        
        // FACTORY PATTERN: Criação do serviço centralizada
        $this->service = FluxoFactory::criarService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            // ... [Outros cases] ...

            case 'salvar_datas':
                return $this->atomic(function() {
                    // DTO PATTERN: Transforma o array solto em Objeto Tipado
                    $dto = new SalvarDatasDTO($this->params);
                    
                    return $this->service->salvarDatasPrevisao($dto);
                });

            // ... [Outros cases] ...
            
            default:
                throw new Exception("Ação desconhecida: " . $acao);
        }
    }
}

// Inicialização (Mantida compatível com seu index.php)
try {
    $controller = new FluxoController($pdo, $connSenior);
    $controller->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}