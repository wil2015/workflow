<?php
namespace App\Modulos\PesquisaMercado;

use App\Core\BaseRepository;
use PDO;

class PesquisaMercadoRepo extends BaseRepository
{
    public function buscarDadosComparativos(int $idProcesso)
    {
        // REMOVIDA A COLUNA INEXISTENTE, AGORA BUSCAMOS NUM E SEQ
        $sql = "SELECT 
                    g.id_item,
                    i.num_solicitacao,
                    i.seq_solicitacao,
                    p.nome_do_fornecedor,
                    p.cnpj_cpf,
                    g.valor_cotado as valor_unitario,
                    g.valor_total as valor_total_item,
                    g.vencedor
                FROM grade_de_custos g
                
                INNER JOIN processos_itens i 
                    ON g.id_item = i.id 
                
                INNER JOIN licitacao_participantes p 
                    ON g.id_fornecedor_senior = p.id_fornecedor_senior 
                   AND g.id_instancia_processo = p.id_processo_instancia
                   
                WHERE g.id_instancia_processo = ?
                ORDER BY g.id_item ASC, g.valor_cotado ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // MÉTODO NOVO: Busca a descrição direto no Senior
    public function buscarDetalheSenior($num, $seq) {
        if (!$this->connSenior) return null;
        $stmt = $this->connSenior->prepare("SELECT cplpro FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?");
        $stmt->execute([$num, $seq]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
        $sql = "SELECT 1 FROM grade_de_custos WHERE id_instancia_processo = ? AND vencedor = 1 LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return (bool) $stmt->fetchColumn();
    }

    // --- MANIPULAÇÃO DE ARQUIVOS FÍSICOS NO BANCO ---
    public function limparDocumentosAnteriores($idProcesso, $tipo) {
        $this->pdo->prepare("DELETE FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = ?")
                  ->execute([$idProcesso, $tipo]);
    }

    public function registrarDocumento($idProcesso, $tipo, $meta, $idUsuario) {
        $sql = "INSERT INTO documentos_oficiais (id_processo_instancia, tipo_documento, caminho_arquivo, nome_arquivo, hash_arquivo, criado_por, criado_em) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $idProcesso, 
            $tipo, 
            $meta['caminho_relativo'], 
            $meta['nome_arquivo'], 
            $meta['hash_sha256'], 
            $idUsuario
        ]);
    }
}