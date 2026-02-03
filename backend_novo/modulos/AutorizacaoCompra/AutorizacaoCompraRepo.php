<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class AutorizacaoCompraRepo extends BaseRepository
{
    // --- 1. GERAÇÃO DE DADOS (Procedure) ---
    public function executarSnapshotDados($idProcesso, $idUsuario) {
        $stmt = $this->pdo->prepare("CALL sp_gerar_autorizacao_snapshot(?, ?)");
        $stmt->execute([$idProcesso, $idUsuario]);
    }

    // --- 2. LEITURA DE DADOS CONGELADOS ---
    public function buscarAutorizacoesGeradas($idProcesso) {
        $sql = "SELECT * FROM autorizacao_compra WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensDoSnapshot($idAutorizacao) {
        $sql = "SELECT * FROM autorizacao_item WHERE id_autorizacao = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idAutorizacao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. DADOS EXTERNOS (Senior / Legado) ---
    public function buscarDadosFornecedorSenior($idFornecedor) {
        // Aqui entraria a conexão com SQL Server se necessário
        return [
            'nomfor' => "Fornecedor Teste $idFornecedor", 
            'cgccpf' => '00.000.000/0001-00', 
            'intnet' => 'fornecedor@exemplo.com'
        ];
    }

    // --- 4. MANIPULAÇÃO DE ARQUIVOS FÍSICOS (Mantido igual) ---
    public function limparDocumentosAnteriores($idProcesso, $tipo) {
        $this->pdo->prepare("DELETE FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = ?")
                  ->execute([$idProcesso, $tipo]);
    }

    public function registrarDocumento($idProcesso, $tipo, $meta, $idUsuario) {
        $sql = "INSERT INTO documentos_oficiais (id_processo_instancia, tipo_documento, caminho_arquivo, nome_arquivo, hash_arquivo, criado_por, criado_em) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $tipo, $meta['caminho_relativo'], $meta['nome_arquivo'], $meta['hash_sha256'], $idUsuario]);
    }

    public function listarDocumentosPorProcesso($idProcesso) {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = 'AUTORIZACAO_COMPRA' ORDER BY criado_em DESC");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDocumentoPorNomeParcial($idProcesso, $parteNome) {
        $stmt = $this->pdo->prepare("SELECT caminho_arquivo FROM documentos_oficiais WHERE id_processo_instancia = ? AND nome_arquivo LIKE ? LIMIT 1");
        $stmt->execute([$idProcesso, "%$parteNome%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}