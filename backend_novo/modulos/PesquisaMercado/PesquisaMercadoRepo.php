<?php
namespace App\Modulos\PesquisaMercado;

use App\Core\BaseRepository;
use PDO;

class PesquisaMercadoRepo extends BaseRepository
{
    public function buscarDadosComparativos(int $idProcesso)
    {
        // Usa a grade_de_custos como base principal
        $sql = "SELECT 
                    g.id_item,
                    i.descricao_item_snapshot as descricao,
                    p.nome_do_fornecedor,
                    p.cnpj_cpf,
                    g.valor_cotado as valor_unitario,
                    g.valor_total as valor_total_item,
                    g.vencedor
                FROM grade_de_custos g
                
                -- Busca a descrição do item
                INNER JOIN processos_itens i 
                    ON g.id_item = i.id 
                
                -- Busca os dados do fornecedor
                INNER JOIN licitacao_participantes p 
                    ON g.id_fornecedor_senior = p.id_fornecedor_senior 
                   AND g.id_instancia_processo = p.id_processo_instancia
                   
                WHERE g.id_instancia_processo = ?
                ORDER BY g.id_item ASC, g.valor_cotado ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listarDocumentosPorProcesso(int $idProcesso)
    {
        $sql = "SELECT * FROM documentos_oficiais 
                WHERE id_processo_instancia = ? AND tipo_documento = 'PESQUISA_MERCADO' 
                ORDER BY criado_em DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verificarSeExisteVencedor(int $idProcesso): bool
    {
        // Verifica na grade de custos se pelo menos 1 item teve vencedor
        $sql = "SELECT 1 FROM grade_de_custos WHERE id_instancia_processo = ? AND vencedor = 1 LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return (bool) $stmt->fetchColumn();
    }
}