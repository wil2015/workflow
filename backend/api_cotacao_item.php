<?php
// backend/api_cotacao_item.php
require 'db_conexao.php';

$idProc = $_GET['id_proc'];
$numSol = $_GET['num'];
$seqSol = $_GET['seq'];

// 1. Busca os Fornecedores (Do MySQL, pois já foram selecionados)
$sqlForn = "SELECT 
                p.id_fornecedor_senior, 
                p.nome_do_fornecedor,
                o.valor_unitario
            FROM licitacao_participantes p
            LEFT JOIN licitacao_itens_ofertados o 
                ON p.id_processo_instancia = o.id_processo_instancia
                AND p.id_fornecedor_senior = o.id_fornecedor_senior
                AND o.num_solicitacao = ? 
                AND o.seq_solicitacao = ?
            WHERE p.id_processo_instancia = ?
            ORDER BY p.nome_do_fornecedor";

$stmt = $pdo->prepare($sqlForn);
$stmt->execute([$numSol, $seqSol, $idProc]);
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($fornecedores)) {
    echo '<div style="padding:20px; color:orange">⚠️ Nenhum fornecedor participante neste processo.</div>';
    exit;
}
?>

<h3>Cotação para o Item: <?= $numSol ?>-<?= $seqSol ?></h3>
<p style="margin-bottom:20px; color:#666">Digite o valor unitário para cada fornecedor.</p>

<?php foreach ($fornecedores as $f): 
    $valorFmt = $f['valor_unitario'] ? number_format($f['valor_unitario'], 2, ',', '.') : '';
?>
    <div class="form-group">
        <div style="flex:1;">
            <div style="font-weight:bold; font-size:14px;"><?= $f['nome_do_fornecedor'] ?></div>
            <div style="font-size:11px; color:#888;">Cód: <?= $f['id_fornecedor_senior'] ?></div>
        </div>
        <div>
            <div class="input-group" style="display:flex; align-items:center;">
                <span style="margin-right:5px; font-weight:bold; color:#555;">R$</span>
                <input type="text" 
                       class="input-money mask-money-ajax" 
                       value="<?= $valorFmt ?>" 
                       placeholder="0,00"
                       
                       data-proc="<?= $idProc ?>"
                       data-num="<?= $numSol ?>"
                       data-seq="<?= $seqSol ?>"
                       data-forn="<?= $f['id_fornecedor_senior'] ?>">
            </div>
        </div>
    </div>
<?php endforeach; ?>