<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/DashboardRepo.php';

class DashboardService extends BaseService
{
    private $repo;

    public function __construct($pdo)
    {
        // Passa null pois Dashboard não usa Senior
        parent::__construct($pdo, null);
        $this->repo = new DashboardRepo($pdo);
    }

    public function carregarHome()
    {
        $fluxos = $this->repo->buscarFluxosAtivos();
        $instancias = $this->repo->buscarInstanciasRecentes();

        $fluxos = array_map(function($f) {
            $f['cor_ui'] = $this->getCorPorId($f['id']);
            return $f;
        }, $fluxos);

        $instancias = array_map(function($i) {
            $i['data_formatada'] = date('d/m/Y H:i', strtotime($i['data_inicio']));
            return $i;
        }, $instancias);

        return [
            'fluxos' => $fluxos,
            'tarefas' => $instancias
        ];
    }

    private function getCorPorId($id)
    {
        $cores = ['#007bff', '#6610f2', '#e83e8c', '#fd7e14', '#28a745', '#20c997'];
        return $cores[$id % count($cores)];
    }
}