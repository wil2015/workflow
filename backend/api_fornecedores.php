<?php
// backend/api_fornecedores.php
// API Server-Side para DataTables

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

$instance_id = $_GET['instance_id'] ?? 0;
$start       = $_GET['start'] ?? 0;
$length      = $_GET['length'] ?? 10;
$search      = $_GET['search']['value'] ?? '';
$draw        = $_GET['draw'] ?? 1;

try {
    // 1. Mapeia Vínculos (MySQL)
    $vinculados = [];
    $stmt = $pdo->prepare("SELECT id_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
    $stmt->execute([$instance_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vinculados[$row['id_fornecedor_senior']] = true;
    }

    // 2. Query Senior
    $colunas = "codfor, nomfor, cgccpf, sigufs, cidfor";
    $tabela  = "Sapiens.sapiens.e095for";
    $condicao = "WHERE sitfor = 'A'";

    $paramsSenior = [];
    if (!empty($search)) {
        $condicao .= " AND (nomfor LIKE ? OR cgccpf LIKE ? OR CAST(codfor AS VARCHAR) LIKE ?)";
        $term = "%$search%";
        $paramsSenior = [$term, $term, $term];
    }

    // 3. Totais
    $totalRecords = 0;
    $totalFiltered = 0;
    
    if ($connSenior) {
        $sqlCount = "SELECT COUNT(*) as total FROM $tabela WHERE sitfor = 'A'";
        $stmtC = sqlsrv_query($connSenior, $sqlCount);
        if ($stmtC) {
            $rowC = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC);
            $totalRecords = $rowC['total'];
        }

        if (!empty($search)) {
            $sqlCountFilter = "SELECT COUNT(*) as total FROM $tabela $condicao";
            $stmtCF = sqlsrv_query($connSenior, $sqlCountFilter, $paramsSenior);
            if ($stmtCF) {
                $rowCF = sqlsrv_fetch_array($stmtCF, SQLSRV_FETCH_ASSOC);
                $totalFiltered = $rowCF['total'];
            }
        } else {
            $totalFiltered = $totalRecords;
        }

        // 4. Dados
        $sqlDados = "SELECT $colunas FROM $tabela $condicao 
                     ORDER BY nomfor ASC 
                     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        $paramsSenior[] = (int)$start;
        $paramsSenior[] = (int)$length;

        $stmtD = sqlsrv_query($connSenior, $sqlDados, $paramsSenior);
        
        $data = [];
        if ($stmtD) {
            while ($row = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
                $cod = $row['codfor'];
                $jaTem = isset($vinculados[$cod]);
                
                // Limpeza de encoding para o JSON
                $nome = utf8_encode($row['nomfor']);
                $cidade = utf8_encode($row['cidfor']);
                
                // JSON payload para salvar
                $dadosJson = htmlspecialchars(json_encode([
                    'cod' => $cod,
                    'nome' => $nome,
                    'doc' => $row['cgccpf']
                ]), ENT_QUOTES, 'UTF-8');

                // --- MUDANÇA AQUI ---
                // Sempre desenha checkbox. Se já tem, adiciona 'checked'.
                $checkedStr = $jaTem ? 'checked' : '';
                
                // Adicionamos data-cod para facilitar a remoção
                $acaoHtml = "<input type='checkbox' 
                                    class='chk-forn' 
                                    value='$dadosJson' 
                                    data-cod='$cod' 
                                    $checkedStr 
                                    style='transform: scale(1.2); cursor: pointer;'>";

                if ($jaTem) {
                    $statusHtml = "<span class='badge-ok'>Selecionado</span>";
                } else {
                    $statusHtml = "<span class='badge-dispo'>Disponível</span>";
                }

                $data[] = [
                    "acao"   => $acaoHtml,
                    "nome"   => "<b>$nome</b><br><small style='color:#888'>Cód: $cod</small>",
                    "doc"    => $row['cgccpf'],
                    "cidade" => "$cidade - " . $row['sigufs'],
                    "status" => $statusHtml
                ];
            }
        }
    } else {
        $data = [];
    }

    echo json_encode([
        "draw"            => intval($draw),
        "recordsTotal"    => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data"            => $data
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>