<?php
require  '../db_conexao.php';
require  '../db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

if (!$idProcesso) { echo "<div style='color:red;padding:20px'>Erro: Processo não informado.</div>"; exit; }

// 1. Busca Participantes
$participantes = [];
$stmt = $pdo->prepare("SELECT id_fornecedor_senior, nome_do_fornecedor FROM licitacao_participantes WHERE id_processo_instancia = ? ORDER BY nome_do_fornecedor");
$stmt->execute([$idProcesso]);
$participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($participantes)) {
    echo "<div style='padding:20px'>⚠️ Nenhum fornecedor selecionado.</div>";
    exit;
}

// 2. Busca Itens do Processo (Lendo da tabela de vínculo processos_itens)
$itensSolicitacao = [];
$stmtItens = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao FROM processos_itens WHERE id_processo_instancia = ? ORDER BY num_solicitacao, seq_solicitacao");
$stmtItens->execute([$idProcesso]);
$vinculos = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

if ($connSenior) {
    foreach ($vinculos as $v) {
        // Busca detalhes no Senior para cada item vinculado
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? AND seqsol = ?";
        $stmtS = sqlsrv_query($connSenior, $sql, [$v['num_solicitacao'], $v['seq_solicitacao']]);
        if ($stmtS && $row = sqlsrv_fetch_array($stmtS, SQLSRV_FETCH_ASSOC)) {
            $itensSolicitacao[] = $row;
        }
    }
} else {
    // Simulação
    $itensSolicitacao = [
        ['numsol'=>1060, 'seqsol'=>1, 'cplpro'=>'Item Simulado 1', 'qtdsol'=>2, 'unimed'=>'UN'],
        ['numsol'=>1060, 'seqsol'=>2, 'cplpro'=>'Item Simulado 2', 'qtdsol'=>5, 'unimed'=>'UN']
    ];
}

// 3. Busca Valores Já Salvos (Agora usando a chave composta NUM+SEQ)
$valoresSalvos = [];
$stmt = $pdo->prepare("SELECT num_solicitacao, seq_solicitacao, id_fornecedor_senior, valor_unitario 
                       FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
$stmt->execute([$idProcesso]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Chave: [num-seq][cod_forn]
    $chaveItem = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
    $valoresSalvos[$chaveItem][$row['id_fornecedor_senior']] = $row['valor_unitario'];
}
?>

<style>
    .matriz-container { overflow-x: auto; max-width: 100%; border: 1px solid #ddd; margin-top: 10px; }
    .matriz-cotacao { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 800px; }
    .matriz-cotacao th, .matriz-cotacao td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; }
    .matriz-cotacao th { background: #f8f9fa; color: #333; font-weight: 600; }
    .col-produto { text-align: left !important; background-color: #fff; position: sticky; left: 0; z-index: 2; width: 300px; }
    .input-money { width: 100%; min-width: 80px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; text-align: right; }
    .input-money:focus { border-color: #007bff; background: #fffbe6; outline: none; }
</style>

<div class="modal-header">
    <h2>Lançamento de Propostas</h2>
    <p>Informe os valores unitários.</p>
</div>

<div style="padding: 10px;">
    <form id="form-cotacao">
        <input type="hidden" name="id_processo" value="<?= $idProcesso ?>">
        <input type="hidden" name="acao" value="salvar_valores">

        <div class="matriz-container">
            <table class="matriz-cotacao">
                <thead>
                    <tr>
                        <th class="col-produto">Item / Produto</th>
                        <th width="80">Qtd</th>
                        <?php foreach ($participantes as $f): ?>
                            <th title="<?= $f['nome_fornecedor'] ?>">
                                <?= mb_strimwidth($f['nome_fornecedor'], 0, 15, "...") ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itensSolicitacao as $item): 
                        $num = $item['numsol'];
                        $seq = $item['seqsol'];
                        $chaveUnica = $num . '-' . $seq; // Chave "NUM-SEQ"
                        
                        $nomeItem = $item['cplpro'];
                    ?>
                    <tr>
                        <td class="col-produto">
                            <small style="color:#666; font-weight:bold">Sol: <?= $num ?>-<?= $seq ?></small><br>
                            <?= mb_strimwidth($nomeItem, 0, 60, "...") ?>
                        </td>
                        
                        <td><?= number_format($item['qtdsol'], 2, ',', '.') ?></td>

                        <?php foreach ($participantes as $f): 
                            $codForn = $f['cod_fornecedor_senior'];
                            $valorDB = $valoresSalvos[$chaveUnica][$codForn] ?? '';
                            if ($valorDB) $valorDB = number_format($valorDB, 2, ',', '.');
                        ?>
                            <td>
                                <input type="text" 
                                       class="input-money mask-money" 
                                       name="cotacao[<?= $chaveUnica ?>][<?= $codForn ?>]" 
                                       value="<?= $valorDB ?>"
                                       placeholder="0,00"
                                       autocomplete="off">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-cotacao">Fechar</button>
    <button class="btn-save" id="btn-salvar-cotacao">Salvar Valores</button>
</div>

<script>
(function() {
    document.getElementById('btn-fechar-cotacao').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    const inputs = document.querySelectorAll('.mask-money');
    inputs.forEach(el => {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, "");
            v = (v / 100).toFixed(2) + "";
            v = v.replace(".", ",");
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            e.target.value = v;
        });
    });

    const btnSave = document.getElementById('btn-salvar-cotacao');
    btnSave.addEventListener('click', async function() {
        btnSave.disabled = true; btnSave.innerText = "Salvando...";
        const form = document.getElementById('form-cotacao');
        const formData = new FormData(form);

        try {
            const req = await fetch('/backend/acoes/salvar_cotacoes.php', { method: 'POST', body: formData });
            const texto = await req.text();
            let res;
            try { res = JSON.parse(texto); } catch(e) { throw new Error("Erro Resposta: " + texto); }

            if (res.sucesso) {
                alert(res.msg);
                document.getElementById('modal-overlay').classList.add('hidden');
            } else { alert('Erro: ' + res.erro); }
        } catch (err) { alert('Falha: ' + err.message); } 
        finally { btnSave.disabled = false; btnSave.innerText = "Salvar Valores"; }
    });
})();
</script>