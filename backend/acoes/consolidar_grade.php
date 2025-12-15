<?php
// backend/acoes/consolidar_grade.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require '../db_conexao.php';

// NÃO precisamos mais do Senior aqui. O sistema fica autônomo (Offline-first).
// require '../db_senior.php'; 

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'consolidar_vencedores') {
        $idProcesso = $_POST['id_processo'];
        if (!$idProcesso) throw new Exception("ID inválido.");

        // 1. REFAZ O CÁLCULO (Usando apenas dados LOCAIS)
        // -------------------------------------------------------
        
        // Busca Itens e suas Quantidades gravadas localmente
        // OBS: Assume que a coluna 'quantidade' existe em processos_itens
        $stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id_processo_instancia = ?");
        $stmtI->execute([$idProcesso]);
        $listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        // Busca Preços Ofertados
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
            
            // Pega a quantidade local. Se estiver zerada/nula, assume 1 para não zerar o cálculo
            $qtd = (float)($item['quantidade'] ?? 1);
            if ($qtd <= 0) $qtd = 1;

            // Acha o Menor Preço para este item
            $valoresItem = $precos[$chave] ?? [];
            $validos = array_filter($valoresItem, function($v) { return $v > 0; });
            
            if (!empty($validos)) {
                $menor = min($validos);
                $totalGeral += ($menor * $qtd);
            }
        }

        // 2. GRAVA A FOTOGRAFIA DO VALOR
        // -------------------------------------------------------
        $stmtUp = $pdo->prepare("UPDATE processos_instancia SET valor_final_processo = ? WHERE id = ?");
        $stmtUp->execute([$totalGeral, $idProcesso]);

        ob_clean();
        echo json_encode([
            'sucesso' => true, 
            'msg' => 'Valores consolidados (Offline) com sucesso!', 
            'valor_gravado' => number_format($totalGeral, 2, ',', '.')
        ]);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>