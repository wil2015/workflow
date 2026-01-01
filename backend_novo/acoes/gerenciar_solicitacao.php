<?php
// backend/acoes/gerenciar_solicitacao.php

ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require '../db_conexao.php'; 
require '../db_senior.php'; 

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

        // ... (código anterior) ...
        
        // 1. Captura o ID do processo se estivermos editando (Vem do Vue)
        $idProcessoEditando = $_POST['id_processo_instancia'] ?? null;
        // Se vier string "null" ou vazio, transforma em null real
        if ($idProcessoEditando === 'null' || empty($idProcessoEditando)) $idProcessoEditando = null;

        foreach ($mapaSolicitacoes as $numsol => $listaSeqs) {
            
            // LÓGICA CORRIGIDA:
            if ($idProcessoEditando) {
                // CENÁRIO A: Estamos editando um processo existente.
                // Forçamos todos os itens a entrarem neste processo.
                $idProcesso = $idProcessoEditando;
            } else {
                // CENÁRIO B: Estamos criando do zero (Dashboard).
                // Tenta achar um processo existente para esta solicitação ou cria novo.
                
                $stmt = $pdo->prepare("SELECT id FROM processos_instancia WHERE id_processo_senior = ? LIMIT 1");
                $stmt->execute([$numsol]);
                $proc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($proc) {
                    $idProcesso = $proc['id'];
                } else {
                    $sqlInsert = "INSERT INTO processos_instancia 
                        ( id_processo_senior, id_fluxo_definicao, data_inicio, estatus_atual, etapa_bpmn_atual) 
                        VALUES ( :numsol, :idFluxo, NOW(), 'Em Andamento', 'Activity_SelecionarSolicitacao')";
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->execute([
                        ':numsol' => $numsol,
                        ':idFluxo' => $idFluxo
                    ]);
                    $idProcesso = $pdo->lastInsertId();
                }
            }

            // Insere Item (COM A CORREÇÃO DOS 4 PARÂMETROS QUE FIZEMOS ANTES)
            $sqlItem = "INSERT IGNORE INTO processos_itens (id_processo_instancia, num_solicitacao, seq_solicitacao, quantidade) VALUES (?, ?, ?, ?)";
            $stmtItem = $pdo->prepare($sqlItem);

            // ... (resto da lógica de buscar quantidade no senior e insert cotação) ...
            
            // --- NOVA LÓGICA DE BUSCA NO SENIOR (Mantida do seu código) ---
            $qtdSenior = 1.0;
            if (isset($connSenior)) {
                $qQtd = sqlsrv_query($connSenior, "SELECT qtdsol FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?", [$numsol, $listaSeqs[0]]); // Usei listaSeqs[0] apenas como exemplo, o ideal é estar dentro do loop interno se seq variar
                 // PERA! O loop de $listaSeqs está LOGO ABAIXO. Vamos corrigir o escopo.
            }
            // -------------------------------------------------------------

            // GERA MATRIZ DE COTAÇÃO (Mantido)
            $sqlGeraCota = "INSERT IGNORE INTO licitacao_itens_ofertados 
                            (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                            SELECT ?, ?, ?, id_fornecedor_senior, NULL 
                            FROM licitacao_participantes 
                            WHERE id_processo_instancia = ?";
            $stmtGeraCota = $pdo->prepare($sqlGeraCota);

            foreach ($listaSeqs as $seq) {
                 // BUSCA QTD REAL (Movemos para cá para pegar a seq correta)
                 $qtdSenior = 1.0;
                 if (isset($connSenior)) {
                     $qQtd = sqlsrv_query($connSenior, "SELECT qtdsol FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?", [$numsol, $seq]);
                     if ($qQtd && $rowQ = sqlsrv_fetch_array($qQtd, SQLSRV_FETCH_ASSOC)) {
                         $qtdSenior = (float)$rowQ['qtdsol'];
                     }
                 }

                // EXECUTA O INSERT COM 4 PARÂMETROS
                $stmtItem->execute([$idProcesso, $numsol, $seq, $qtdSenior]);
                
                if ($stmtItem->rowCount() > 0) {
                    $itensProcessados++;
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