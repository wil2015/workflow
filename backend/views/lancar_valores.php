<?php
require  '../db_conexao.php';
require  '../db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

if (!$idProcesso) { echo "<div style='color:red;padding:20px'>Erro: Processo não informado.</div>"; exit; }

// 1. Busca FORNECEDORES Participantes (Do MySQL)
// Tabela: licitacao_participantes
$participantes = [];
$stmt = $pdo->prepare("SELECT id_fornecedor_senior, nome_do_fornecedor FROM licitacao_participantes WHERE id_processo_instancia = ? ORDER BY nome_do_fornecedor");
$stmt->execute([$idProcesso]);
$participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($participantes)) {
    echo "<div style='padding:20px'>⚠️ Nenhum fornecedor selecionado na etapa anterior.</div>";
    exit;
}

// 2. Busca ITENS da Solicitação (Do Senior - Apenas Leitura)
// Usamos isso para saber QUAIS linhas desenhar na tabela
$itensSolicitacao = [];
if ($connSenior) {
    // Busca pelo numsol (que é igual ao idProcesso)
    $sql = "SELECT seqsol, cplpro, qtdsol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ? ORDER BY seqsol ASC";
    $stmtSenior = sqlsrv_query($connSenior, $sql, [$idProcesso]);
    if ($stmtSenior) {
        while ($row = sqlsrv_fetch_array($stmtSenior, SQLSRV_FETCH_ASSOC)) {
            $itensSolicitacao[] = $row;
        }
    }
} else {
    // Simulação
    $itensSolicitacao = [
        ['seqsol'=>1, 'cplpro'=>'Notebook Dell (Simulado)', 'qtdsol'=>2, 'unimed'=>'UN'],
        ['seqsol'=>2, 'cplpro'=>'Mouse USB (Simulado)', 'qtdsol'=>5, 'unimed'=>'UN'],
    ];
}

// 3. Busca VALORES JÁ LANÇADOS (Do MySQL)
// Tabela: licitacao_itens_ofertados
// Precisamos cruzar pelo Item (item_solicitado) ou melhor, pela sequência se tivéssemos salvo.
// Como salvamos o Nome, vamos tentar bater o nome ou usar um array simples se a ordem for garantida.
// O ideal seria ter salvo 'seq_senior' na tabela ofertados, mas vamos usar o nome do item.
$valoresSalvos = [];
$stmt = $pdo->prepare("SELECT item_solicitado, id_fornecedor_senior, valor_unitario FROM licitacao_itens_ofertados WHERE id_processo_instancia = ?");
$stmt->execute([$idProcesso]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Chave: [NomeItem][CodFornecedor]
    $valoresSalvos[$row['item_solicitado']][$row['cod_fornecedor_senior']] = $row['valor_unitario'];
}
?>

<style>
    .matriz-container { overflow-x: auto; max-width: 100%; border: 1px solid #ddd; }
    .matriz-cotacao { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 800px; }
    .matriz-cotacao th, .matriz-cotacao td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; }
    .matriz-cotacao th { background: #f8f9fa; color: #333; font-weight: 600; }
    
    /* Coluna Fixa do Produto */
    .col-produto { text-align: left !important; background-color: #fff; position: sticky; left: 0; z-index: 2; width: 250px; min-width: 250px; }
    .col-qtd { width: 80px; }
    
    /* Inputs */
    .input-money { 
        width: 100%; min-width: 80px;
        padding: 6px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        text-align: right; 
        font-family: monospace;
    }
    .input-money:focus { border-color: #007bff; outline: none; box-shadow: 0 0 3px rgba(0,123,255,0.2); }
    
    .forn-header { max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; margin: 0 auto; }
</style>

<div class="modal-header">
    <h2>Lançamento de Propostas</h2>
    <p>Informe os valores unitários por fornecedor.</p>
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
                        <th class="col-qtd">Qtd</th>
                        
                        <?php foreach ($participantes as $f): ?>
                            <th>
                                <span class="forn-header" title="<?= $f['nome_fornecedor'] ?>">
                                    <?= $f['nome_fornecedor'] ?>
                                </span>
                                <small style="color:#888; font-weight:normal">#<?= $f['cod_fornecedor_senior'] ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itensSolicitacao as $item): 
                        $seq = $item['seqsol'];
                        $nomeItem = $item['cplpro']; // Usaremos o nome como chave de vínculo
                        $qtd = $item['qtdsol'];
                    ?>
                    <tr>
                        <td class="col-produto">
                            <input type="hidden" name="detalhes[<?= $seq ?>][nome]" value="<?= htmlspecialchars($nomeItem) ?>">
                            <input type="hidden" name="detalhes[<?= $seq ?>][qtd]" value="<?= $qtd ?>">
                            
                            <b>Item <?= $seq ?></b><br>
                            <?= mb_strimwidth($nomeItem, 0, 40, "...") ?>
                        </td>
                        
                        <td><?= number_format($qtd, 2, ',', '.') ?> <?= $item['unimed'] ?></td>

                        <?php foreach ($participantes as $f): 
                            $codForn = $f['cod_fornecedor_senior'];
                            
                            // Tenta encontrar valor salvo (usando o nome do item como chave)
                            $valorDB = $valoresSalvos[$nomeItem][$codForn] ?? '';
                            if ($valorDB) $valorDB = number_format($valorDB, 2, ',', '.');
                        ?>
                            <td>
                                <input type="text" 
                                       class="input-money mask-money" 
                                       name="cotacao[<?= $seq ?>][<?= $codForn ?>]" 
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
    // 1. Fechar
    document.getElementById('btn-fechar-cotacao').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // 2. Máscara de Moeda Simples (Ao digitar)
    const inputs = document.querySelectorAll('.mask-money');
    inputs.forEach(el => {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, ""); // Remove tudo que não é dígito
            v = (v / 100).toFixed(2) + ""; // Divide por 100 e fixa 2 decimais
            v = v.replace(".", ",");       // Troca ponto por vírgula
            v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1."); // Coloca ponto de milhar
            e.target.value = v;
        });
    });

    // 3. Salvar
    const btnSave = document.getElementById('btn-salvar-cotacao');
    btnSave.addEventListener('click', async function() {
        btnSave.disabled = true;
        btnSave.innerText = "Salvando...";

        const form = document.getElementById('form-cotacao');
        const formData = new FormData(form);

        try {
            const req = await fetch('/backend/acoes/salvar_cotacoes.php', { method: 'POST', body: formData });
            const texto = await req.text(); // Lê como texto para debug
            let res;
            try {
                res = JSON.parse(texto);
            } catch(e) {
                throw new Error("Resposta inválida: " + texto);
            }

            if (res.sucesso) {
                alert(res.msg);
                // Fecha o modal após sucesso
                document.getElementById('modal-overlay').classList.add('hidden');
                document.getElementById('modal-body').innerHTML = '';
            } else {
                alert('Erro: ' + res.erro);
            }
        } catch (err) {
            console.error(err);
            alert('Falha: ' + err.message);
        } finally {
            btnSave.disabled = false;
            btnSave.innerText = "Salvar Valores";
        }
    });
})();
</script>