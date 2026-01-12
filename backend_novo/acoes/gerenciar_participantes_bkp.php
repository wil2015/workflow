<?php
// backend/acoes/gerenciar_participantes.php

// 1. Inicia buffer e desativa erros visuais
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require '../db_conexao.php'; 

// --- FUNÇÃO DE LIMPEZA EXTREMA ---
function limparNomeNuclear($string) {
    if (empty($string)) return "SEM NOME";

    // 1. Tenta converter encoding vindo do SQL Server (geralmente Windows-1252) para UTF-8
    // Isso conserta caracteres que chegam "quebrados" antes de limpar
    $utf8 = mb_convert_encoding($string, 'UTF-8', 'auto');

    // 2. Transliteração: Tenta trocar acentos por letras normais (Á -> A, ç -> c)
    // O //TRANSLIT tenta aproximar, o //IGNORE descarta se não der
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);

    // 3. REGEX FINAL: Remove TUDO que não for Letra (A-Z), Número (0-9) ou Espaço
    // Remove traços, pontos, aspas, emojis e caracteres invisíveis
    $limpo = preg_replace('/[^a-zA-Z0-9 ]/', '', $ascii);

    // 4. Remove espaços duplicados criados pela remoção
    $limpo = preg_replace('/\s+/', ' ', $limpo);

    return strtoupper(trim($limpo));
}
// ----------------------------------

$acao = $_POST['acao'] ?? '';

try {
    if (isset($pdo)) { try { $pdo->exec("SET NAMES utf8mb4"); } catch(Exception $ex) {} }

    // --- ADICIONAR FORNECEDOR ---
    if ($acao === 'salvar_participantes') {
        $idProcesso = $_POST['id_processo'] ?? '';
        $participantes = $_POST['participantes'] ?? []; 

        if (empty($idProcesso)) throw new Exception("ID do processo não informado.");
        
        $pdo->beginTransaction();
        $count = 0;

        // 1. Prepara INSERT do Fornecedor (Tabela Participantes)
        $sqlPart = "INSERT INTO licitacao_participantes 
                    (id_processo_instancia, id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf) 
                    VALUES (:id_proc, :cod, :nome, :doc)
                    ON DUPLICATE KEY UPDATE status_participante = 'Selecionado'";
        $stmtPart = $pdo->prepare($sqlPart);

        // 2. Prepara INSERT da Matriz de Cotação (Fornecedor x Itens do Processo)
        // Isso cria as linhas na tabela de ofertas automaticamente!
        $sqlMatriz = "INSERT IGNORE INTO licitacao_itens_ofertados 
                      (id_processo_instancia, num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario)
                      SELECT id_processo_instancia, num_solicitacao, seq_solicitacao, :cod_forn, NULL 
                      FROM processos_itens 
                      WHERE id_processo_instancia = :id_proc_matriz";
        $stmtMatriz = $pdo->prepare($sqlMatriz);

        foreach ($participantes as $jsonItem) {
            $forn = json_decode($jsonItem, true);
            if (!$forn) continue; 

            $nomeLimpo = limparNomeNuclear($forn['nome'] ?? '');
            if (empty($nomeLimpo)) $nomeLimpo = "FORN " . $forn['cod'];

            // A. Insere na lista de Participantes
            $stmtPart->execute([
                ':id_proc' => $idProcesso,
                ':cod'     => $forn['cod'],
                ':nome'    => $nomeLimpo,
                ':doc'     => $forn['doc']
            ]);

            // B. Gera a Matriz (Cria linha para cada item existente neste processo)
            $stmtMatriz->execute([
                ':cod_forn' => $forn['cod'],
                ':id_proc_matriz' => $idProcesso
            ]);

            $count++;
        }

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "$count fornecedor(es) vinculado(s) e matriz gerada!"]);

    // --- REMOVER FORNECEDOR ---
    } elseif ($acao === 'remover_participante') {
        $idProcesso = $_POST['id_processo'];
        $codFornecedor = $_POST['cod_fornecedor'];

        $pdo->beginTransaction();

        // 1. Remove da lista de participantes
        $stmt = $pdo->prepare("DELETE FROM licitacao_participantes 
                               WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?");
        $stmt->execute([$idProcesso, $codFornecedor]);

        // 2. Remove as linhas de cotação deste fornecedor (Limpeza)
        $stmtDel = $pdo->prepare("DELETE FROM licitacao_itens_ofertados 
                                  WHERE id_processo_instancia = ? AND id_fornecedor_senior = ?");
        $stmtDel->execute([$idProcesso, $codFornecedor]);

        $pdo->commit();
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Participante e suas ofertas removidos."]);

    } else {
        throw new Exception("Ação inválida.");
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => "Erro: " . $e->getMessage()]);
}
?>