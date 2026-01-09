<?php
class DashboardRepo {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function buscarFluxosAtivos() {
        // MANTENDO A COMPATIBILIDADE COM O VUE LEGADO
        // O Vue pede: fluxo.fluxo_id, fluxo.nome_do_fluxo, fluxo.arquivo_xml
        $sql = "SELECT 
                    id_fluxo_definicao AS id,        -- Para o :key do Vue
                    id_fluxo_definicao AS fluxo_id,  -- Para o link (fluxo_id=...)
                    nome_do_fluxo, 
                    arquivo_xml 
                FROM nome_do_fluxo 
                WHERE ativo = 1 
                  AND id_fluxo_definicao IS NOT NULL
                ORDER BY nome_do_fluxo ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function buscarInstanciasRecentes() {
        // MANTENDO A COMPATIBILIDADE COM O VUE LEGADO
        // O Vue pede: estatus_atual (com 'e'), id_processo_senior
        $sql = "SELECT 
                    p.id, 
                    p.data_inicio, 
                    p.id_processo_senior, 
                    d.nome_do_fluxo, 
                    p.estatus_atual -- Mantemos com 'e' pois é assim que o Vue espera
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                ORDER BY p.id DESC"; 
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
}