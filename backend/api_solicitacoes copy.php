<?php
// backend/api_solicitacoes.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

// Parâmetros do DataTables
$instance_id = $_GET['instance_id'] ?? 0; // ID do Processo Atual
$start       = $_GET['start'] ?? 0;
$length      = $_GET['length'] ?? 10;
$search      = $_GET['search']['value'] ?? '';
$draw        = $_GET['draw'] ?? 1;

try {
    // 1. MAPEAMENTO DE VÍNCULOS (MySQL)
    // Descobre quais itens já estão vinculados a processos
    // Retorna: Array [ "NUMSOL-SEQSOL" => ID_PROCESSO_DONO ]
    $itensVinculados = [];
    $stmt = $pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        $itensVinculados[$chave] = $row['id_processo_instancia'];
    }

    // 2. QUERY SENIOR (SQL Server)
    $colunas = "codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol";
    $tabela  = "Sapiens.sapiens.e405sol";
    
    // Filtro base: Apenas Situação 1 (Aberta) ou 2 (Em Cotação)
    $condicao = "WHERE sitsol IN (1, 2)";

    // Busca (Pesquisa Global)
    $paramsSenior = [];
    if (!empty($search)) {
        $condicao .= " AND (cplpro LIKE ? OR CAST(numsol AS VARCHAR) LIKE ? OR numprj LIKE ?)";
        $term = "%$search%";
        $paramsSenior = [$term, $term, $term];
    }

    // Contagem Total
    $totalRecords = 0;
    $totalFiltered = 0;
    
    if ($connSenior) {
        $sqlCount = "SELECT COUNT(*) as total FROM $tabela WHERE sitsol IN (1, 2)";
        $stmtC = sqlsrv_query($connSenior, $sqlCount);
        $rowC = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC);
        $totalRecords = $rowC['total'];

        if (!empty($search)) {
            $sqlCountFilter = "SELECT COUNT(*) as total FROM $tabela $condicao";
            $stmtCF = sqlsrv_query($connSenior, $sqlCountFilter, $paramsSenior);
            $rowCF = sqlsrv_fetch_array($stmtCF, SQLSRV_FETCH_ASSOC);
            $totalFiltered = $rowCF['total'];
        } else {
            $totalFiltered = $totalRecords;
        }

        // 3. QUERY DE DADOS (Com Paginação)
        // Ordenação Fixa: Projeto ASC, Data DESC, Solicitação DESC
        $sqlDados = "SELECT $colunas FROM $tabela $condicao 
                     ORDER BY numprj ASC, datsol DESC, numsol DESC, seqsol ASC
                     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        
        $paramsSenior[] = (int)$start;
        $paramsSenior[] = (int)$length;

        $stmtD = sqlsrv_query($connSenior, $sqlDados, $paramsSenior);
        
        $data = [];
        if ($stmtD) {
            while ($sol = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
                
                // Lógica de Vínculo
                $numsol = $sol['numsol'];
                $seqsol = $sol['seqsol'];
                $chaveCheck = $numsol . '-' . $seqsol;
                $idDono = $itensVinculados[$chaveCheck] ?? null;

                $vinculadoAqui = ($instance_id && $idDono == $instance_id);
                $vinculadoOutro = ($idDono && $idDono != $instance_id);
                $chaveEnvio = $sol['codemp'] . '-' . $numsol . '-' . $seqsol;

                // Formatações
                $dataFmt = '';
                if (isset($sol['datsol']) && $sol['datsol'] instanceof DateTime) {
                    $dataFmt = $sol['datsol']->format('d/m/Y');
                }
                $precoFmt = number_format($sol['presol'] ?? 0, 2, ',', '.');
                $qtdFmt = number_format($sol['qtdsol'], 2, ',', '.') . ' ' . $sol['unimed'];
                $projeto = trim((string)$sol['numprj']);

                // -- MONTAGEM DAS COLUNAS HTML --

                // Coluna 0: Ação (Checkbox ou Botão)
                if ($vinculadoAqui) {
                    $acao = "<button type='button' class='btn-rm-item js-rm-item' data-num='$numsol' data-seq='$seqsol' data-chave='$chaveEnvio'>&times;</button>";
                } elseif ($vinculadoOutro) {
                    $acao = "<span title='Em Proc. #$idDono' style='color:#ccc'>🔒</span>";
                } else {
                    $acao = "<input type='checkbox' name='selecionados[]' value='$chaveEnvio' class='chk-item-erp'>";
                }

                // Coluna 1: Projeto
                $colProj = "<span class='proj-tag'>$projeto</span>";

                // Coluna 4: Produto
                $colProd = utf8_encode($sol['cplpro']) . "<br><small style='color:#666'>Qtd: $qtdFmt</small>";

                // Coluna 6: Status
                if ($vinculadoAqui) $st = "<span class='status-ok'>Vinculado</span>";
                elseif ($vinculadoOutro) $st = "<span style='color:#999;font-size:10px'>Proc. #$idDono</span>";
                else $st = "<span class='status-new'>Disponível</span>";

                $data[] = [
                    "acao"    => $acao,
                    "projeto" => $colProj,
                    "data"    => $dataFmt,
                    "sol"     => "<b>$numsol</b>-$seqsol",
                    "prod"    => $colProd,
                    "preco"   => "R$ $precoFmt",
                    "status"  => $st
                ];
            }
        }
    } else {
        $data = []; // Fallback se não conectar
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