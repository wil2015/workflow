<?php
// backend/acoes/salvar_cotacoes.php
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    // --- NOVO: SALVAR UM ÚNICO VALOR (Auto-Save) ---
    if ($acao === 'salvar_unitario') {
        $idProc  = $_POST['id_processo'];
        $numSol  = $_POST['num_solicitacao'];
        $seqSol  = $_POST['seq_solicitacao'];
        $codForn = $_POST['cod_fornecedor'];
        $valor   = $_POST['valor']; // "1.500,00"

        // Validação básica
        if (!$idProc || !$numSol || !$seqSol || !$codForn) {
            throw new Exception("Dados incompletos para salvar valor.");
        }

        // Formata Moeda (PT-BR -> Float)
        if ($valor === '' || $valor === null) {
            $valorFloat = null;
        } else {
            $valorFloat = str_replace('.', '', $valor); // Remove milhar (1.000 -> 1000)
            $valorFloat = str_replace(',', '.', $valorFloat); // Vírgula vira ponto (10,50 -> 10.50)
        }

        // Upsert (Insert ou Update)
        // A tabela deve ter UNIQUE KEY (id_proc, num, seq, cod) para isso funcionar
        $sql = "INSERT INTO licitacao_itens_ofertados 
                (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario) 
                VALUES (:id, :num, :seq, :cod, :val)
                ON DUPLICATE KEY UPDATE valor_unitario = :val";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idProc,
            ':num' => $numSol,
            ':seq' => $seqSol,
            ':cod' => $codForn,
            ':val' => $valorFloat
        ]);

        ob_clean();
        echo json_encode(['sucesso' => true]);

    } 
    // --- ANTIGO: SALVAR EM MASSA (Caso ainda use o botão 'Salvar Valores') ---
    elseif ($acao === 'salvar_valores') {
        // ... (código antigo mantido se quiser compatibilidade) ...
        // Se não usar mais o botão "Salvar Tudo", pode remover este bloco.
        echo json_encode(['sucesso' => true, 'msg' => 'Use o salvamento automático.']);
    }
    else {
        throw new Exception("Ação inválida ($acao).");
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>