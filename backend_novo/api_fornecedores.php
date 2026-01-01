<?php
// backend_novo/api_fornecedores.php
// Limpa qualquer lixo de buffer anterior
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db_conexao.php';
require 'db_senior.php';

// Função auxiliar UTF-8
function utf8_clean($arr) {
    array_walk_recursive($arr, function(&$val) {
        if (is_string($val) && !mb_detect_encoding($val, 'utf-8', true)) $val = utf8_encode($val);
    });
    return $arr;
}

try {
    $instance_id = (int)($_GET['instance_id'] ?? 0);
    $start       = (int)($_GET['start'] ?? 0);
    $length      = (int)($_GET['length'] ?? 10);
    $search      = $_GET['search']['value'] ?? '';
    $draw        = (int)($_GET['draw'] ?? 1);

    // 1. Mapeia quem já está vinculado (MySQL)
    $vinculados = [];
    if ($instance_id) {
        $stmt = $pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$instance_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vinculados[$row['id_fornecedor_senior']] = true;
        }
    }

    // 2. Query Principal (Senior)
    // Filtramos apenas fornecedores ativos (sitfor = A)
    $colunas = "codfor, nomfor, cgccpf, sigufs, cidfor";
    $tabela  = "Sapiens.sapiens.e095for";
    $condicao = "WHERE sitfor = 'A'";
    $params = [];

    if (!empty($search)) {
        // Busca por Nome, CPF/CNPJ ou Código
        $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR(20)) LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
    }

    // 3. Totais (Para paginação correta)
    $totalRecords = 0;
    $totalFiltered = 0;
    
    // Contagem Total
    $sqlTotal = "SELECT COUNT(*) as T FROM $tabela WHERE sitfor = 'A'";
    $resT = sqlsrv_fetch_array(sqlsrv_query($connSenior, $sqlTotal), SQLSRV_FETCH_ASSOC);
    $totalRecords = $resT['T'];

    // Contagem Filtrada
    if (!empty($search)) {
        $sqlFilt = "SELECT COUNT(*) as T FROM $tabela $condicao";
        $resF = sqlsrv_fetch_array(sqlsrv_query($connSenior, $sqlFilt, $params), SQLSRV_FETCH_ASSOC);
        $totalFiltered = $resF['T'];
    } else {
        $totalFiltered = $totalRecords;
    }

    // 4. Busca Paginada
    // Ordenação fixa por Nome (nomfor) para simplificar
    $sqlDados = "SELECT $colunas FROM $tabela $condicao 
                 ORDER BY nomfor ASC 
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    $params[] = $start;
    $params[] = $length;

    $stmt = sqlsrv_query($connSenior, $sqlDados, $params);
    if ($stmt === false) throw new Exception("Erro SQL Senior");

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cod = (int)$row['codfor'];
        $isLinked = isset($vinculados[$cod]);
        
        // Prepara objeto JSON para o Frontend usar no POST
        // Importante: utf8_encode aqui para garantir que o JSON do JS fique correto
        $objParaSalvar = [
            'cod' => $cod,
            'nome' => utf8_encode($row['nomfor']),
            'doc' => trim($row['cgccpf'])
        ];

        $data[] = [
            'cod' => $cod,
            'nome' => utf8_encode($row['nomfor']),
            'doc' => trim($row['cgccpf']),
            'cidade_uf' => utf8_encode($row['cidfor']) . ' - ' . $row['sigufs'],
            'vinculado' => $isLinked, // Booleano simples para o Vue
            'json_full' => json_encode($objParaSalvar) // JSON string pronto para enviar de volta
        ];
    }

    ob_end_clean();
    echo json_encode(utf8_clean([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ]));

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["error" => $e->getMessage()]);
}
?>