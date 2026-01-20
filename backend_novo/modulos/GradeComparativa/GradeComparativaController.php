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
        switch ($acao) {
            case 'carregar_grade':
                return $this->service->montarGradeParaFront($this->params['instance_id'] ?? 0);

            case 'consolidar_vencedores':
                return $this->atomic(function() {
                    $id = $this->params['id_processo'] ?? 0;
                    $ofertas = $this->params['ofertas'] ?? [];
                    if (!is_array($ofertas)) $ofertas = [];
                    
                    return $this->service->consolidarProcesso($id, $ofertas);
                });

            default:
                throw new Exception("Ação desconhecida: '$acao'");
        }
    }
}

$controller = new GradeComparativaController($pdo, $connSenior);
$controller->handleRequest();