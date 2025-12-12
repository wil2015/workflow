<?php
// backend/acoes/consolidar_grade.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';
require '../db_senior.php'; // Se tiver conexão externa

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'consolidar_vencedores') {
        $idProcesso = $_POST['id_processo'];
        if (!$idProcesso) throw new Exception("ID inválido.");

        // 1. REFAZ O CÁLCULO (Para garantir segurança no Backend)
        // -------------------------------------------------------
        // Busca Itens
        $stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ?");
        $stmtI->execute([$idProcesso]);
        $listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        // Busca Preços
        $precos = [];
        $stmtP = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, valor_unitario FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
        $stmtP->execute([$idProcesso]);
        while ($r = $stmtP->fetch(PDO::FETCH_ASSOC)) {
            $chave = $r['num_solicitacao'].'-'.$r['seq_solicitacao'];
            $precos[$chave][] = (float)$r['valor_unitario'];
        }

        $totalGeral = 0;

        foreach ($listaItens as $item) {
            $chave = $item['num_solicitacao'].'-'.$item['seq_solicitacao'];
            
            // Busca Qtd (Senior ou Padrão)
            $qtd = 1;
            if ($connSenior) {
                $qS = sqlsrv_query($connSenior, "SELECT qtdsol FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?", [$item['num_solicitacao'], $item['seq_solicitacao']]);
                if ($qS && $rowS = sqlsrv_fetch_array($qS)) $qtd = (float)$rowS['qtdsol'];
            }

            // Acha o Menor Preço
            $valoresItem = $precos[$chave] ?? [];
            $validos = array_filter($valoresItem, function($v) { return $v > 0; });
            
            if (!empty($validos)) {
                $menor = min($validos);
                $totalGeral += ($menor * $qtd);
            }
        }

        // 2. GRAVA O VALOR NO PROCESSO (A "FOTOGRAFIA")
        // -------------------------------------------------------
        $stmtUp = $pdo->prepare("UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?");
        $stmtUp->execute([$totalGeral, $idProcesso]);

        ob_clean();
        echo json_encode([
            'sucesso' => true, 
            'msg' => 'Valores consolidados com sucesso!', 
            'valor_gravado' => number_format($totalGeral, 2, ',', '.')
        ]);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>