<?php
// backend/api_dashboard.php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$acao = $_GET['acao'] ?? '';

try {
    if ($acao === 'definicoes') {
        // --- NOVO PROCESSO ---
        // Envia estritamente o ID Lógico (id_fluxo_definicao) para o botão "Novo"
        $sql = "SELECT 
                    id_fluxo_definicao as fluxo_id, 
                    nome_do_fluxo, 
                    arquivo_xml 
                FROM nome_do_fluxo 
                WHERE ativo = 1 
                AND id_fluxo_definicao IS NOT NULL";
                
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($acao === 'instancias') {
        // --- LISTA DE PROCESSOS ---
        // CORREÇÃO CRUCIAL: O JOIN agora respeita a modelagem lógica
        // Liga o ID Lógico da instância (p) com o ID Lógico da definição (d)
        $sql = "SELECT 
                    p.id, 
                    p.id_processo_senior, 
                    p.data_inicio, 
                    p.estatus_atual, 
                    d.nome_do_fluxo,
                    d.arquivo_xml,
                    d.id_fluxo_definicao as fluxo_id 
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id_fluxo_definicao
                ORDER BY p.id DESC";
        
        $stmt = $pdo->query($sql);
        $result = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dt = new DateTime($row['data_inicio']);
            $row['data_formatada'] = $dt->format('d/m/Y H:i');
            $row['data_order'] = $dt->getTimestamp();
            $result[] = $row;
        }
        echo json_encode($result);
    } 
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>