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
        
        // Matriz: cotacao[NUM-SEQ][COD_FORNECEDOR] = VALOR
        $cotacoes = $_POST['cotacao'] ?? [];

        if (empty($idProcesso)) throw new Exception("ID do processo inválido.");

        $pdo->beginTransaction();

        // Limpa anteriores deste processo para regravação limpa
        $stmtDel = $pdo->prepare("DELETE FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmtDel->execute([$idProcesso]);

        $stmtInsert = $pdo->prepare("INSERT INTO licitacao_itens_ofertados 
            (id_processo_instancia, num_solicitacao, seq_solicitacao, cod_fornecedor_senior, valor_unitario) 
            VALUES (:id_proc, :num, :seq, :cod, :val)");

        $count = 0;

        foreach ($cotacoes as $chaveItem => $fornecedores) {
            // Explode a chave "NUM-SEQ" (Ex: "26462-1")
            $parts = explode('-', $chaveItem);
            if (count($parts) < 2) continue; // Ignora se chave inválida
            
            $numSol = $parts[0];
            $seqSol = $parts[1];

            foreach ($fornecedores as $codForn => $valor) {
                if ($valor === '' || $valor === null) continue;

                // Formata moeda
                $valorFloat = str_replace('.', '', $valor);
                $valorFloat = str_replace(',', '.', $valorFloat);

                $stmtInsert->execute([
                    ':id_proc' => $idProcesso,
                    ':num'     => $numSol,
                    ':seq'     => $seqSol,
                    ':cod'     => $codForn,
                    ':val'     => $valorFloat
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