<?php
// backend/acoes/salvar_cotacoes.php
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'salvar_valores') {
        $idProcesso = $_POST['id_processo'];
        
        // Matriz: cotacao[SEQ_ITEM_SENIOR][COD_FORNECEDOR] = VALOR
        $cotacoes = $_POST['cotacao'] ?? [];
        // Array auxiliar com dados do item (nome, qtd) que vieram hidden do form
        $detalhes = $_POST['detalhes'] ?? []; 

        if (empty($idProcesso)) throw new Exception("ID do processo inválido.");

        $pdo->beginTransaction();

        // 1. Limpa lançamentos anteriores deste processo para regravação (estratégia mais segura para matriz)
        // Se preferir UPDATE, precisaríamos de uma chave única composta (id_proc + id_forn + item)
        $stmtDel = $pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmtDel->execute([$idProcesso]);

        $stmtInsert = $pdo->prepare("INSERT INTO licitacao_itens_ofertados 
            (id_processo_instancia, id_fornecedor_senior, item_solicitado, quantidade, valor_unitario, marca_modelo) 
            VALUES (:id_proc, :cod_forn, :item, :qtd, :valor, :marca)");

        $count = 0;

        foreach ($cotacoes as $seqItem => $fornecedores) {
            
            // Dados do item (Nome e Qtd) que vieram do Senior para o Form
            $nomeItem = $detalhes[$seqItem]['nome'] ?? 'Item ' . $seqItem;
            $qtdItem  = $detalhes[$seqItem]['qtd'] ?? 1;

            foreach ($fornecedores as $codForn => $valor) {
                // Se valor vazio, pula
                if ($valor === '' || $valor === null) continue;

                // Formata moeda (1.500,00 -> 1500.00)
                $valorFloat = str_replace('.', '', $valor);
                $valorFloat = str_replace(',', '.', $valorFloat);

                // Pega marca (se houver campo input de marca)
                $marca = $_POST['marca'][$seqItem][$codForn] ?? '';

                $stmtInsert->execute([
                    ':id_proc'  => $idProcesso,
                    ':cod_forn' => $codForn,
                    ':item'     => $nomeItem, // Salvamos o nome do item aqui
                    ':qtd'      => $qtdItem,
                    ':valor'    => $valorFloat,
                    ':marca'    => $marca
                ]);
                $count++;
            }
        }

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "$count ofertas registradas!"]);

    } else {
        throw new Exception("Ação inválida.");
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>