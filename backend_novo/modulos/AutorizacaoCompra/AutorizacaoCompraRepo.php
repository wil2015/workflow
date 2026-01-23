<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class AutorizacaoCompraRepo extends BaseRepository
{
    // --- LEITURA (MySQL) ---

    public function buscarItensVencedores($idProcesso) {
        // Busca itens consolidados na Grade de Custos
        $sql = "SELECT 
                    g.id_fornecedor_senior, 
                    g.valor_cotado, 
                    pi.num_solicitacao, 
                    pi.seq_solicitacao, 
                    pi.quantidade
                FROM grade_de_custos g 
                INNER JOIN processos_itens pi ON g.id_item = pi.id 
                WHERE g.id_instancia_processo = ? 
                ORDER BY g.id_fornecedor_senior, pi.num_solicitacao, pi.seq_solicitacao";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDadosProcesso($idProcesso) {
        $sql = "SELECT p.id, p.id_processo_senior, p.data_inicio, f.nome_do_fluxo 
                FROM processos_instancia p
                LEFT JOIN nome_do_fluxo f ON p.id_fluxo_definicao = f.id_fluxo_definicao
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listarDocumentosPorProcesso($idProcesso) {
        $sql = "SELECT id, tipo_documento, nome_arquivo, criado_em 
                FROM documentos_oficiais 
                WHERE id_processo_instancia = ? AND tipo_documento = 'AUTORIZACAO_COMPRA'
                ORDER BY criado_em DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ESCRITA (MySQL - Registro Oficial) ---

    public function registrarDocumento($idProcesso, $tipo, $meta, $idUsuario) {
        $sql = "INSERT INTO documentos_oficiais 
                (id_processo_instancia, tipo_documento, caminho_arquivo, nome_arquivo, hash_arquivo, criado_por, criado_em) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $idProcesso, 
            $tipo, 
            $meta['caminho_relativo'], // Caminho para download
            $meta['nome_arquivo'],     // Nome para exibição
            $meta['hash_sha256'],      // Hash de integridade
            $idUsuario
        ]);
    }

    // --- LEITURA (SENIOR Sapiens) ---

    public function buscarDadosFornecedorSenior($codFornecedor) {
        if (!$this->connSenior) return null;
        
        $sql = "SELECT nomfor, cgccpf, endfor, nroend, cplend, baifor, cidfor, sigufs, cepfor, fonfor 
                FROM Sapiens.sapiens.e095for WHERE codfor = ?";
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$codFornecedor]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarDetalheItemSenior($num, $seq) {
        if (!$this->connSenior) return null;
        
        $sql = "SELECT cplpro, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$num, $seq]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}