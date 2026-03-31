<?php
namespace App\Modulos\Cotacao;

// Garante o carregamento das classes (PSR-4)
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\BaseController;
use App\Config\Database;
use Exception;
use Throwable;

class CotacaoController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);

        // REFATORAÇÃO: Injeção de Dependência Manual
        // 1. Instanciamos o Repositório com as conexões necessárias
        $repo = new CotacaoRepo($pdo, $connSenior);
        
        // 2. Injetamos o Repositório no Service
        // Isso permite que o Service foque apenas na regra de negócio
        $this->service = new CotacaoService($repo);
    }

    /**
     * Implementação do método abstrato do BaseController.
     * Define quais ações este módulo aceita.
     */
    protected function executarAcao(string $acao)
    {
        switch ($acao) {
            case 'listar_itens':
                // Retorna os itens da solicitação com detalhes do Senior (descrição, unidade)
                return $this->service->listarItensComDetalhes($this->params['instance_id'] ?? 0);

            case 'listar_cotacoes':
                // Retorna a lista de fornecedores e os valores (se já houver) para um item específico
                // Passamos o array $this->params inteiro pois o Service precisa de 'id_processo', 'num', 'seq'
                return $this->service->buscarCotacoesDoItem($this->params);

            case 'salvar_lote':
                // Executa dentro de uma transação de banco de dados (Atomicidade).
                // Se o Service lançar erro (ex: validação do DTO), o 'atomic' faz rollback automático.
                return $this->atomic(function() {
                    return $this->service->salvarLote($this->params);
                });

            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

// --- BOOTSTRAP / ÁREA DE EXECUÇÃO ---
// Este bloco inicializa o controlador quando o arquivo é chamado pela rota/frontend.
try {
    // Obtém conexões através da classe de Configuração
    $pdo = Database::getConexao();
    $senior = Database::getSenior();
    
    // Instancia e executa o controlador
    $controller = new CotacaoController($pdo, $senior);
    $controller->handleRequest();

} catch (Throwable $e) {
    // Tratamento de erro de último nível (caso falhe antes de entrar no BaseController)
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Erro crítico de inicialização: ' . $e->getMessage()]);
    exit;
}