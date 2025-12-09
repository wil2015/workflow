<?php
require  '../db_conexao.php';
require  '../db_senior.php';

$idProcesso = $_GET['instance_id'] ?? null;

if (!$idProcesso) {
    echo "<div style='padding:20px; color:red'>Erro: Processo não identificado.</div>";
    exit;
}

// 1. Busca Participantes JÁ SALVOS no MySQL
$selecionados = [];
try {
    $stmt = $pdo->prepare("SELECT cod_fornecedor_senior FROM licitacao_participantes WHERE id_processo_instancia = ?");
    $stmt->execute([$idProcesso]);
    $selecionados = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $selecionados = array_flip($selecionados); // [100 => true, 200 => true]
} catch (Exception $e) { }

// 2. Busca Fornecedores no Senior
$listaFornecedores = [];
if ($connSenior) {
    $sql = "SELECT TOP 1000 codfor, nomfor, cgccpf FROM Sapiens.sapiens.e095for WHERE sitfor = 'A' ORDER BY nomfor ASC";
    $stmtSenior = sqlsrv_query($connSenior, $sql);
    if ($stmtSenior) {
        while ($row = sqlsrv_fetch_array($stmtSenior, SQLSRV_FETCH_ASSOC)) {
            $listaFornecedores[] = $row;
        }
    }
} else {
    // Simulação
    $listaFornecedores = [
        ['codfor'=>10, 'nomfor'=>'Kalunga Comércio', 'cgccpf'=>'43.283.811/0001-50'],
        ['codfor'=>20, 'nomfor'=>'Dell Computadores', 'cgccpf'=>'72.381.189/0001-10'],
        ['codfor'=>30, 'nomfor'=>'Amazon Servicos', 'cgccpf'=>'15.436.940/0001-03'],
    ];
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 13px; margin-top: 10px; }
    #tb-fornecedores { width: 100% !important; }
    
    /* Estilos de Status */
    .badge-ok { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size:11px; font-weight:bold; }
    
    /* BOTÃO VERMELHO DE REMOVER */
    .btn-rm { 
        background: #dc3545; 
        color: white; 
        border: none; 
        width: 28px; height: 28px; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold;
        transition: 0.2s;
    }
    .btn-rm:hover { background: #c82333; }
    
    .row-selected { background-color: #f0fff4 !important; } /* Fundo verdinho para selecionados */
</style>

<div class="modal-header">
    <h2>Selecionar Fornecedores</h2>
    <p>Participantes do Processo <strong>#<?= $idProcesso ?></strong>.</p>
</div>

<div style="padding: 0 10px;">
    <table id="tb-fornecedores" class="display">
        <thead>
            <tr>
                <th width="30"><input type="checkbox" id="chk-all-forn"></th>
                <th>Razão Social / Nome</th>
                <th width="150">CNPJ / CPF</th>
                <th width="100">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listaFornecedores as $f): 
                $cod = $f['codfor'];
                $jaTem = isset($selecionados[$cod]);
                
                $doc = $f['cgccpf'];
                // JSON para salvar
                $dadosJson = htmlspecialchars(json_encode([
                    'cod' => $cod,
                    'nome' => $f['nomfor'],
                    'doc' => $doc
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            <tr class="<?= $jaTem ? 'row-selected' : '' ?>">
                <td class="text-center">
                    <?php if ($jaTem): ?>
                        <button type="button" class="btn-rm js-rm-forn" data-cod="<?= $cod ?>" title="Remover este fornecedor">
                            &times;
                        </button>
                    <?php else: ?>
                        <input type="checkbox" name="forn_selecionado[]" value="<?= $dadosJson ?>" class="chk-forn">
                    <?php endif; ?>
                </td>
                
                <td>
                    <b><?= $f['nomfor'] ?></b><br>
                    <small style="color:#888">Cód: <?= $cod ?></small>
                </td>
                
                <td><?= $doc ?></td>
                
                <td>
                    <?php if ($jaTem): ?>
                        <span class="badge-ok">Participante</span>
                    <?php else: ?>
                        <small style="color:#999">Disponível</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-close-forn">Fechar</button>
    <button class="btn-save" id="btn-save-forn">Salvar Seleção</button>
</div>

<script>
(function() {
    // ... (Init DataTable e Carregamento de Libs igual ao anterior) ...
    // Vou focar apenas na mudança do SALVAR e REMOVER:

    // 1. Fechar
    document.getElementById('btn-close-forn').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // 2. SALVAR SELEÇÃO
    const btnSave = document.getElementById('btn-save-forn');
    btnSave.addEventListener('click', async function() {
        var table = $('#tb-fornecedores').DataTable();
        var checked = table.$('input.chk-forn:checked');

        if (checked.length === 0) { alert('Selecione ao menos um fornecedor.'); return; }

        btnSave.disabled = true; btnSave.innerText = "Salvando...";
        const fd = new FormData();
        fd.append('acao', 'salvar_participantes');
        fd.append('id_processo', '<?= $idProcesso ?>');
        checked.each(function() { fd.append('participantes[]', this.value); });

        try {
            const req = await fetch('/backend/acoes/gerenciar_participantes.php', { method: 'POST', body: fd });
            const res = await req.json();
            
            if (res.sucesso) {
                alert(res.msg);
                // MUDANÇA: Fecha o modal apenas.
                document.getElementById('modal-overlay').classList.add('hidden');
            } else {
                alert('Erro: ' + res.erro);
            }
        } catch (err) { console.error(err); alert('Falha ao salvar.'); }
        finally { btnSave.disabled = false; btnSave.innerText = "Salvar Seleção"; }
    });

    // 3. REMOVER (Botão X)
    $('#tb-fornecedores tbody').on('click', '.js-rm-forn', async function() {
        const codFornecedor = $(this).data('cod');
        if (!confirm('Remover fornecedor da lista?')) return;

        const fd = new FormData();
        fd.append('acao', 'remover_participante');
        fd.append('id_processo', '<?= $idProcesso ?>');
        fd.append('cod_fornecedor', codFornecedor);

        try {
            const req = await fetch('/backend/acoes/gerenciar_participantes.php', { method: 'POST', body: fd });
            const res = await req.json();
            if (res.sucesso) {
                // Atualiza visualmente trocando o botão X pelo checkbox
                // (Para simplificar, apenas removemos a formatação de "selecionado")
                var row = $(this).parents('tr');
                row.removeClass('row-selected');
                
                // Recria o checkbox na célula (Hack visual para não precisar de reload)
                // O ideal seria reload do modal, mas se quer ficar na tela, isso serve.
                // OU: Simplesmente avisamos "Removido" e fechamos o modal para forçar reabertura limpa.
                alert('Removido! Reabra a janela para atualizar a lista completa.');
                document.getElementById('modal-overlay').classList.add('hidden');
            } else { alert('Erro: ' + res.erro); }
        } catch (err) { alert('Erro: ' + err.message); }
    });

})();
</script>