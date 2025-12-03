<?php
// backend/acoes/gerenciar_participantes.php

// 1. Inicia o buffer para segurar qualquer erro/texto indesejado
ob_start();

// Desativa erros na tela (vão para o log)
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require '../db_conexao.php';

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'salvar_participantes') {
        $idProcesso = $_POST['id_processo'] ?? '';
        $participantes = $_POST['participantes'] ?? []; // Array de JSON strings

        if (empty($idProcesso)) throw new Exception("ID do processo não informado.");
        if (empty($participantes)) throw new Exception("Nenhum fornecedor selecionado.");

        $pdo->beginTransaction();
        $count = 0;

        // Prepara o insert
        $sql = "INSERT INTO licitacao_participantes 
                (id_processo_instancia, id_fornecedor_senior, nome_do_fornecedor) 
                VALUES (:id_proc, :cod, :nome)
                ON DUPLICATE KEY UPDATE status_participante = 'Selecionado'";
        
        $stmt = $pdo->prepare($sql);

        foreach ($participantes as $jsonItem) {
            // O JavaScript envia strings JSON, precisamos decodificar
            $forn = json_decode($jsonItem, true);
            
            if (!$forn) continue; // Pula se o JSON for inválido

            $stmt->execute([
                ':id_proc' => $idProcesso,
                ':cod'     => $forn['cod'],
                ':nome'    => $forn['nome']
                
            ]);
            $count++;
        }

        $pdo->commit();
        
        // Limpa qualquer lixo antes de enviar o sucesso
        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "$count fornecedor(es) salvo(s)!"]);

    } elseif ($acao === 'remover_participante') {
        $idProcesso = $_POST['id_processo'];
        $codFornecedor = $_POST['cod_fornecedor'];

        $stmt = $pdo->prepare("DELETE FROM licitacao_participantes 
                               WHERE id_processo_instancia = ? AND cod_fornecedor_senior = ?");
        $stmt->execute([$idProcesso, $codFornecedor]);

        ob_clean();
        echo json_encode(['sucesso' => true, 'msg' => "Participante removido."]);

    } else {
        throw new Exception("Ação inválida.");
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    
    // Limpa o buffer para garantir que o erro venha como JSON limpo
    ob_clean();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>