<?php

declare(strict_types=1);

namespace AutorizacaoCompra\Repository;

use Exception;
use PDO;

class AutorizacaoCompraRepo
{
    private PDO $pdo;
    private ?PDO $connSenior;

    public function __construct(PDO $pdo, ?PDO $connSenior = null)
    {
        $this->pdo = $pdo;
        $this->connSenior = $connSenior;
    }

    public function executarSnapshotDados(int $idProcesso, int $idUsuario): void
    {
        $stmt = $this->pdo->prepare("CALL sp_gerar_autorizacao_snapshot(:id, :usuario)");
        $stmt->bindValue(':id', $idProcesso, PDO::PARAM_INT);
        $stmt->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        try {
            do { } while ($stmt->nextRowset());
        } catch (Exception $e) { }

        $stmt->closeCursor();
    }

    public function buscarAutorizacoesGeradas(int $idProcesso): array
    {
        $sql = "SELECT * FROM autorizacao_compra WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarItensDoSnapshot(int $idAutorizacao): array
    {
        $sql = "SELECT * FROM autorizacao_item WHERE id_autorizacao = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idAutorizacao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDadosFornecedorSenior($idFornecedor): array
    {
        return [
            'nomfor' => "Fornecedor Teste $idFornecedor",
            'cgccpf' => '00.000.000/0001-00',
            'intnet' => 'fornecedor@exemplo.com',
        ];
    }

    public function limparDocumentosAnteriores(int $idProcesso, string $tipo): void
    {
        $this->pdo->prepare("DELETE FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = ?")
            ->execute([$idProcesso, $tipo]);
    }

    public function registrarDocumento(int $idProcesso, string $tipo, array $meta, int $idUsuario): void
    {
        $sql = "INSERT INTO documentos_oficiais (id_processo_instancia, tipo_documento, caminho_arquivo, nome_arquivo, hash_arquivo, criado_por, criado_em) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso, $tipo, $meta['caminho_relativo'], $meta['nome_arquivo'], $meta['hash_sha256'], $idUsuario]);
    }

    public function listarDocumentosPorProcesso(int $idProcesso): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = 'AUTORIZACAO_COMPRA' ORDER BY criado_em DESC");
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDocumentoPorNomeParcial(int $idProcesso, string $parteNome)
    {
        $stmt = $this->pdo->prepare("SELECT caminho_arquivo FROM documentos_oficiais WHERE id_processo_instancia = ? AND nome_arquivo LIKE ? LIMIT 1");
        $stmt->execute([$idProcesso, "%$parteNome%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
