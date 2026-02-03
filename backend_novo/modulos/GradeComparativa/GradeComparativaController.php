<?php
require_once __DIR__ . '/../../core/BaseController.php';
require_once __DIR__ . '/GradeComparativaService.php';

class GradeComparativaController extends BaseController
{
    public function __construct($pdo, $connSenior)
    {
        parent::__construct($pdo, $connSenior);
        $this->service = new GradeComparativaService($pdo, $connSenior);
    }

    protected function executarAcao(string $acao)
    {
        try {
            switch ($acao) {
                case 'carregar_grade':
                    // Apenas leitura/visualização, usa lógica PHP (logicaDeMontagem)
                    return $this->service->montarGradeParaFront($this->params['instance_id'] ?? 0);

                case 'consolidar_vencedores':
                    // Escrita e Processamento: Usa a Procedure MySQL
                    $id = $this->params['id_processo'] ?? 0;
                    $ofertas = $this->params['ofertas'] ?? [];
                    if (!is_array($ofertas)) $ofertas = [];
                    
                    return $this->service->consolidarProcesso($id, $ofertas);

                default:
                    throw new Exception("Ação desconhecida: '$acao'");
            }
        } catch (Throwable $e) {
            return [
                'erro' => 'ERRO: ' . $e->getMessage(),
                'arquivo' => $e->getFile(), // Opcional: remover em produção
                'linha' => $e->getLine()    // Opcional: remover em produção
            ];
        }
    }
}

$controller = new GradeComparativaController($pdo, $connSenior);
$controller->handleRequest();