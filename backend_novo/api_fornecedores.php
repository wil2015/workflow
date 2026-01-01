<?php
// backend/api_fornecedores.php
// Limpa buffers para evitar sujeira no JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db_conexao.php'; // MySQL ($pdo)
require 'db_senior.php';  // SQL Server ($connSenior)

try {
    // Parâmetros do DataTables
    $instance_id = (int)($_GET['instance_id'] ?? 0);
    $start       = (int)($_GET['start'] ?? 0);
    $length      = (int)($_GET['length'] ?? 10);
    $search      = $_GET['search']['value'] ?? '';
    $draw        = (int)($_GET['draw'] ?? 1);

    // ---------------------------------------------------------
    // PASSO 1: Descobrir IDs já selecionados no MySQL
    // ---------------------------------------------------------
    $idsVinculados = [];
    if ($instance_id) {
        $stmt = $pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
        $stmt->execute([$instance_id]);
        // Cria um array simples: [1050, 2030, 4050...]
        $idsVinculados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ---------------------------------------------------------
    // PASSO 2: Montar Query no SQL Server (Senior)
    // ---------------------------------------------------------
    $colunas = "codfor, nomfor, cgccpf, sigufs, cidfor";
    $tabela  = "Sapiens.sapiens.e095for";
    $condicao = "WHERE sitfor = 'A'"; // Apenas ativos
    $params = [];

    // Filtro de Busca
    if (!empty($search)) {
        $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
    }

    // ---------------------------------------------------------
    // PASSO 3: Ordenação Inteligente (Vinculados no Topo)
    // ---------------------------------------------------------
    $orderBy = "";
    
    // Se tiver itens vinculados, forçamos eles para cima usando CASE WHEN
    if (!empty($idsVinculados)) {
        $listaIds = implode(',', array_map('intval', $idsVinculados));
        // Lógica: Se o ID estiver na lista, ganha peso 1 (Topo), senão peso 0 (Fundo)
        $orderBy = "CASE WHEN codfor IN ($listaIds) THEN 1 ELSE 0 END DESC, ";
    }
    
    // Ordenação secundária alfabética
    $orderBy .= "nomfor ASC";

    // ---------------------------------------------------------
    // PASSO 4: Contagens e Execução
    // ---------------------------------------------------------
    
    // A. Total Geral (Sem filtro)
    $sqlTotal = "SELECT COUNT(*) as T FROM $tabela WHERE sitfor = 'A'";
    $resT = sqlsrv_fetch_array(sqlsrv_query($connSenior, $sqlTotal), SQLSRV_FETCH_ASSOC);
    $totalRecords = $resT['T'];

    // B. Total Filtrado
    if (!empty($search)) {
        $sqlFilt = "SELECT COUNT(*) as T FROM $tabela $condicao";
        $stmtFilt = sqlsrv_query($connSenior, $sqlFilt, $params);
        $resF = sqlsrv_fetch_array($stmtFilt, SQLSRV_FETCH_ASSOC);
        $totalFiltered = $resF['T'];
    } else {
        $totalFiltered = $totalRecords;
    }

    // C. Busca Real Paginada
    // SQL Server 2012+ usa OFFSET/FETCH
    $sqlDados = "SELECT $colunas FROM $tabela $condicao 
                 ORDER BY $orderBy 
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    
    // Adiciona paginação aos parâmetros
    $params[] = $start;
    $params[] = $length;

    $stmt = sqlsrv_query($connSenior, $sqlDados, $params);
    
    if ($stmt === false) {
        throw new Exception("Erro na consulta Senior: " . print_r(sqlsrv_errors(), true));
    }

    // ---------------------------------------------------------
    // PASSO 5: Formatar JSON
    // ---------------------------------------------------------
    $data = [];
    // Transforma a lista de IDs em chave-valor para busca rápida no loop: [1050 => true, ...]
    $mapaVinculados = array_fill_keys($idsVinculados, true);

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cod = (int)$row['codfor'];
        
        // Verifica se está no mapa
        $isLinked = isset($mapaVinculados[$cod]);
        
        // Tratamento UTF-8
        $nome = utf8_encode($row['nomfor']);
        $cidade = utf8_encode($row['cidfor']);
        
        $objParaSalvar = [
            'cod' => $cod,
            'nome' => $nome,
            'doc' => trim($row['cgccpf'])
        ];

        $data[] = [
            'cod' => $cod,
            'nome' => $nome,
            'doc' => trim($row['cgccpf']),
            'cidade_uf' => $cidade . ' - ' . $row['sigufs'],
            'vinculado' => $isLinked, 
            'json_full' => json_encode($objParaSalvar) 
        ];
    }

    ob_end_clean();
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ]);

} catch (Exception $e) {
    ob_end_clean();
    // Retorna erro formatado para o DataTables não quebrar feio
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => $e->getMessage()
    ]);
}
?>