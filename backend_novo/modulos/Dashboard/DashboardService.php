<?php
require_once __DIR__ . '/../../core/BaseService.php';
require_once __DIR__ . '/DashboardRepo.php';

class DashboardService extends BaseService
{
    private $repo;

    public function __construct($pdo) {
        parent::__construct($pdo, null);
        $this->repo = new DashboardRepo($pdo);
    }

    public function carregarHome() {
        $fluxos = $this->repo->buscarFluxosAtivos();
        $instancias = $this->repo->buscarInstanciasRecentes();

        // Cores dos Cards
        $fluxos = array_map(function($f) {
            $f['cor_ui'] = $this->getCorPorId($f['id']);
            return $f;
        }, $fluxos);

        // Lista de Tarefas (DataTables)
        $instancias = array_map(function($i) {
            // Lógica Nova: ID/Ano
            $ano = !empty($i['ano_do_processo']) ? $i['ano_do_processo'] : date('Y');
            $idVisual = $i['id'] . '/' . $ano;

            // Datas novas formatadas
            $dtCot = $i['data_esperada_da_cotacao'];
            $dtRec = $i['data_esperada_do_recebimento'];

            return [
                // --- CAMPOS ORIGINAIS (NÃO MEXER) ---
                'id' => $i['id'], 
                'nome_do_fluxo' => $this->utf8($i['nome_do_fluxo']), // O Vue espera exatamente 'nome_do_fluxo'
                'id_processo_senior' => $i['id_processo_senior'],
                'data_formatada' => date('d/m/Y H:i', strtotime($i['data_inicio'])),
                'status_atual' => $i['status_atual'], // O Vue espera 'status_atual'

                // --- CAMPOS NOVOS (ADICIONAIS) ---
                'id_visual' => $idVisual,
                'prev_cotacao' => $dtCot ? date('d/m/Y', strtotime($dtCot)) : '-',
                'prev_entrega' => $dtRec ? date('d/m/Y', strtotime($dtRec)) : '-'
            ];
        }, $instancias);

        return [
            'fluxos' => $fluxos,
            'tarefas' => $instancias
        ];
    }

    private function getCorPorId($id) {
        $cores = ['#007bff', '#6610f2', '#e83e8c', '#fd7e14', '#28a745', '#20c997'];
        return $cores[$id % count($cores)];
    }
}