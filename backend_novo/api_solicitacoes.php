<?php
// backend_novo/api_solicitacoes.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

try {
    // --- PARÂMETROS DO VUE ---
    $pagina = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limite = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $busca  = $_GET['search'] ?? '';
    $instance_id = $_GET['instance_id'] ?? 0;

    $offset = ($pagina - 1) * $limite;

    // 1. MAPEAMENTO DE VÍNCULOS (Quem é dono de qual item)
    $itensEmUso = [];
    $clausulasMeusItens = []; 
    
    $stmt = $pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        $itensEmUso[$chave] = $row['id_processo_instancia'];

        if ($instance_id && $row['id_processo_instancia'] == $instance_id) {
            $n = $row['num_solicitacao'];
            $s = $row['seq_solicitacao'];
            $clausulasMeusItens[] = "(numsol = $n AND seqsol = $s)";
        }
    }

    // 2. CONSTRUÇÃO DA QUERY COM FILTRO
    $colunas = "codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol";
    $tabela  = "Sapiens.sapiens.e405sol";
    
    // Filtro base: Situação 1 ou 2
    $whereSql = "WHERE sitsol IN (1, 2)";
    $params = [];

    // Se tiver busca digitada
    if (!empty($busca)) {
        $whereSql .= " AND (cplpro LIKE ? OR CAST(numsol AS VARCHAR) LIKE ? OR numprj LIKE ?)";
        $termo = "%$busca%";
        $params = [$termo, $termo, $termo];
    }

    // 3. CONTAGEM TOTAL (Para calcular quantas páginas existem)
    $sqlCount = "SELECT COUNT(*) as total FROM $tabela $whereSql";
    $stmtCount = sqlsrv_query($connSenior, $sqlCount, $params);
    $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalRegistros = $rowCount['total'];

    // 4. ORDENAÇÃO INTELIGENTE
    $orderBy = "ORDER BY datsol DESC";
    if (!empty($clausulasMeusItens)) {
        $condicaoSql = implode(" OR ", $clausulasMeusItens);
        $orderBy = "ORDER BY CASE WHEN $condicaoSql THEN 0 ELSE 1 END ASC, datsol DESC";
    }

    // 5. QUERY PAGINADA (A Mágica da Velocidade)
    $sqlFinal = "SELECT $colunas FROM $tabela $whereSql 
                 $orderBy 
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    // Adiciona offset e limit aos parâmetros
    $params[] = $offset;
    $params[] = $limite;

    $stmt = sqlsrv_query($connSenior, $sqlFinal, $params);
    if ($stmt === false) throw new Exception("Erro SQL Senior");

    $dados = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $num = $row['numsol'];
        $seq = $row['seqsol'];
        $chave = $num . '-' . $seq;
        
        $idDono = $itensEmUso[$chave] ?? null;
        $status = 'disponivel';
        $bloqueador = '';

        if ($idDono) {
            if ($instance_id && $idDono == $instance_id) {
                $status = 'vinculado';
            } else {
                $status = 'bloqueado';
                $bloqueador = "Proc. #$idDono";
            }
        }

        $dados[] = [
            'id' => $row['codemp'] . '-' . $num . '-' . $seq,
            'projeto' => trim((string)$row['numprj']),
            'data_solicitacao' => $row['datsol'] ? $row['datsol']->format('Y-m-d') : null,
            'id_solicitacao_senior' => "$num-$seq",
            'descricao_produto' => utf8_encode($row['cplpro']), 
            'quantidade' => (float)$row['qtdsol'],
            'preco_unitario' => (float)$row['presol'],
            'unidade' => trim($row['unimed']),
            'status' => $status,
            'proc_bloqueador' => $bloqueador
        ];
    }

    echo json_encode([
        'sucesso' => true,
        'data' => $dados,
        'total' => $totalRegistros, // Vue precisa disso para saber o total de páginas
        'pagina' => $pagina
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>