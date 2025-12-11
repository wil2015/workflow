<?php
// backend/api_dashboard.php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

$acao = $_GET['acao'] ?? '';

try {
    if ($acao === 'definicoes') {
        // Lista os fluxos disponíveis para iniciar (Cards)
        $stmt = $pdo->query("SELECT id, nome_do_fluxo, arquivo_xml FROM nome_do_fluxo WHERE ativo = 1");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } elseif ($acao === 'instancias') {
        // Lista o histórico (Tabela)
        // MUDANÇA: Adicionado id_processo_senior no SELECT e JOIN para pegar o nome do fluxo
        $sql = "
            SELECT 
                pi.id, 
                pi.id_processo_senior, 
                pi.data_inicio, 
                pi.estatus_atual,
                nf.nome_do_fluxo,
                nf.arquivo_xml
            FROM processos_instancia pi
            JOIN nome_do_fluxo nf ON pi.id_fluxo_definicao = nf.id
            ORDER BY pi.data_inicio DESC
        ";
        
        $stmt = $pdo->query($sql);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata a data para BR
        foreach ($dados as &$linha) {
            $linha['data_formatada'] = date('d/m/Y H:i', strtotime($linha['data_inicio']));
            // Cria um campo timestamp para ordenação correta do DataTables
            $linha['data_order'] = strtotime($linha['data_inicio']);
        }

        echo json_encode($dados);

    } else {
        throw new Exception("Ação inválida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>