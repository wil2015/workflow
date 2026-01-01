<?php
// backend_novo/api_solicitacoes.php
ob_start(); // Previne lixo no JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db_conexao.php';
require 'db_senior.php';

function utf8_clean($arr) {
    array_walk_recursive($arr, function(&$val) {
        if (is_string($val) && !mb_detect_encoding($val, 'utf-8', true)) $val = utf8_encode($val);
    });
    return $arr;
}

try {
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $draw = (int)($_GET['draw'] ?? 1);
    $search = $_GET['search']['value'] ?? '';
    $instance_id = (int)($_GET['instance_id'] ?? 0);
    
    // Ordenação vinda do DataTables
    $colIdx = $_GET['order'][0]['column'] ?? 1; // Padrão Projeto (1)
    $colDir = $_GET['order'][0]['dir'] ?? 'desc';
    $dirSQL = ($colDir === 'asc') ? 'ASC' : 'DESC';

    // Mapeamento Colunas (Índice JS => Coluna SQL)
    $colMap = [
        1 => 'numprj',
        2 => 'datsol',
        3 => 'numsol',
        4 => 'cplpro',
        5 => 'presol',
        6 => 'peso_ordenacao' // Mágica aqui! Ordena pela coluna virtual
    ];
    $campoOrdenacao = $colMap[$colIdx] ?? 'numprj';

    // 1. Identificar Vínculos (Meus vs Bloqueados)
    $meusItens = [];
    $bloqueados = [];
    $mapaDonos = []; // Para saber quem bloqueou

    if ($instance_id >= 0) {
        $stmt = $pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $n = (int)$row['num_solicitacao'];
            $s = (int)$row['seq_solicitacao'];
            $chave = "$n-$s";
            
            // Condição SQL Segura para o SQL Server
            $sqlCond = "(CAST(numsol AS INT) = $n AND CAST(seqsol AS INT) = $s)";
            
            $mapaDonos[$chave] = $row['id_processo_instancia'];

            if ($instance_id && $row['id_processo_instancia'] == $instance_id) {
                $meusItens[] = $sqlCond;
            } else {
                $bloqueados[] = $sqlCond;
            }
        }
    }

    // 2. Montar Coluna Virtual de Peso (Calculada no SELECT)
    // Peso 2: Meu Vinculado
    // Peso 1: Bloqueado
    // Peso 0: Disponível
    $caseParts = [];
    if (!empty($meusItens))  $caseParts[] = "WHEN (" . implode(" OR ", $meusItens) . ") THEN 2";
    if (!empty($bloqueados)) $caseParts[] = "WHEN (" . implode(" OR ", $bloqueados) . ") THEN 1";
    
    // Se não tiver regras, peso é sempre 0
    $colunaPeso = empty($caseParts) ? "0" : "CASE " . implode(" ", $caseParts) . " ELSE 0 END";

    // 3. Montar ORDER BY
    // Lógica: 
    // Se clicar em Status (col 6): Ordena pelo Peso (2->1->0 ou inverso).
    // Se clicar em Outros: Ordena pelo Peso DESC (fixa meus no topo) e depois pela coluna.
    
    if ($colIdx == 6) {
        // Clicou Status
        $orderBy = "ORDER BY peso_ordenacao $dirSQL, datsol DESC";
    } else {
        // Clicou Outro (ex: Projeto)
        // Fixa 'Meus' (Peso 2) no topo, ordena resto pela coluna escolhida
        $orderBy = "ORDER BY peso_ordenacao DESC, $campoOrdenacao $dirSQL";
    }

    // 4. Query Principal
    $tabela = "Sapiens.sapiens.e405sol";
    
    // Filtro WHERE (Disponíveis OR Meus OR Bloqueados)
    // Importante: Bloqueados também aparecem para mostrar o cadeado
    $filtroStatus = "sitsol IN (1, 2)";
    $todosEmUso = array_merge($meusItens, $bloqueados);
    if (!empty($todosEmUso)) {
        $filtroStatus = "($filtroStatus OR (" . implode(" OR ", $todosEmUso) . "))";
    }
    
    $where = "WHERE $filtroStatus";
    $params = [];

    if ($search) {
        $where .= " AND (cplpro LIKE ? OR CAST(numsol AS VARCHAR(20)) LIKE ? OR numprj LIKE ?)";
        $termo = "%$search%";
        $params = [$termo, $termo, $termo];
    }

    // SELECT Final com Coluna Virtual
    // Note: 'peso_ordenacao' é criado aqui para o ORDER BY usar
    $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol, 
            $colunaPeso as peso_ordenacao
            FROM $tabela $where 
            $orderBy 
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $params[] = $start;
    $params[] = $length;

    $stmt = sqlsrv_query($connSenior, $sql, $params);
    if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));

    // 5. Totais
    $sqlTotal = "SELECT COUNT(*) as T FROM $tabela WHERE $filtroStatus";
    $tTotal = sqlsrv_fetch_array(sqlsrv_query($connSenior, $sqlTotal), SQLSRV_FETCH_ASSOC)['T'];
    
    $tFiltrado = $tTotal;
    if ($search) {
        $sqlFilt = "SELECT COUNT(*) as T FROM $tabela $where"; // Params já definidos exceto offset
        // Precisamos recriar params do count (apenas busca)
        $paramsCount = [$termo, $termo, $termo]; 
        $tFiltrado = sqlsrv_fetch_array(sqlsrv_query($connSenior, $sqlFilt, $paramsCount), SQLSRV_FETCH_ASSOC)['T'];
    }

    // 6. Formatar Dados
    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $n = (int)$row['numsol'];
        $s = (int)$row['seqsol'];
        $chave = "$n-$s";
        $peso = (int)$row['peso_ordenacao'];
        
        // Traduz peso numérico para string de status
        $status = 'disponivel';
        $bloqueador = '';
        
        if ($peso === 2) {
            $status = 'vinculado';
        } elseif ($peso === 1) {
            $status = 'bloqueado';
            $bloqueador = $mapaDonos[$chave] ?? '?';
        }

        $dt = ($row['datsol'] instanceof DateTime) ? $row['datsol']->format('Y-m-d') : null;

        $data[] = [
            'id_unico' => $row['codemp'] . '-' . $n . '-' . $s,
            'projeto' => trim((string)$row['numprj']),
            'data_solicitacao' => $dt,
            'id_solicitacao_senior' => "$n-$s",
            'descricao_produto' => $row['cplpro'],
            'quantidade' => (float)$row['qtdsol'],
            'preco_unitario' => (float)$row['presol'],
            'unidade' => trim($row['unimed']),
            'status' => $status,
            'proc_bloqueador' => $bloqueador
        ];
    }

    ob_end_clean();
    echo json_encode(utf8_clean([
        "draw" => $draw,
        "recordsTotal" => $tTotal,
        "recordsFiltered" => $tFiltrado,
        "data" => $data
    ]));

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["error" => $e->getMessage()]);
}
?>