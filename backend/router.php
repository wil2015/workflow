<?php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$taskId = $_GET['task_id'] ?? '';
$processKey = $_GET['process_key'] ?? ''; // Ex: 'compra_direta.xml'

if (!$taskId || !$processKey) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros incompletos (Task ID ou XML)']);
    exit;
}

try {
    // Sua abordagem: Query direta com AND. Simples e eficiente.
    // Ajustei apenas os nomes das colunas para bater com o que criamos
    $sql = "SELECT titulo_modal, caminho_view 
            FROM etapas_do_fluxo 
            WHERE id_etapa_bpmn = ? AND id_fluxo_definicao = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId, $processKey]);
    $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etapa) {
        echo json_encode([
            'sucesso' => true,
            'titulo' => $etapa['titulo_modal'],
            'url' => $etapa['caminho_view']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'erro' => 'Rota não encontrada para este fluxo']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>