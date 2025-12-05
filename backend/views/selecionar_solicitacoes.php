<?php
// backend/views/selecionar_solicitacoes.php

require  '../db_conexao.php';
require  '../db_senior.php';

// 1. Recebe o ID do Processo (ID interno do MySQL)
$idProcessoMySQL = $_GET['instance_id'] ?? null;
if ($idProcessoMySQL === 'null' || $idProcessoMySQL === '') $idProcessoMySQL = null;

$numSolSenior = null;

// 2. Se tem ID, descobre qual é a solicitação do Senior
if ($idProcessoMySQL) {
    try {
        $stmt = $pdo->prepare("SELECT id_processo_senior FROM processos_instancia WHERE id = ?");
        $stmt->execute([$idProcessoMySQL]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) $numSolSenior = $res['id_processo_senior'];
    } catch (Exception $e) { }
}

// 3. Mapeia processos existentes
$processosVinculados = [];
try {
    $stmt = $pdo->query("SELECT id_processo_senior FROM processos_instancia WHERE id_fluxo_definicao = 1");
    $processosVinculados = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $processosVinculados = array_flip($processosVinculados);
} catch (Exception $e) { }

// 4. Busca no Senior
$listaSolicitacoes = [];
if ($connSenior) {
    if ($idProcessoMySQL) {
        // Visualizar UM
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed FROM Sapiens.sapiens.e405sol WHERE numsol = ?";
        $params = [$numSolSenior];
    } else {
        // Visualizar TODOS
        $sql = "SELECT TOP 1000 codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed FROM Sapiens.sapiens.e405sol WHERE sitsol IN (1, 2) ORDER BY datsol DESC";
        $params = [];
    }
    if (!empty($sql)) {
        $stmtSenior = sqlsrv_query($connSenior, $sql, $params);
        if ($stmtSenior) {
            while ($row = sqlsrv_fetch_array($stmtSenior, SQLSRV_FETCH_ASSOC)) $listaSolicitacoes[] = $row;
        }
    }
} else {
    // Simulação
    $ns = $numSolSenior ? $numSolSenior : 1050;
    $listaSolicitacoes = [['codemp'=>1, 'numsol'=>$ns, 'seqsol'=>1, 'cplpro'=>'Item '.$ns, 'qtdsol'=>1, 'presol'=>100, 'unimed'=>'UN']];
}
?>

<!-- APENAS O HTML E SCRIPT ATUALIZADOS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 13px; margin-top: 10px; }
    #tabela-solicitacoes { width: 100% !important; }
    .badge-info { background: #17a2b8; color: white; }
    .row-disabled { background-color: #f9f9f9; color: #999; }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
</style>

<!-- HEADER IGUAL -->
<div class="modal-header">
    <?php if ($idProcessoMySQL): ?>
        <h2>Gerenciar Processo #<?= $idProcessoMySQL ?></h2>
        <p>Solicitação Senior: <strong>#<?= $numSolReal ?? 'N/A' ?></strong></p>
    <?php else: ?>
        <h2>Iniciar Novo Processo</h2>
        <p>Selecione as solicitações disponíveis no ERP.</p>
    <?php endif; ?>
</div>

<!-- TABELA IGUAL -->
<div style="padding: 0 10px;">
    <table id="tabela-solicitacoes" class="display" style="width:100%">
        <thead>
            <tr>
                <th width="30">
                    <?php if (!$idProcessoMySQL): ?><input type="checkbox" id="chk-todos-erp"><?php endif; ?>
                </th>
                <th>Solicitação</th>
                <th>Produto / Descrição</th>
                <th>Qtd.</th>
                <th>Preço Unit.</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listaSolicitacoes as $sol): 
                $numsol = $sol['numsol'];
                $estaVinculado = isset($processosVinculados[$numsol]);
                $chaveEnvio = $sol['codemp'] . '-' . $numsol;
            ?>
            <tr class="<?= $estaVinculado ? 'row-disabled' : '' ?>">
                <td class="text-center">
                    <?php if ($estaVinculado): ?>
                        <button type="button" class="btn-cancel-mini js-cancelar-btn" data-id="<?= $numsol ?>" title="Cancelar">&times;</button>
                    <?php else: ?>
                        <input type="checkbox" name="selecionados[]" value="<?= $chaveEnvio ?>" class="chk-item-erp">
                    <?php endif; ?>
                </td>
                <td><b><?= $numsol ?></b>-<?= $sol['seqsol'] ?></td>
                <td><?= $sol['cplpro'] ?></td>
                <td><?= number_format($sol['qtdsol'], 2, ',', '.') ?> <?= $sol['unimed'] ?></td>
                <td>R$ <?= number_format($sol['presol'], 2, ',', '.') ?></td>
                <td>
                    <?php if ($estaVinculado): ?>
                        <span class="badge badge-info">Vinculado</span>
                    <?php else: ?>
                        <span class="badge badge-success">Disponível</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- RODAPÉ -->
<div class="modal-footer" style="display: flex; justify-content: space-between;">
    <div>
        <?php if ($idProcessoMySQL): ?>
            <button class="btn-danger" id="btn-deletar-processo" data-id="<?= $numSolReal ?>">Excluir Processo</button>
        <?php endif; ?>
    </div>
    <div>
        <button class="btn-cancel" id="btn-fechar-modal-erp">Fechar</button>
        <?php if (!$idProcessoMySQL): ?>
            <button class="btn-save" id="btn-gerar-processo-erp">Gerar Processos</button>
        <?php endif; ?>
    </div>
</div>

<!-- SCRIPT CORRIGIDO (SEM RELOAD) -->
<script>
(function() {
    function initDataTable() {
        $('#tabela-solicitacoes').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
            "pageLength": 10,
            "searching": <?= $idProcessoMySQL ? 'false' : 'true' ?>,
            "paging": <?= $idProcessoMySQL ? 'false' : 'true' ?>,
            "info": <?= $idProcessoMySQL ? 'false' : 'true' ?>,
            "columnDefs": [ { "orderable": false, "targets": 0 } ]
        });
        const chkMaster = document.getElementById('chk-todos-erp');
        if (chkMaster) {
            chkMaster.addEventListener('click', function(){
                var rows = $('#tabela-solicitacoes').DataTable().rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"]', rows).prop('checked', this.checked);
            });
        }
    }

    if (typeof jQuery == 'undefined') {
        var s = document.createElement("script"); s.src = "https://code.jquery.com/jquery-3.7.0.min.js";
        s.onload = function() {
            var sd = document.createElement("script"); sd.src = "https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js";
            sd.onload = initDataTable; document.head.appendChild(sd);
        }; document.head.appendChild(s);
    } else { initDataTable(); }

    // FECHAR: Apenas esconde o modal
    document.getElementById('btn-fechar-modal-erp').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // GERAR PROCESSOS
    const btnGerar = document.getElementById('btn-gerar-processo-erp');
    if (btnGerar) {
        btnGerar.addEventListener('click', async function() {
            var tabela = $('#tabela-solicitacoes').DataTable();
            var checkedInputs = tabela.$('input.chk-item-erp:checked');

            if (checkedInputs.length === 0) { alert('Selecione um item.'); return; }

            btnGerar.disabled = true; btnGerar.innerText = "Processando...";
            const formData = new FormData();
            formData.append('acao', 'vincular');
            checkedInputs.each(function() { formData.append('selecionados[]', this.value); });

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                
                if (res.sucesso) { 
                    alert(res.msg); 
                    // MUDANÇA: Apenas fecha o modal. O usuário continua no diagrama.
                    document.getElementById('modal-overlay').classList.add('hidden'); 
                    // Se quiser ver a mudança, o usuário clica de novo na tarefa e a lista recarrega.
                } else { 
                    alert('Erro: ' + res.erro); 
                }
            } catch (err) { alert('Falha: ' + err.message); } 
            finally { btnGerar.disabled = false; btnGerar.innerText = "Gerar Processos"; }
        });
    }

    // CANCELAR ITEM (Botão X na tabela)
    $('#tabela-solicitacoes tbody').on('click', '.js-cancelar-btn', async function() {
        const idProcesso = $(this).data('id');
        if (!confirm('Cancelar Processo #' + idProcesso + '?')) return;

        const formData = new FormData();
        formData.append('acao', 'cancelar');
        formData.append('id_processo', idProcesso);

        try {
            const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
            const res = await req.json();
            if (res.sucesso) {
                alert(res.msg);
                // Remove visualmente a linha sem reload
                $('#tabela-solicitacoes').DataTable().row($(this).parents('tr')).remove().draw();
            } else { alert('Erro: ' + res.erro); }
        } catch (err) { alert('Erro: ' + err.message); }
    });

    // EXCLUIR PROCESSO (Botão Vermelho Grande)
    const btnDeletar = document.getElementById('btn-deletar-processo');
    if (btnDeletar) {
        btnDeletar.addEventListener('click', async function() {
            const idSol = this.dataset.id;
            if (!confirm('ATENÇÃO: Excluir este processo vai fechar a tela atual. Confirmar?')) return;

            const formData = new FormData();
            formData.append('acao', 'cancelar');
            formData.append('id_processo', idSol);

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) {
                    alert('Processo excluído.');
                    // Aqui sim, PRECISAMOS sair, pois o processo que estamos vendo deixou de existir.
                    window.location.href = '/'; 
                } else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Erro: ' + err.message); }
        });
    }

})();
</script>