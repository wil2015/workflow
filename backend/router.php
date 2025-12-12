<?php
// backend/router.php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$taskId  = $_GET['task_id'] ?? '';
// CORREÇÃO: Ler o parâmetro 'fluxo_id' que o JS está enviando
$fluxoId = $_GET['fluxo_id'] ?? ''; 

if (!$taskId || !$fluxoId) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => "Parâmetros incompletos. Recebido: Task=$taskId, Fluxo=$fluxoId"]);
    exit;
}

try {
    // Busca na tabela usando o ID numérico do fluxo
    $sql = "SELECT titulo_modal, caminho_view 
            FROM etapas_do_fluxo 
            WHERE id_etapa_bpmn = ? AND id_fluxo_definicao = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId, $fluxoId]);
    $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etapa) {
        echo json_encode([
            'sucesso' => true,
            'titulo' => $etapa['titulo_modal'],
            'url' => $etapa['caminho_view']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'erro' => "Rota não configurada para a tarefa '$taskId' neste fluxo (ID $fluxoId)."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>