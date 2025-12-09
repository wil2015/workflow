<?php
// backend/api_solicitacoes.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

// --- RECEBE PARÂMETROS DO DATATABLES ---
$instance_id = $_GET['instance_id'] ?? 0; // ID do Processo Atual
$start       = $_GET['start'] ?? 0;
$length      = $_GET['length'] ?? 10;
$search      = $_GET['search']['value'] ?? '';
$draw        = $_GET['draw'] ?? 1;

// Parâmetros de Ordenação enviados pelo JS
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1; // Padrão: Coluna 1 (Projeto)
$orderDir         = $_GET['order'][0]['dir'] ?? 'desc'; // Padrão: DESC

// Mapeamento: Índice da Coluna (JS) => Nome do Campo (SQL Server)
$columnsMap = [
    0 => 'numsol', // Ação (Usamos numsol apenas como fallback)
    1 => 'numprj', // Projeto
    2 => 'datsol', // Data
    3 => 'numsol', // Solicitação
    4 => 'cplpro', // Produto
    5 => 'presol', // Preço
    6 => 'numsol'  // Status
];

$colunaOrdenacao = $columnsMap[$orderColumnIndex] ?? 'numprj';
// Proteção contra SQL Injection na direção
$orderDir = (strtoupper($orderDir) === 'ASC') ? 'ASC' : 'DESC';

try {
    // 1. MAPEAMENTO DE VÍNCULOS (MySQL)
    // Descobre (A) Itens deste processo (para jogar pro topo) e (B) Itens de outros (para bloquear)
    $meusItensStr = ""; 
    $itensOutros = [];
    
    $stmt = $pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        
        if ($instance_id && $row['id_processo_instancia'] == $instance_id) {
            // Monta string para o SQL Server CASE: (numsol=X AND seqsol=Y)
            $n = $row['num_solicitacao'];
            $s = $row['seq_solicitacao'];
            $meusItensStr .= "(numsol=$n AND seqsol=$s) OR ";
        } else {
            // Guarda para bloquear checkbox
            $itensOutros[$chave] = $row['id_processo_instancia'];
        }
    }
    
    // Remove o último " OR "
    $meusItensStr = rtrim($meusItensStr, " OR ");

    // 2. QUERY SENIOR
    $colunas = "codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol";
    $tabela  = "Sapiens.sapiens.e405sol";
    $condicao = "WHERE sitsol IN (1, 2)";

    $paramsSenior = [];
    if (!empty($search)) {
        $condicao .= " AND (cplpro LIKE ? OR CAST(numsol AS VARCHAR) LIKE ? OR numprj LIKE ?)";
        $term = "%$search%";
        $paramsSenior = [$term, $term, $term];
    }

    // 3. CONSTRUÇÃO DO ORDER BY (O Segredo!)
    // Prioridade 1: Itens deste processo (CASE WHEN ...)
    // Prioridade 2: Ordenação clicada pelo usuário
    $sqlOrder = "ORDER BY ";
    
    if (!empty($meusItensStr)) {
        // Se é meu item = 0 (topo), senão = 1
        $sqlOrder .= "CASE WHEN $meusItensStr THEN 0 ELSE 1 END ASC, ";
    }
    
    $sqlOrder .= "$colunaOrdenacao $orderDir";

    // 4. EXECUÇÃO
    $totalRecords = 0;
    $totalFiltered = 0;
    $data = [];

    if ($connSenior) {
        // Count Total
        $stmtC = sqlsrv_query($connSenior, "SELECT COUNT(*) as total FROM $tabela WHERE sitsol IN (1, 2)");
        $rowC = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC);
        $totalRecords = $rowC['total'];

        // Count Filtered
        if (!empty($search)) {
            $stmtCF = sqlsrv_query($connSenior, "SELECT COUNT(*) as total FROM $tabela $condicao", $paramsSenior);
            $rowCF = sqlsrv_fetch_array($stmtCF, SQLSRV_FETCH_ASSOC);
            $totalFiltered = $rowCF['total'];
        } else {
            $totalFiltered = $totalRecords;
        }

        // Query Final com Paginação
        // SQL Server 2012+ usa OFFSET/FETCH
        $sqlFinal = "SELECT $colunas FROM $tabela $condicao 
                     $sqlOrder 
                     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        $paramsSenior[] = (int)$start;
        $paramsSenior[] = (int)$length;

        $stmtD = sqlsrv_query($connSenior, $sqlFinal, $paramsSenior);
        
        if ($stmtD) {
            while ($sol = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
                $numsol = $sol['numsol'];
                $seqsol = $sol['seqsol'];
                $chaveCheck = $numsol . '-' . $seqsol;
                
                // Lógica de Vínculo Visual
                // Se o SQL jogou pro topo pelo CASE, podemos checar se está na string ou revalidar
                // Mas aqui usamos a lógica padrão de comparação
                // Verifica se está na lista de "Outros"
                $idOutro = $itensOutros[$chaveCheck] ?? null;
                
                // Verifica se é "Meu" (não está em 'outros', mas precisamos saber se já salvei)
                // A melhor forma é checar se veio na query de 'meusItensStr' ou fazer uma query rapida
                // Simplificação: Se não está em 'Outros', e o botão de remover aparecer, é meu.
                // Reconstruindo a lógica exata:
                $vinculadoAqui = false;
                // Verificação bruta na string (rápida para exibição)
                if (!empty($meusItensStr) && strpos($meusItensStr, "numsol=$numsol AND seqsol=$seqsol") !== false) {
                    $vinculadoAqui = true;
                }

                $chaveEnvio = $sol['codemp'] . '-' . $numsol . '-' . $seqsol;

                // Formatações
                $dataFmt = '';
                if (isset($sol['datsol']) && $sol['datsol'] instanceof DateTime) {
                    $dataFmt = $sol['datsol']->format('d/m/Y');
                }
                $precoFmt = number_format($sol['presol'] ?? 0, 2, ',', '.');
                $qtdFmt = number_format($sol['qtdsol'], 2, ',', '.') . ' ' . $sol['unimed'];
                $projeto = trim((string)$sol['numprj']);

                // HTML Coluna Ação
                if ($vinculadoAqui) {
                    $acao = "<button type='button' class='btn-rm-item js-rm-item' data-num='$numsol' data-seq='$seqsol' data-chave='$chaveEnvio'>&times;</button>";
                    $st = "<span class='status-ok'>Vinculado</span>";
                } elseif ($idOutro) {
                    $acao = "<span title='Em Proc. #$idOutro' style='color:#ccc'>🔒</span>";
                    $st = "<span style='color:#999;font-size:10px'>Proc. #$idOutro</span>";
                } else {
                    $acao = "<input type='checkbox' name='selecionados[]' value='$chaveEnvio' class='chk-item-erp'>";
                    $st = "<span class='status-new'>Disponível</span>";
                }

                $data[] = [
                    "acao"    => $acao,
                    "projeto" => "<span class='proj-tag'>$projeto</span>",
                    "data"    => $dataFmt,
                    "sol"     => "<b>$numsol</b>-$seqsol",
                    "prod"    => utf8_encode($sol['cplpro']) . "<br><small style='color:#666'>Qtd: $qtdFmt</small>",
                    "preco"   => "R$ $precoFmt",
                    "status"  => $st
                ];
            }
        }
    }

    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data" => $data
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>