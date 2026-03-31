<?php
namespace App\Modulos\Dashboard;

use App\Core\BaseService;
use App\Core\Utils\Formatador;

class DashboardService extends BaseService
{
    private $repo;

    public function __construct(DashboardRepo $repo) {
        $this->repo = $repo;
    }

    public function carregarHome() {
        $fluxos = $this->repo->buscarFluxosAtivos();
        $instancias = $this->repo->buscarInstanciasRecentes();

        $fluxos = array_map(function($f) {
            $f['cor_ui'] = $this->getCorPorId($f['id']);
            return $f;
        }, $fluxos);

        $instancias = array_map(function($i) {
            $ano = !empty($i['ano_do_processo']) ? $i['ano_do_processo'] : date('Y');
            return [
                'id' => $i['id'], 
                'nome_do_fluxo' => Formatador::utf8($i['nome_do_fluxo']),
                'id_processo_senior' => $i['id_processo_senior'],
                'data_formatada' => Formatador::dataHora($i['data_inicio']),
                'status_atual' => $i['status_atual'],
                'id_visual' => $i['id'] . '/' . $ano,
                'prev_cotacao' => Formatador::data($i['data_esperada_da_cotacao']),
                'prev_entrega' => Formatador::data($i['data_esperada_do_recebimento'])
            ];
        }, $instancias);

        return ['fluxos' => $fluxos, 'tarefas' => $instancias];
    }

    private function getCorPorId($id) {
        $cores = ['#007bff', '#6610f2', '#e83e8c', '#fd7e14', '#28a745', '#20c997'];
        return $cores[$id % count($cores)];
    }
}