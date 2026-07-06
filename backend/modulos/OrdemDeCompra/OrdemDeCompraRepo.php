<?php
namespace App\Modulos\OrdemDeCompra;

use App\Core\BaseRepository;
use PDO;

class OrdemDeCompraRepo extends BaseRepository
{
    public function listarAutorizacoes($idProcesso): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                id_processo_instancia,
                id_fornecedor_senior,
                id_autorizacao,
                nome_do_fornecedor,
                valor_total_pedido,
                ordem_de_compra
            FROM autorizacao_compra
            WHERE id_processo_instancia = ?
            ORDER BY id_autorizacao, nome_do_fornecedor
        ");
        $stmt->execute([(int)$idProcesso]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAutorizacao($idProcesso, $idAutorizacao, $idFornecedorSenior): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM autorizacao_compra
            WHERE id_processo_instancia = ?
              AND id_autorizacao = ?
              AND id_fornecedor_senior = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$idProcesso, (string)$idAutorizacao, (int)$idFornecedorSenior]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return $linha ?: null;
    }

    public function buscarItensAutorizacao($idProcesso, $idAutorizacao, $idFornecedorSenior): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id_item,
                quantidade,
                valor_cotado,
                valor_total
            FROM autorizacao_item
            WHERE id_processo_instancia = ?
              AND id_autorizacao = ?
              AND id_fornecedor_senior = ?
            ORDER BY id
        ");
        $stmt->execute([(int)$idProcesso, (string)$idAutorizacao, (int)$idFornecedorSenior]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarNumeroOrdemCompra($idProcesso, $idAutorizacao, $idFornecedorSenior, $numeroOrdemCompra): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE autorizacao_compra
               SET ordem_de_compra = ?
             WHERE id_processo_instancia = ?
               AND id_autorizacao = ?
               AND id_fornecedor_senior = ?
        ");
        $stmt->execute([
            (int)$numeroOrdemCompra,
            (int)$idProcesso,
            (string)$idAutorizacao,
            (int)$idFornecedorSenior,
        ]);
    }

    public function buscarDadosFornecedorSenior($idFornecedor): array
    {
        $idFornecedor = (int)$idFornecedor;
        if ($idFornecedor <= 0) {
            return [];
        }

        $this->checkSenior();

        $stmt = $this->connSenior->prepare("
            SELECT
                codfor AS codfor,
                nomfor AS nomfor
            FROM Sapiens.sapiens.e095for
            WHERE codfor = ?
        ");
        $stmt->execute([$idFornecedor]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dados ?: [];
    }
}
