<?php
// backend/api_dashboard.php
ini_set('display_errors', 0); // Evita lixo no JSON, mas não mascara lógica ruim
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$acao = $_GET['acao'] ?? '';

try {
    if ($acao === 'definicoes') {
        // MODO NOVO PROCESSO:
        // Buscamos estritamente o id_fluxo_definicao (lógico)
        $sql = "SELECT 
                    id_fluxo_definicao as fluxo_id, 
                    nome_do_fluxo, 
                    arquivo_xml 
                FROM nome_do_fluxo 
                WHERE ativo = 1 
                AND id_fluxo_definicao IS NOT NULL"; // Só traz se estiver configurado certo
                
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($acao === 'instancias') {
        // MODO LISTA DE PROCESSOS:
        // Join estrito. Retorna d.id_fluxo_definicao para o Frontend usar no Router.
        
        $sql = "SELECT 
                    p.id, 
                    p.id_processo_senior, 
                    p.data_inicio, 
                    p.estatus_atual, 
                    d.nome_do_fluxo,
                    d.arquivo_xml,
                    d.id_fluxo_definicao as fluxo_id  -- <--- CAMPO CORRETO E ÚNICO
                FROM processos_instancia p
                INNER JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id
                ORDER BY p.id DESC";
        
        $stmt = $pdo->query($sql);
        $result = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Sem fallbacks mágicos. Se o banco estiver errado, o campo vai vazio
            // e o JS vai alertar, o que é o comportamento correto para debug.
            
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