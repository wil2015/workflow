<?php
// Sobe 2 níveis para achar a conexão global
require '../../db_conexao.php'; 
require 'DashboardService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Agora o $pdo existe porque editamos o db_conexao.php no Passo 0
    $service = new DashboardService($pdo);
    
    $acao = $_GET['acao'] ?? 'home';

    if ($acao === 'home') {
        echo json_encode($service->carregarHome());
    } else {
        throw new Exception("Ação inválida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}