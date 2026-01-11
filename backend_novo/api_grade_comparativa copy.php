<?php
// backend_novo/api_grade_comparativa.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require 'db_conexao.php';
// require 'db_senior.php'; // Não necessário se tudo estiver no MySQL

try {
    $idProcesso = $_GET['instance_id'] ?? 0;
    if (!$idProcesso) throw new Exception("ID não informado");

    // 1. Busca Fornecedores (Cabeçalho)
    $stmt = $pdo->prepare("SELECT DISTINCT p.id_fornecedor_senior as id, p.nome_do_fornecedor as nome 
                           FROM licitacao_participantes p 
                           WHERE p.id_processo_instancia = ? 
                           ORDER BY p.nome_do_fornecedor");
    $stmt->execute([$idProcesso]);
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($fornecedores)) {
        echo json_encode(['cabecalho' => [], 'linhas' => [], 'total_fmt' => '0,00']);
        exit;
    }

    // Formata nomes para o cabeçalho
    foreach ($fornecedores as &$f) {
        $parts = explode(' ', trim($f['nome']));
        $f['nome_curto'] = $parts[0] . (isset($parts[1]) ? ' ' . substr($parts[1],0,3).'.' : '');
        $f['nome_completo'] = $f['nome'];
    }

    // 2. Busca Itens (Linhas)
    // Precisamos trazer dados do Senior ou tabela local se tiver cache
    // Aqui estou usando a tabela local processos_itens + Join na descrição se possível, ou mock
    // ADAPTE O SELECT ABAIXO CONFORME SUA TABELA DE ITENS
    $sqlItens = "SELECT num_solicitacao, seq_solicitacao, quantidade FROM processos_itens WHERE id_processo_instancia = ?";
    $stmtI = $pdo->prepare($sqlItens);
    $stmtI->execute([$idProcesso]);
    $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // 3. Busca Todos os Preços (Matriz)
    $sqlPrecos = "SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                  FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?";
    $stmtP = $pdo->prepare($sqlPrecos);
    $stmtP->execute([$idProcesso]);
    
    $mapaPrecos = [];
    while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['num_solicitacao'].'-'.$row['seq_solicitacao'].'-'.$row['id_fornecedor_senior'];
        $mapaPrecos[$k] = (float)$row['valor_unitario'];
    }

    // 4. Monta a Grade e Calcula Vencedores
    $linhas = [];
    $totalGeral = 0;

    foreach ($itens as $item) {
        $num = $item['num_solicitacao'];
        $seq = $item['seq_solicitacao'];
        $qtd = (float)$item['quantidade'];
        
        // Simulação de nome do produto (Ideal seria buscar no banco ou Senior)
        $nomeProduto = "Item $num-$seq"; 

        $linha = [
            'chave' => "$num-$seq",
            'produto' => $nomeProduto, // Teria que buscar o nome real via Join ou Senior
            'qtd_fmt' => number_format($qtd, 2, ',', '.'),
            'celulas' => []
        ];

        // Acha o menor preço desta linha
        $precosDestaLinha = [];
        foreach ($fornecedores as $f) {
            $chaveP = "$num-$seq-".$f['id'];
            $val = $mapaPrecos[$chaveP] ?? null;
            if ($val > 0) $precosDestaLinha[] = $val;
        }

        $menorPreco = !empty($precosDestaLinha) ? min($precosDestaLinha) : null;
        $linha['melhor_fmt'] = $menorPreco ? 'R$ '.number_format($menorPreco, 2, ',', '.') : '-';

        if ($menorPreco) {
            $totalGeral += ($menorPreco * $qtd);
        }

        // Monta as células para cada fornecedor
        foreach ($fornecedores as $f) {
            $chaveP = "$num-$seq-".$f['id'];
            $valor = $mapaPrecos[$chaveP] ?? null;
            
            $status = 'empty';
            $valorFmt = '-';

            if ($valor > 0) {
                $valorFmt = 'R$ ' . number_format($valor, 2, ',', '.');
                if ($valor == $menorPreco) {
                    // Verifica empate
                    $qtdEmpate = count(array_keys($precosDestaLinha, $menorPreco));
                    $status = ($qtdEmpate > 1) ? 'tie' : 'winner';
                } else {
                    $status = 'loser';
                }
            }

            $linha['celulas'][$f['id']] = [
                'valor_fmt' => $valorFmt,
                'status' => $status
            ];
        }

        $linhas[] = $linha;
    }

    ob_clean();
    echo json_encode([
        'cabecalho' => $fornecedores,
        'linhas' => $linhas,
        'total_fmt' => number_format($totalGeral, 2, ',', '.')
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['erro' => $e->getMessage()]);
}
?>