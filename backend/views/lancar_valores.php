<?php
require '../db_conexao.php';
require '../db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;
if (!$idProcesso) { echo "<div style='color:red;padding:20px'>Erro: Processo não informado.</div>"; exit; }

// 1. Busca Itens do Processo (Lado Esquerdo)
$itens = [];
$stmt = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
$stmt->execute([$idProcesso]);
$vinculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($connSenior) {
    foreach ($vinculos as $v) {
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        $stmtS = sqlsrv_query($connSenior, $sql, [$v['num_solicitacao'], $v['seq_solicitacao']]);
        if ($stmtS && $row = sqlsrv_fetch_array($stmtS, SQLSRV_FETCH_ASSOC)) {
            // Conta quantos fornecedores já têm preço lançado para este item
            $chave = $row['numsol'].'-'.$row['seqsol'];
            $itens[] = $row;
        }
    }
} else {
    // Simulação
    $itens = [
        ['numsol'=>1060, 'seqsol'=>1, 'cplpro'=>'Item Simulado 1', 'qtdsol'=>2, 'unimed'=>'UN'],
        ['numsol'=>1060, 'seqsol'=>2, 'cplpro'=>'Item Simulado 2', 'qtdsol'=>5, 'unimed'=>'UN']
    ];
}
?>

<style>
    .layout-cotacao { display: flex; height: 500px; border: 1px solid #ccc; }
    .lista-itens { width: 40%; border-right: 1px solid #ccc; overflow-y: auto; background: #f8f9fa; }
    .detalhe-item { width: 60%; padding: 20px; overflow-y: auto; background: #fff; }
    
    .item-row { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; }
    .item-row:hover { background: #e9ecef; }
    .item-row.active { background: #007bff; color: white; }
    .item-row.active small { color: #dcdcdc; }
    
    .input-money { width: 150px; padding: 8px; text-align: right; border: 1px solid #ccc; border-radius: 4px; }
    .form-group { margin-bottom: 15px; padding: 10px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center; }
    
    .badge-qtd { background: #6c757d; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
</style>

<div class="modal-header">
    <h2>Lançamento de Valores</h2>
    <p>Selecione um item à esquerda para informar os preços dos fornecedores.</p>
</div>

<div class="layout-cotacao">
    <div class="lista-itens">
        <?php foreach ($itens as $i): 
            $chave = $i['numsol'] . '-' . $i['seqsol'];
        ?>
        <div class="item-row" onclick="carregarCotacaoItem('<?= $idProcesso ?>', '<?= $i['numsol'] ?>', '<?= $i['seqsol'] ?>', this)">
            <div style="font-weight:bold; font-size:13px; margin-bottom:5px;">
                Sol: <?= $i['numsol'] ?>-<?= $i['seqsol'] ?>
            </div>
            <div style="font-size:12px; line-height:1.4;">
                <?= mb_strimwidth($i['cplpro'], 0, 60, "...") ?>
            </div>
            <div style="margin-top:5px;">
                <span class="badge-qtd"><?= number_format($i['qtdsol'], 2, ',', '.') ?> <?= $i['unimed'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="detalhe-item" id="area-form-precos">
        <div style="text-align:center; color:#999; margin-top:150px;">
            <p>← Clique em um item para lançar valores.</p>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-cotacao">Fechar</button>
</div>

<script>
// Função Global para carregar via AJAX
window.carregarCotacaoItem = async function(idProc, numSol, seqSol, el) {
    // 1. Visual Ativo
    document.querySelectorAll('.item-row').forEach(r => r.classList.remove('active'));
    el.classList.add('active');

    // 2. Carrega HTML do Formulário
    const container = document.getElementById('area-form-precos');
    container.innerHTML = '<div style="text-align:center; margin-top:50px">Carregando fornecedores...</div>';

    try {
        const url = `/backend/api_cotacao_item.php?id_proc=${idProc}&num=${numSol}&seq=${seqSol}`;
        const resp = await fetch(url);
        const html = await resp.text();
        container.innerHTML = html;
        
        // Ativa máscaras e eventos no novo HTML carregado
        initFormCotacao(); 
    } catch (err) {
        container.innerHTML = '<div style="color:red">Erro ao carregar.</div>';
    }
};

function initFormCotacao() {
    // Máscara de Moeda
    const inputs = document.querySelectorAll('.mask-money-ajax');
    inputs.forEach(el => {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, "");
            v = (v / 100).toFixed(2) + "";
            v = v.replace(".", ",");
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            e.target.value = v;
        });
        
        // Auto-Save ao perder o foco (blur)
        el.addEventListener('blur', function() {
            salvarValorIndividual(this);
        });
    });
}

async function salvarValorIndividual(input) {
    const codForn = input.dataset.forn;
    const numSol = input.dataset.num;
    const seqSol = input.dataset.seq;
    const idProc = input.dataset.proc;
    const valor = input.value;

    // Feedback visual
    input.style.borderColor = '#ffc107'; // Amarelo = Salvando

    const fd = new FormData();
    fd.append('acao', 'salvar_unitario');
    fd.append('id_processo', idProc);
    fd.append('num_solicitacao', numSol);
    fd.append('seq_solicitacao', seqSol);
    fd.append('cod_fornecedor', codForn);
    fd.append('valor', valor);

    try {
        const req = await fetch('/backend/acoes/salvar_cotacoes.php', { method: 'POST', body: fd });
        const res = await req.json();
        if (res.sucesso) {
            input.style.borderColor = '#28a745'; // Verde = Salvo
        } else {
            input.style.borderColor = '#dc3545'; // Vermelho = Erro
            alert(res.erro);
        }
    } catch (err) {
        input.style.borderColor = '#dc3545';
    }
}

document.getElementById('btn-fechar-cotacao').addEventListener('click', function() {
    document.getElementById('modal-overlay').classList.add('hidden');
    document.getElementById('modal-body').innerHTML = '';
});
</script>