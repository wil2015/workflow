<?php

declare(strict_types=1);

namespace Dashboard\Repository;

use Shared\Repository\BaseRepository;

class DashboardRepo extends BaseRepository
{
    public function buscarFluxosAtivos(): array
    {
        $sql = "SELECT id_fluxo_definicao AS id, id_fluxo_definicao AS fluxo_id, nome_do_fluxo, arquivo_xml
                FROM nome_do_fluxo WHERE ativo = 1 AND id_fluxo_definicao IS NOT NULL ORDER BY nome_do_fluxo ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarInstanciasRecentes(): array
    {
        $sql = "SELECT p.id, p.ano_do_processo, p.data_esperada_da_cotacao, p.data_esperada_do_recebimento,
                       p.data_inicio, p.id_processo_senior, d.nome_do_fluxo, p.status_atual
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                ORDER BY p.id DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
}
