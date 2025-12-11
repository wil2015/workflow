<?php
// backend/api_autorizacao.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
require 'db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

try {
    if (!$idProcesso) throw new Exception("ID inválido.");

    // 1. CARREGA DADOS + HISTÓRICO DE ENVIO
    // A. Fornecedores com LEFT JOIN no histórico para saber se já enviou
    $mapFornecedores = [];
    $sqlF = "SELECT 
                p.id_fornecedor_senior, 
                p.nome_do_fornecedor, 
                p.cnpj_cpf,
                (SELECT MAX(data_envio) FROM historico_autorizacoes h 
                 WHERE h.id_processo_instancia = p.id_processo_instancia 
                 AND h.id_fornecedor_senior = p.id_fornecedor_senior) as data_ultimo_envio
             FROM licitacao_participantes p 
             WHERE p.id_processo_instancia = ?";
             
    $stmtF = $pdo->prepare($sqlF);
    $stmtF->execute([$idProcesso]);
    while ($row = $stmtF->fetch(PDO::FETCH_ASSOC)) {
        $mapFornecedores[$row['id_fornecedor_senior']] = $row;
    }

    // B. Itens
    $stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ?");
    $stmtI->execute([$idProcesso]);
    $listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // C. Preços
    $precos = [];
    $stmtP = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                            FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
    $stmtP->execute([$idProcesso]);
    while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        $precos[$chave][$row['id_fornecedor_senior']] = (float)$row['valor_unitario'];
    }

    // 2. PROCESSAMENTO (Vencedores)
    $ordensDeCompra = [];

    foreach ($listaItens as $item) {
        $num = $item['num_solicitacao'];
        $seq = $item['seq_solicitacao'];
        $chave = "$num-$seq";

        $nomeProduto = "Item $chave";
        $qtd = 0; $unid = 'UN';
        
        if ($connSenior) {
            $qS = sqlsrv_query($connSenior, "SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?", [$num, $seq]);
            if ($qS && $rS = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
                $nomeProduto = utf8_encode($rS['cplpro']);
                $qtd = (float)$rS['qtdsol'];
                $unid = $rS['unimed'];
            }
        }

        $menorPreco = null;
        $vencedorId = null;

        foreach ($mapFornecedores as $idForn => $dadosForn) {
            $valor = $precos[$chave][$idForn] ?? 0;
            if ($valor > 0) {
                if ($menorPreco === null || $valor < $menorPreco) {
                    $menorPreco = $valor;
                    $vencedorId = $idForn;
                }
            }
        }

        if ($vencedorId !== null) {
            $totalItem = $menorPreco * $qtd;
            
            if (!isset($ordensDeCompra[$vencedorId])) {
                // Formata Data de Envio se existir
                $dataEnvioFmt = null;
                if ($mapFornecedores[$vencedorId]['data_ultimo_envio']) {
                    $dt = new DateTime($mapFornecedores[$vencedorId]['data_ultimo_envio']);
                    $dataEnvioFmt = $dt->format('d/m/Y H:i');
                }

                $ordensDeCompra[$vencedorId] = [
                    'id_fornecedor' => $vencedorId,
                    'razao_social' => $mapFornecedores[$vencedorId]['nome_do_fornecedor'],
                    'doc' => $mapFornecedores[$vencedorId]['cnpj_cpf'],
                    'data_envio' => $dataEnvioFmt, // Informação Nova
                    'total_geral' => 0,
                    'itens' => []
                ];
            }

            $ordensDeCompra[$vencedorId]['itens'][] = [
                'solicitacao' => $chave,
                'produto' => $nomeProduto,
                'qtd_fmt' => number_format($qtd, 2, ',', '.') . ' ' . $unid,
                'preco_fmt' => "R$ " . number_format($menorPreco, 2, ',', '.'),
                'total_fmt' => "R$ " . number_format($totalItem, 2, ',', '.')
            ];
            $ordensDeCompra[$vencedorId]['total_geral'] += $totalItem;
        }
    }

    foreach ($ordensDeCompra as &$oc) {
        $oc['total_geral_fmt'] = number_format($oc['total_geral'], 2, ',', '.');
    }

    echo json_encode(array_values($ordensDeCompra));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>