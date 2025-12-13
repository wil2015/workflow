<?php
// backend/acoes/gerenciar_solicitacao.php

ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require '../db_conexao.php'; 

$acao = $_POST['acao'] ?? '';

$idFluxo = $_POST['id_fluxo_definicao'] ?? 1; // Lê do input hidden

try {
    // --- 1. ADICIONAR (VINCULAR) ITENS ---
    if ($acao === 'vincular') {
        $selecionados = $_POST['selecionados'] ?? [];
        
        if (empty($selecionados)) throw new Exception("Nenhum item selecionado.");

        $pdo->beginTransaction();
        $itensProcessados = 0;
        $mapaSolicitacoes = [];
        
        foreach ($selecionados as $itemKey) {
            $parts = explode('-', $itemKey);
            if (count($parts) < 3) continue;
            $numsol = $parts[1];
            $seqsol = $parts[2];
            $mapaSolicitacoes[$numsol][] = $seqsol;
        }

        foreach ($mapaSolicitacoes as $numsol => $listaSeqs) {
            // Busca ID do Processo
            $stmt = $pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
            $stmt->execute([$numsol]);
            $proc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proc) {
                $idProcesso = $proc['id'];
            } else {
                $sqlInsert = "INSERT INTO processos_instancia 
                    ( id_processo_instancia, id_processo_senior, id_fluxo_definicao, data_inicio, estatus_atual, etapa_bpmn_atual) 
                    VALUES ( :numsol, :numsol, :idFluxo, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':numsol' => $numsol,
                    ':idFluxo' => $idFluxo
                ]);
                $idProcesso = $pdo->lastInsertId();
            }

            // Insere Item
            $sqlItem = "INSERT IGNORE INTO processos_itens (id_processo_instancia, num_solicitacao, seq_solicitacao) VALUES (?, ?, ?)";
            $stmtItem = $pdo->prepare($sqlItem);

            // GERA MATRIZ DE COTAÇÃO PARA ITENS NOVOS
            // Se já existirem fornecedores no processo, criamos as linhas vazias na cotação para esse novo item
            $sqlGeraCota = "INSERT IGNORE INTO licitacao_itens_ofertados 
                            (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                            SELECT ?, ?, ?, id_fornecedor_senior, NULL 
                            FROM licitacao_participantes 
                            WHERE id_processo_instancia = ?";
            $stmtGeraCota = $pdo->prepare($sqlGeraCota);

            foreach ($listaSeqs as $seq) {
                $stmtItem->execute([$idProcesso, $numsol, $seq]);
                if ($stmtItem->rowCount() > 0) {
                    $itensProcessados++;
                    // Cria linhas de cotação vazias para os fornecedores que já estão no processo
                    $stmtGeraCota->execute([$idProcesso, $numsol, $seq, $idProcesso]);
                }
            }
        }

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "$itensProcessados item(ns) adicionado(s)!"]);

    // --- 2. REMOVER ITEM (COM LIMPEZA EM CASCATA) ---
    } elseif ($acao === 'remover_item') {
        $idProcesso = $_POST['id_processo'] ?? null;
        $numSol     = $_POST['num_solicitacao'] ?? null;
        $seqSol     = $_POST['seq_solicitacao'] ?? null;

        if (!$idProcesso || !$numSol || !$seqSol) {
            throw new Exception("Dados incompletos.");
        }

        $pdo->beginTransaction();

        // A. LIMPEZA: Remove qualquer cotação/valor atrelado a este item
        // Isso garante que não sobre "lixo" na tabela de valores
        $stmtClean = $pdo->prepare("DELETE FROM licitacao_itens_ofertados 
                                    WHERE id_processo_instancia = ? 
                                    AND num_solicitacao = ? 
                                    AND seq_solicitacao = ?");
        $stmtClean->execute([$idProcesso, $numSol, $seqSol]);

        // B. REMOÇÃO: Remove o item do processo
        $stmtDelete = $pdo->prepare("DELETE FROM processos_itens 
                                     WHERE id_processo_instancia = ? 
                                     AND num_solicitacao = ? 
                                     AND seq_solicitacao = ?");
        $stmtDelete->execute([$idProcesso, $numSol, $seqSol]);

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Item e suas cotações foram removidos."]);

    // --- 3. EXCLUIR PROCESSO ---
    } elseif ($acao === 'cancelar_processo') {
        $idProcessoMySQL = $_POST['id_processo'] ?? null;
        if (!$idProcessoMySQL) throw new Exception("ID inválido.");

        $stmt = $pdo->prepare("DELETE FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcessoMySQL]);
        
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Processo excluído."]);

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