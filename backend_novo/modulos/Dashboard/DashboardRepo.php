<?php
class DashboardRepo {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function buscarFluxosAtivos() {
        $sql = "SELECT id, nome_do_fluxo, arquivo_xml 
                FROM licitacao_fluxos 
                WHERE ativo = 1 
                ORDER BY nome_do_fluxo ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarInstanciasRecentes() {
        // ADICIONADO: id_processo_senior na query
        $sql = "SELECT 
                    i.id, 
                    i.data_inicio, 
                    i.id_processo_senior, 
                    f.nome_do_fluxo, 
                    i.status_atual
                FROM licitacao_instancias i
                JOIN licitacao_fluxos f ON i.fluxo_id = f.id
                WHERE i.status_atual != 'Finalizado'
                ORDER BY i.data_inicio DESC"; 
        
        return $this->pdo->query($sql)->fetchAll();
    }
}