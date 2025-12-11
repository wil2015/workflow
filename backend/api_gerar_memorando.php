<?php
// backend/api_gerar_memorando.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

try {
    if (!$idProcesso) throw new Exception("ID inválido.");

    // 1. CARREGA DADOS BÁSICOS
    // ----------------------------------------
    $mapForn = [];
    $stmtF = $pdo->prepare("SELECT id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf FROM licitacao_participantes WHERE id_processo_instancia = ?");
    $stmtF->execute([$idProcesso]);
    while ($r = $stmtF->fetch(PDO::FETCH_ASSOC)) $mapForn[$r['id_fornecedor_senior']] = $r;

    // Preços Vencedores
    $precos = [];
    $stmtP = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
    $stmtP->execute([$idProcesso]);
    while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $chave = $r['num_solicitacao'].'-'.$r['seq_solicitacao'];
        $precos[$chave][$r['id_fornecedor_senior']] = (float)$r['valor_unitario'];
    }

    // 2. VERIFICA ITENS JÁ PROCESSADOS (Checklist)
    // ----------------------------------------
    // Se o item existe na tabela de memorandos, consideramos ele "Entregue/Pago"
    $itensConcluidos = [];
    $stmtM = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao 
                            FROM memorandos_oc_itens 
                            WHERE id_memorando IN (SELECT id FROM memorandos_oc WHERE id_processo_instancia = ?)");
    $stmtM->execute([$idProcesso]);
    while ($r = $stmtM->fetch(PDO::FETCH_ASSOC)) {
        $chave = $r['num_solicitacao'].'-'.$r['seq_solicitacao'];
        $itensConcluidos[$chave] = true;
    }

    // 3. MONTA A LISTA
    // ----------------------------------------
    $stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ?");
    $stmtI->execute([$idProcesso]);
    $listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    $pendencias = [];

    foreach ($listaItens as $i) {
        $num = $i['num_solicitacao'];
        $seq = $i['seq_solicitacao'];
        $chave = "$num-$seq";

        // Dados Senior
        $nomeProd = "Item $chave";
        $qtdTotal = 0;
        $unid = 'UN';
        
        if ($connSenior) {
            $qS = sqlsrv_query($connSenior, "SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?", [$num, $seq]);
            if ($qS && $rS = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
                $nomeProd = utf8_encode($rS['cplpro']);
                $qtdTotal = (float)$rS['qtdsol'];
                $unid = $rS['unimed'];
            }
        }

        // Quem ganhou?
        $menorPreco = null;
        $vencedorId = null;
        foreach ($mapForn as $idF => $d) {
            $val = $precos[$chave][$idF] ?? 0;
            if ($val > 0) {
                if ($menorPreco === null || $val < $menorPreco) {
                    $menorPreco = $val;
                    $vencedorId = $idF;
                }
            }
        }

        if ($vencedorId) {
            // LÓGICA SIMPLIFICADA:
            // Está na lista de concluídos? Sim = Status Pago. Não = Status Pendente.
            $jaFoiPago = isset($itensConcluidos[$chave]);

            // Se já foi pago, ainda mostramos na lista (como informativo), mas bloqueado
            // Ou se preferir esconder, basta adicionar um `if (!$jaFoiPago)` aqui.
            // Vou manter mostrando para histórico.

            if (!isset($pendencias[$vencedorId])) {
                $pendencias[$vencedorId] = [
                    'fornecedor' => $mapForn[$vencedorId],
                    'itens' => []
                ];
            }

            $pendencias[$vencedorId]['itens'][] = [
                'num' => $num,
                'seq' => $seq,
                'produto' => $nomeProd,
                'unid' => $unid,
                'qtd' => $qtdTotal, // Qtd sempre cheia
                'preco' => $menorPreco,
                'total' => $qtdTotal * $menorPreco,
                'status' => $jaFoiPago ? 'CONCLUIDO' : 'PENDENTE'
            ];
        }
    }

    echo json_encode(array_values($pendencias));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>