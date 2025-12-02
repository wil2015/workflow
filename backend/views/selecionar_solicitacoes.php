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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 13px; margin-top: 10px; }
    #tabela-solicitacoes { width: 100% !important; }
    .badge-info { background: #17a2b8; color: white; }
    .row-disabled { background-color: #f9f9f9; color: #999; }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    .btn-danger:hover { background: #c82333; }
</style>

<div class="modal-header">
    <?php if ($idProcessoMySQL): ?>
        <h2>Gerenciar Processo #<?= $idProcessoMySQL ?></h2>
        <p>Solicitação vinculada: <strong>#<?= $numSolSenior ?? 'N/A' ?></strong></p>
    <?php else: ?>
        <h2>Iniciar Novo Processo</h2>
    <?php endif; ?>
</div>

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
                // Se estamos vendo um processo específico, consideramos ele vinculado visualmente
                $isLinked = isset($processosVinculados[$numsol]) || ($idProcessoMySQL && $numsol == $numSolSenior);
                $chaveEnvio = $sol['codemp'] . '-' . $numsol;
            ?>
            <tr class="<?= $isLinked ? 'row-disabled' : '' ?>">
                <td class="text-center">
                    <?php if ($isLinked): ?>
                        <span style="color:#999">-</span>
                    <?php else: ?>
                        <input type="checkbox" name="selecionados[]" value="<?= $chaveEnvio ?>" class="chk-item-erp">
                    <?php endif; ?>
                </td>
                <td><b><?= $numsol ?></b>-<?= $sol['seqsol'] ?></td>
                <td><?= $sol['cplpro'] ?></td>
                <td><?= number_format($sol['qtdsol'], 2, ',', '.') ?> <?= $sol['unimed'] ?></td>
                <td>R$ <?= number_format($sol['presol'], 2, ',', '.') ?></td>
                <td>
                    <?php if ($isLinked): ?>
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

<div class="modal-footer" style="justify-content: space-between; display: flex;">
    <div>
        <?php if ($idProcessoMySQL && $numSolSenior): ?>
            <button type="button" class="btn-danger" id="btn-cancelar-geral" data-id="<?= $numSolSenior ?>">
                Deletar Processo
            </button>
        <?php endif; ?>
    </div>
    
    <div>
        <button class="btn-cancel" id="btn-fechar-modal-erp">Fechar</button>
        <?php if (!$idProcessoMySQL): ?>
            <button class="btn-save" id="btn-gerar-processo-erp">Gerar Processos</button>
        <?php endif; ?>
    </div>
</div>

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

    document.getElementById('btn-fechar-modal-erp').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

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
                if (res.sucesso) { alert(res.msg); document.getElementById('modal-overlay').classList.add('hidden'); window.location.reload(); }
                else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Falha: ' + err.message); } 
            finally { btnGerar.disabled = false; btnGerar.innerText = "Gerar Processos"; }
        });
    }

    // LÓGICA DO BOTÃO VERMELHO "DELETAR PROCESSO" (NOVO)
    const btnDeletar = document.getElementById('btn-cancelar-geral');
    if (btnDeletar) {
        btnDeletar.addEventListener('click', async function() {
            const idSol = this.dataset.id;
            if (!confirm('ATENÇÃO: Deseja excluir permanentemente o Processo da Solicitação #' + idSol + '?')) return;

            const formData = new FormData();
            formData.append('acao', 'cancelar');
            formData.append('id_processo', idSol); // backend espera 'id_processo'

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) {
                    alert('Processo excluído com sucesso.');
                    // Redireciona para o painel principal
                    window.location.href = '/'; 
                } else {
                    alert('Erro ao excluir: ' + res.erro);
                }
            } catch (err) {
                alert('Erro de comunicação: ' + err.message);
            }
        });
    }

})();
</script>