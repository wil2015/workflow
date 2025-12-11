<?php
// backend/api_dashboard.php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$acao = $_GET['acao'] ?? '';

try {
    if ($acao === 'definicoes') {
        // Lista os fluxos disponíveis para criar novo (Botões do topo)
        $stmt = $pdo->query("SELECT id, nome_do_fluxo, arquivo_xml FROM nome_do_fluxo WHERE ativo = 1");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($acao === 'instancias') {
        // Lista os processos existentes (Tabela)
        // CRUCIAL: O SELECT PRECISA TRAZER 'd.arquivo_xml'
        $sql = "SELECT 
                    p.id, 
                    p.id_processo_senior, 
                    p.data_inicio, 
                    p.estatus_atual, 
                    d.nome_do_fluxo,
                    d.arquivo_xml
                FROM processos_instancia p
                LEFT JOIN nome_do_fluxo d ON p.id_fluxo_definicao = d.id
                ORDER BY p.id DESC";
        
        $stmt = $pdo->query($sql);
        $result = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // FALLBACK DE SEGURANÇA:
            // Se o banco trouxer vazio (NULL), forçamos o padrão para não travar o JS
            if (empty($row['arquivo_xml'])) {
                $row['arquivo_xml'] = 'compras.xml'; 
                $row['nome_do_fluxo'] = $row['nome_do_fluxo'] . ' (Recuperado)';
            }

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