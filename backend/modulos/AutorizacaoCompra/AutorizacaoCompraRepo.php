<?php
/*require_once __DIR__ . '/../../core/BaseRepository.php';*/
namespace App\Modulos\AutorizacaoCompra;

use App\Core\BaseRepository;
use PDO;

class AutorizacaoCompraRepo extends BaseRepository
{
    // --- 1. GERAÇÃO DE DADOS (Procedure) ---
    public function executarSnapshotDados($idProcesso, $idUsuario) {
        // 1. Prepara a chamada
        $stmt = $this->pdo->prepare("CALL sp_gerar_autorizacao_snapshot(:id, :usuario)");

        // 2. Força a tipagem (Segurança extra contra erros de conversão)
        $stmt->bindValue(':id', $idProcesso, PDO::PARAM_INT);
        $stmt->bindValue(':usuario', $idUsuario, PDO::PARAM_INT);

        // 3. Executa
        $stmt->execute();

        // 4. O SEGREDO: Loop para consumir TODOS os resultados extras
        // Procedures no MySQL podem retornar múltiplos pacotes de resposta (rowsets)
        // Se não consumirmos todos, o próximo SELECT falha silenciosamente.
        try {
            do {
                // Não precisamos fazer nada, só avançar o cursor
            } while ($stmt->nextRowset());
        } catch (\Exception $e) {
            // Ignora erro se não houver mais rowsets
        }

        $stmt->closeCursor(); 
    }

    // --- 2. LEITURA DE DADOS CONGELADOS ---
    public function buscarAutorizacoesGeradas($idProcesso) {
        $sql = "SELECT * FROM autorizacao_compra WHERE id_processo_instancia = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idProcesso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAutorizacaoPorId($idProcesso, $idAutorizacao, $idFornecedorSenior = 0) {
        $params = [$idProcesso, $idAutorizacao];
        $filtroFornecedor = '';

        if ((int)$idFornecedorSenior > 0) {
            $filtroFornecedor = " AND id_fornecedor_senior = ?";
            $params[] = (int)$idFornecedorSenior;
        }

        $sql = "SELECT * FROM autorizacao_compra WHERE id_processo_instancia = ? AND id_autorizacao = ?{$filtroFornecedor} LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarItensDoSnapshot($idProcesso, $idFornecedorSenior) {
        $sql = "SELECT
                    ai.*,
                    (
                        SELECT pi.descricao_item
                        FROM processos_itens pi
                        WHERE pi.id_processo_instancia = ai.id_processo_instancia
                          AND pi.id_item = ai.id_item
                        LIMIT 1
                    ) AS descricao_item
                FROM autorizacao_item ai
                WHERE ai.id_processo_instancia = ?
                  AND ai.id_fornecedor_senior = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([(int)$idProcesso, (int)$idFornecedorSenior]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. DADOS EXTERNOS (Senior / Legado) ---
    public function buscarDadosFornecedorSenior($idFornecedor) {
        $idFornecedor = (int)$idFornecedor;
        if ($idFornecedor <= 0) {
            return [];
        }

        $this->checkSenior();

        $sql = "SELECT
                    codfor AS codfor,
                    nomfor AS nomfor,
                    cgccpf AS cgccpf,
                    endfor AS endfor,
                    nenfor AS nenfor,
                    cplend AS cplend,
                    baifor AS baifor,
                    cepfor AS cepfor,
                    cidfor AS cidfor,
                    sigufs AS sigufs,
                    fonfor AS fonfor,
                    fonfo2 AS fonfo2,
                    fonfo3 AS fonfo3,
                    intnet AS intnet
                FROM Sapiens.sapiens.e095for
                WHERE codfor = ?";

        $stmt = $this->connSenior->prepare($sql);
        $stmt->execute([$idFornecedor]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dados ?: [];
    }

    // --- 4. MANIPULAÇÃO DE ARQUIVOS FÍSICOS ---
    public function limparDocumentosAnteriores($idProcesso, $tipo) {
        $this->pdo->prepare("DELETE FROM documentos_oficiais WHERE id_processo_instancia = ? AND tipo_documento = ?")
                  ->execute([$idProcesso, $tipo]);
    }

    public function limparDocumentoAutorizacaoAnterior($idProcesso, $idAutorizacao) {
        $idArquivo = trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string)$idAutorizacao), '_');
        $stmt = $this->pdo->prepare("
            DELETE FROM documentos_oficiais
            WHERE id_processo_instancia = ?
              AND tipo_documento = 'AUTORIZACAO_COMPRA'
              AND nome_arquivo LIKE ?
        ");
        $stmt->execute([$idProcesso, 'auth_' . $idArquivo . '%']);
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
