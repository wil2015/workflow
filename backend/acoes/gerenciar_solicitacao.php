<?php
// backend/acoes/gerenciar_solicitacao.php

// 1. Inicia buffer para evitar "vazamento" de HTML de erro
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require '../db_conexao.php'; 

$acao = $_POST['acao'] ?? '';

try {
    // --- AÇÃO 1: VINCULAR (ADICIONAR) ---
    if ($acao === 'vincular') {
        $selecionados = $_POST['selecionados'] ?? [];
        
        if (empty($selecionados)) throw new Exception("Nenhum item selecionado.");

        $pdo->beginTransaction();
        $itensProcessados = 0;
        $mapaSolicitacoes = [];
        
        // Agrupa por Solicitação
        foreach ($selecionados as $itemKey) {
            // value="CODEMP-NUMSOL-SEQSOL"
            $parts = explode('-', $itemKey);
            if (count($parts) < 3) continue;
            $numsol = $parts[1];
            $seqsol = $parts[2];
            $mapaSolicitacoes[$numsol][] = $seqsol;
        }

        foreach ($mapaSolicitacoes as $numsol => $listaSeqs) {
            // Busca ou Cria Processo Pai
            $stmt = $pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
            $stmt->execute([$numsol]);
            $proc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proc) {
                $idProcesso = $proc['id'];
            } else {
                $sqlInsert = "INSERT INTO processos_instancia 
                    (id_processo_instancia, id_processo_senior, id_fluxo_definicao, data_inicio, estatus_atual, etapa_bpmn_atual) 
                    VALUES (:numsol, :numsol, 1, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([':numsol' => $numsol]);
                $idProcesso = $pdo->lastInsertId();
            }

            // Insere Itens
            $sqlItem = "INSERT IGNORE INTO processos_itens (id_processo_instancia, num_solicitacao, seq_solicitacao) VALUES (?, ?, ?)";
            $stmtItem = $pdo->prepare($sqlItem);

            foreach ($listaSeqs as $seq) {
                $stmtItem->execute([$idProcesso, $numsol, $seq]);
                if ($stmtItem->rowCount() > 0) $itensProcessados++;
            }
        }

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "$itensProcessados item(ns) salvo(s)!"]);

    // --- AÇÃO 2: REMOVER ITEM (Correção Aqui) ---
    } elseif ($acao === 'remover_item') {
        // Validação estrita para evitar erro 500
        $idProcesso = $_POST['id_processo'] ?? null;
        $numSol     = $_POST['num_solicitacao'] ?? null;
        $seqSol     = $_POST['seq_solicitacao'] ?? null;

        if (!$idProcesso || !$numSol || !$seqSol) {
            throw new Exception("Dados incompletos para exclusão (ID: $idProcesso, Sol: $numSol, Seq: $seqSol).");
        }

        $stmt = $pdo->prepare("DELETE FROM processos_itens 
                               WHERE id_processo_instancia = ? 
                               AND num_solicitacao = ? 
                               AND seq_solicitacao = ?");
        $stmt->execute([$idProcesso, $numSol, $seqSol]);

        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Item removido com sucesso."]);

    // --- AÇÃO 3: EXCLUIR PROCESSO ---
    } elseif ($acao === 'cancelar_processo') {
        $idProcessoMySQL = $_POST['id_processo'] ?? null;
        if (!$idProcessoMySQL) throw new Exception("ID do processo inválido.");

        $stmt = $pdo->prepare("DELETE FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcessoMySQL]);
        
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Processo excluído."]);

    } else {
        throw new Exception("Ação inválida ou não informada.");
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    
    // Captura o erro real e manda para o JS
    ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>