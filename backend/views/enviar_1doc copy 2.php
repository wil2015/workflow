<?php
require  '../db_conexao.php';
require  '../db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;
if (!$idProcesso) { echo "<div class='error-msg'>Processo não informado.</div>"; exit; }

// 1. BUSCA PARTICIPANTES (COLUNAS)
// Correção: id_fornecedor_senior
$stmtF = $pdo->prepare("SELECT id_fornecedor_senior, nome_do_fornecedor, cnpj_cpf 
                        FROM licitacao_participantes 
                        WHERE id_processo_instancia = ? 
                        ORDER BY id_fornecedor_senior"); 
$stmtF->execute([$idProcesso]);
$fornecedores = $stmtF->fetchAll(PDO::FETCH_ASSOC);

if (empty($fornecedores)) {
    echo "<div style='padding:20px'>⚠️ Nenhum fornecedor participando.</div>";
    exit;
}

// 2. BUSCA ITENS (LINHAS)
$stmtI = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
$stmtI->execute([$idProcesso]);
$listaItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

// 3. BUSCA VALORES (CRUZAMENTO)
// Correção: id_fornecedor_senior
$precos = [];
$stmtP = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                        FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
$stmtP->execute([$idProcesso]);
while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
    $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
    // Mapeia usando o ID correto
    $precos[$chave][$row['id_fornecedor_senior']] = $row['valor_unitario'];
}

// 4. PREPARA GRADE
$grade = [];
$totalGeral = 0;

foreach ($listaItens as $i) {
    $num = $i['num_solicitacao'];
    $seq = $i['seq_solicitacao'];
    $chave = "$num-$seq";
    
    // Dados do Senior
    $nomeProduto = "Item $num-$seq";
    $qtd = 0;
    if ($connSenior) {
        $sqlS = "SELECT cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        $qS = sqlsrv_query($connSenior, $sqlS, [$num, $seq]);
        if ($qS && $rS = sqlsrv_fetch_array($qS, SQLSRV_FETCH_ASSOC)) {
            $nomeProduto = utf8_encode($rS['cplpro']);
            $qtd = $rS['qtdsol'];
        }
    }

    // Calcula Vencedor
    $menorPreco = null;
    $vencedores = []; 

    foreach ($fornecedores as $f) {
        $cod = $f['id_fornecedor_senior']; // ID Correto
        $valor = $precos[$chave][$cod] ?? 0;
        
        if ($valor > 0) {
            if ($menorPreco === null || $valor < $menorPreco) {
                $menorPreco = $valor;
            }
        }
    }

    if ($menorPreco !== null) {
        foreach ($fornecedores as $f) {
            $cod = $f['id_fornecedor_senior']; // ID Correto
            $valor = $precos[$chave][$cod] ?? 0;
            // Comparação segura de float
            if ($valor > 0 && abs($valor - $menorPreco) < 0.001) {
                $vencedores[] = $cod;
            }
        }
        $totalGeral += ($menorPreco * $qtd);
    }

    $grade[] = [
        'chave' => $chave,
        'produto' => $nomeProduto,
        'qtd' => $qtd,
        'menor_preco' => $menorPreco,
        'lista_vencedores' => $vencedores
    ];
}
?>

<style>
    .grade-wrapper { overflow-x: auto; margin-top: 10px; border: 1px solid #ddd; }
    .table-grade { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1000px; }
    .table-grade th, .table-grade td { padding: 8px; border: 1px solid #eee; text-align: center; vertical-align: middle; }
    
    .table-grade th { background-color: #f8f9fa; color: #333; font-weight: 600; font-size: 11px; }
    .col-prod { text-align: left !important; width: 300px; min-width: 250px; background: #fff; position: sticky; left: 0; z-index: 2; border-right: 2px solid #ddd !important; }
    
    .winner { background-color: #d4edda; color: #155724; border: 2px solid #c3e6cb !important; font-weight: bold; }
    .tie { background-color: #fff3cd; color: #856404; border: 2px solid #ffeeba !important; font-weight: bold; }
    .loser { color: #999; }
    .empty { background-color: #f9f9f9; color: #ccc; }

    .total-box { margin-top: 20px; padding: 15px; background: #e9ecef; text-align: right; font-size: 16px; border-radius: 4px; border-left: 5px solid #28a745; }
</style>

<div class="modal-header">
    <h2>Grade Comparativa</h2>
    <p>Os itens marcados representam o menor preço encontrado.</p>
</div>

<div style="padding: 10px;">
    <div class="grade-wrapper">
        <table class="table-grade">
            <thead>
                <tr>
                    <th class="col-prod">Item / Produto</th>
                    <th style="width: 90px; background:#e2e6ea;">Melhor Preço</th>
                    
                    <?php foreach ($fornecedores as $f): 
                        $nomeExibicao = $f['nome_do_fornecedor'] ?? '';
                        if (empty(trim($nomeExibicao))) {
                            $nomeExibicao = "Fornecedor " . $f['id_fornecedor_senior'];
                        }
                    ?>
                        <th title="<?= htmlspecialchars($nomeExibicao) ?>">
                            <?= mb_strimwidth($nomeExibicao, 0, 20, "...") ?>
                            <br><small style="font-weight:normal; color:#666">Cód: <?= $f['id_fornecedor_senior'] ?></small>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade as $linha): 
                    $chave = $linha['chave'];
                    $isTie = count($linha['lista_vencedores']) > 1; 
                ?>
                <tr>
                    <td class="col-prod">
                        <span style="font-weight:bold; color:#555; font-size:10px"><?= $chave ?></span><br>
                        <?= mb_strimwidth($linha['produto'], 0, 50, "...") ?>
                        <br><small>Qtd: <?= number_format($linha['qtd'], 2, ',', '.') ?></small>
                    </td>
                    
                    <td style="background:#f1f3f5; font-weight:bold;">
                        <?php if ($linha['menor_preco']): ?>
                            R$ <?= number_format($linha['menor_preco'], 2, ',', '.') ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <?php foreach ($fornecedores as $f): 
                        $cod = $f['id_fornecedor_senior']; // ID Correto
                        $valor = $precos[$chave][$cod] ?? 0;
                        
                        $classe = 'empty';
                        $texto = '-';
                        
                        if ($valor > 0) {
                            $valorFmt = number_format($valor, 2, ',', '.');
                            
                            if (in_array($cod, $linha['lista_vencedores'])) {
                                if ($isTie) {
                                    $classe = 'tie';
                                    $texto = "R$ $valorFmt<br><span style='font-size:9px'>EMPATE</span>";
                                } else {
                                    $classe = 'winner';
                                    $texto = "R$ $valorFmt<br><span style='font-size:9px'>★ VENCEU</span>";
                                }
                            } else {
                                $classe = 'loser';
                                $texto = "R$ $valorFmt";
                            }
                        }
                    ?>
                        <td class="<?= $classe ?>">
                            <?= $texto ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="total-box">
        <b>Total Estimado:</b> R$ <?= number_format($totalGeral, 2, ',', '.') ?>
    </div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-grade">Voltar</button>
    <button class="btn-save" id="btn-enviar-1doc">Gerar Documento</button>
</div>

<script>
(function() {
    document.getElementById('btn-fechar-grade').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });
    
    document.getElementById('btn-enviar-1doc').addEventListener('click', function() {
        alert('Funcionalidade de integração com 1Doc seria acionada aqui.');
    });
})();
</script>