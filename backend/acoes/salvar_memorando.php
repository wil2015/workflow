<?php
// backend/acoes/salvar_memorando.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'gerar_memorando') {
        $idProcesso = $_POST['id_processo'];
        $idForn     = $_POST['id_fornecedor'];
        $itens      = json_decode($_POST['itens'], true); // Lista de objetos {num, seq, qtd, preco}

        if (empty($itens)) throw new Exception("Nenhum item selecionado.");

        $pdo->beginTransaction();

        // 1. Cria Cabeçalho do Memorando
        // Podemos gerar um código sequencial ou usar o ID
        $stmtHead = $pdo->prepare("INSERT INTO memorandos_oc (id_processo_instancia, id_fornecedor_senior, observacao) VALUES (?, ?, ?)");
        $stmtHead->execute([$idProcesso, $idForn, 'Memorando de itens NF']);
        $idMemo = $pdo->lastInsertId();

        // 2. Insere Itens
        $stmtItem = $pdo->prepare("INSERT INTO memorandos_oc_itens 
            (id_memorando, num_solicitacao, seq_solicitacao, qtd_faturar, valor_unitario) 
            VALUES (?, ?, ?, ?, ?)");

        foreach ($itens as $i) {
            $stmtItem->execute([
                $idMemo,
                $i['num'],
                $i['seq'],
                $i['qtd'],   // Qtd Total (Full)
                $i['preco']  // Preço Congelado
            ]);
        }

        // AQUI: Integração Futura com Senior (Webservice)
        // ...

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Memorando #$idMemo gerado com sucesso!"]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>