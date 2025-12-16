<?php
// backend/api_dispensa_resumo.php
header('Content-Type: application/json; charset=utf-8');
require 'db_conexao.php';

// Tenta incluir conexão com Senior para pegar o Nome do Fornecedor
@include 'db_senior.php'; 

$idProcesso = $_GET['instance_id'] ?? null;

try {
    if (!$idProcesso) throw new Exception("ID não informado.");

    // 1. DADOS GERAIS DO PROCESSO + VALOR JÁ CONSOLIDADO
    // Como a grade é padrão, o campo 'valor_final_processo' estará preenchido corretamente.
    $stmt = $pdo->prepare("SELECT id_processo_senior, valor_final_processo FROM processos_instancia WHERE id = ?");
    $stmt->execute([$idProcesso]);
    $proc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proc) throw new Exception("Processo não encontrado.");

    $valorTotal = (float)($proc['valor_final_processo'] ?? 0);

    // 2. DESCOBRIR O VENCEDOR (Consulta Leve)
    // Pegamos apenas o ID de quem tem itens com valor atribuído neste processo.
    // Assumindo que na dispensa geralmente há um vencedor principal.
    $idVencedor = 0;
    $nomeVencedor = 'Fornecedor não identificado';

    $stmtV = $pdo->prepare("SELECT DISTINCT id_fornecedor_senior 
                            FROM licitacao_itens_ofertados 
                            WHERE id_processo_instancia = ? AND valor_unitario > 0 
                            LIMIT 1");
    $stmtV->execute([$idProcesso]);
    $rowV = $stmtV->fetch(PDO::FETCH_ASSOC);

    if ($rowV) {
        $idVencedor = $rowV['id_fornecedor_senior'];
        $nomeVencedor = "Fornecedor Cód. " . $idVencedor; // Nome provisório

        // 3. BUSCA O NOME REAL NO SENIOR (Se disponível)
        if (isset($connSenior) && $connSenior) {
            // Ajuste a tabela (E095FOR) conforme seu ambiente Senior
            $sqlSenior = "SELECT nomfor FROM Sapiens.sapiens.e095for WHERE codfor = ?";
            $qS = sqlsrv_query($connSenior, $sqlSenior, [$idVencedor]);
            
            if ($qS && $rowS = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
                $nomeVencedor = $rowS['nomfor']; // Razão Social Oficial
            }
        }
    }

    echo json_encode([
        'sucesso' => true,
        'dados' => [
            'processo_senior' => $proc['id_processo_senior'],
            'valor_total' => $valorTotal,
            'valor_fmt' => number_format($valorTotal, 2, ',', '.'),
            'fornecedor_id' => $idVencedor,
            'fornecedor_nome' => $nomeVencedor,
            'ano' => date('Y')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>