<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class AutorizacaoCompraRepo extends BaseRepository
{
    // ... (Mantenha os métodos existentes: temItensVencedores, buscarItensVencedores, etc) ...

    // --- NOVO MÉTODO: Ajuda a achar o arquivo certo na hora de enviar email ---
    public function buscarDocumentoPorNomeParcial($idProcesso, $parteDoNome) {
        $sql = "SELECT caminho_arquivo 
                FROM documentos_oficiais 
                WHERE id_processo_instancia = ? 
                AND tipo_documento = 'AUTORIZACAO_COMPRA'
                AND nome_arquivo LIKE ?
                ORDER BY criado_em DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        // O % envolve a parte do nome para buscar em qualquer lugar da string
        $stmt->execute([$idProcesso, "%$parteDoNome%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ... (Mantenha registrarDocumento, listarDocumentosPorProcesso, etc) ...
    
    // CASO TENHA PERDIDO, AQUI ESTÁ O REGISTRAR ATUALIZADO:
    public function registrarDocumento($idProcesso, $tipo, $meta, $idUsuario) {
        $sql = "INSERT INTO documentos_oficiais 
                (id_processo_instancia, tipo_documento, caminho_arquivo, nome_arquivo, hash_arquivo, criado_por, criado_em) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $tipo, $meta['caminho_relativo'], $meta['nome_arquivo'], $meta['hash_sha256'], $idUsuario]);
    }
    
    // ... Demais métodos originais ...
}