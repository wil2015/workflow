<?php
// backend/api_dashboard.php

// Define que a resposta SEMPRE será JSON, mesmo em caso de erro
header('Content-Type: application/json; charset=utf-8');

try {
    // Inclui a conexão (Certifique-se que seu db_connection.php lança exceções no PDO)
    require 'db_conexao.php'; 

    $acao = $_GET['acao'] ?? '';

    if ($acao === 'definicoes') {
        // 1. Lista os TIPOS de fluxo
        $stmt = $pdo->query("SELECT id, nome_do_fluxo, arquivo_xml FROM nome_do_fluxo WHERE ativo = 1");
        
        if (!$stmt) {
            throw new Exception("Erro ao consultar definições de fluxo.");
        }
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($acao === 'instancias') {
        // 2. Lista os processos em andamento
        $sql = "SELECT 
                    pi.id, 
                    nf.nome_do_fluxo, 
                    DATE_FORMAT(pi.data_inicio, '%d/%m/%Y %H:%i') as data_formatada,
                    pi.estatus_atual,
                    nf.arquivo_xml
                FROM processos_instancia pi
                JOIN nome_do_fluxo nf ON pi.id_fluxo_definicao = nf.id
                ORDER BY pi.data_inicio DESC LIMIT 20";
        
        $stmt = $pdo->query($sql);

        if (!$stmt) {
            throw new Exception("Erro ao consultar instâncias de processos.");
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } else {
        throw new Exception("Ação inválida ou não informada.");
    }

} catch (Exception $e) {
    // EM CASO DE ERRO:
    // 1. Define o código HTTP para 500 (Erro Interno)
    http_response_code(500);
    
    // 2. Devolve o erro em formato JSON para o JS ler
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>