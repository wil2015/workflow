<?php
require_once __DIR__ . '/../../core/BaseRepository.php';

class AutorizacaoCompraRepo extends BaseRepository
{
    public function buscarItensVencedores($idProcesso) {
        // Tabela grade_de_custos
        $sql = "SELECT * FROM grade_de_custos WHERE id_instancia_processo = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDetalhesItem($idItem) {
        $sql = "SELECT num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idItem]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarDescricaoItemSenior($num, $seq) {
        return "Produto Ref. Solicitacao $num-$seq";
    }

    public function buscarDadosFornecedorSenior($idFornecedor) {
        return ['nomfor' => "Fornecedor $idFornecedor", 'cgccpf' => '00.000.000/0001-00', 'intnet' => 'teste@exemplo.com'];
    }

    public function limparDocumentosAnteriores($idProcesso, $tipo) {
        $this->pdo->prepare("DELETE FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = ?")->execute([$idProcesso, $tipo]);
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