<?php
// backend/api_grade_comparativa.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

try {
    if (!$idProcesso) throw new Exception("ID inválido");

    // 1. PARTICIPANTES (Colunas)
    $stmtF = $pdo->prepare("SELECT id_fornecedor_senior, nome_do_fornecedor 
                            FROM licitacao_participantes 
                            WHERE id_processo_instancia = ? 
                            ORDER BY id_fornecedor_senior");
    $stmtF->execute([$idProcesso]);
    $fornecedoresRaw = $stmtF->fetchAll(PDO::FETCH_ASSOC);

    // Formata nomes para o cabeçalho
    $cabecalho = [];
    foreach ($fornecedoresRaw as $f) {
        $nome = trim($f['nome_do_fornecedor']);
        if (empty($nome)) $nome = "Forn. " . $f['id_fornecedor_senior'];
        
        $cabecalho[] = [
            'id' => $f['id_fornecedor_senior'],
            'nome_curto' => mb_strimwidth($nome, 0, 20, "..."),
            'nome_completo' => $nome
        ];
    }

    // 2. ITENS (Linhas)
    $stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
    $stmtI->execute([$idProcesso]);
    $listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // 3. PREÇOS (Cruzamento)
    $precos = [];
    $stmtP = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                            FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
    $stmtP->execute([$idProcesso]);
    while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        $precos[$chave][$row['id_fornecedor_senior']] = (float)$row['valor_unitario'];
    }

    // 4. PROCESSAMENTO (Regra de Negócio)
    $linhas = [];
    $totalGeral = 0;

    foreach ($listaItens as $i) {
        $num = $i['num_solicitacao'];
        $seq = $i['seq_solicitacao'];
        $chave = "$num-$seq";

        // Busca Senior (Dados do Produto)
        $nomeProd = "Item $chave";
        $qtd = 0;
        
        if ($connSenior) {
            $sqlS = "SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
            $qS = sqlsrv_query($connSenior, $sqlS, [$num, $seq]);
            if ($qS && $rS = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
                $nomeProd = utf8_encode($rS['cplpro']);
                $qtd = (float)$rS['qtdsol'];
            }
        }

        // Calcula Menor Preço
        $menorPreco = null;
        foreach ($cabecalho as $f) {
            $val = $precos[$chave][$f['id']] ?? 0;
            if ($val > 0) {
                if ($menorPreco === null || $val < $menorPreco) $menorPreco = $val;
            }
        }

        // Monta as células dos fornecedores
        $celulas = [];
        $vencedores = [];
        
        foreach ($cabecalho as $f) {
            $idForn = $f['id'];
            $val = $precos[$chave][$idForn] ?? 0;
            
            $status = 'empty'; // empty, winner, tie, loser
            $textoVal = '-';

            if ($val > 0) {
                $textoVal = "R$ " . number_format($val, 2, ',', '.');
                
                // Verifica se venceu
                if ($menorPreco !== null && abs($val - $menorPreco) < 0.001) {
                    $vencedores[] = $idForn;
                    $status = 'winner'; // Provisório, depois checamos empate
                } else {
                    $status = 'loser';
                }
            }

            $celulas[$idForn] = [
                'valor_fmt' => $textoVal,
                'status' => $status
            ];
        }

        // Ajuste de Empate
        if (count($vencedores) > 1) {
            foreach ($vencedores as $idVenc) {
                $celulas[$idVenc]['status'] = 'tie';
            }
        }

        // Soma Total
        if ($menorPreco) $totalGeral += ($menorPreco * $qtd);

        $linhas[] = [
            'chave' => $chave,
            'produto' => $nomeProd,
            'qtd_fmt' => number_format($qtd, 2, ',', '.'),
            'melhor_fmt' => $menorPreco ? "R$ " . number_format($menorPreco, 2, ',', '.') : '-',
            'celulas' => $celulas
        ];
    }

    echo json_encode([
        'cabecalho' => $cabecalho,
        'linhas' => $linhas,
        'total_fmt' => number_format($totalGeral, 2, ',', '.')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>