<?php
require  '../db_conexao.php';
require  '../db_senior.php';

// 1. Recebe ID para filtro
$filtroInstancia = $_GET['instance_id'] ?? null;
if ($filtroInstancia === 'null' || $filtroInstancia === '') $filtroInstancia = null;

// 2. Busca Processos Existentes
$processosExistentes = [];
try {
    $stmt = $pdo->query("SELECT id_processo_instancia FROM processos_instancia WHERE id_fluxo_definicao = 1");
    $processosExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $processosExistentes = array_flip($processosExistentes);
} catch (Exception $e) { }

// 3. Busca no Senior
$listaSolicitacoes = [];
if ($connSenior) {
    if ($filtroInstancia) {
        // Visualizar UM
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol 
                FROM Sapiens.sapiens.e405sol WHERE numsol = ? ORDER BY seqsol ASC"; 
        $params = [$filtroInstancia];
    } else {
        // Listar TODOS
        $sql = "SELECT TOP 1000 codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol 
                FROM Sapiens.sapiens.e405sol 
                WHERE sitsol IN (1, 2) 
               ORDER BY numprj DESC, datsol DESC, numsol DESC, seqsol ASC";
        $params = [];
    }
    $stmtSenior = sqlsrv_query($connSenior, $sql, $params);
    if ($stmtSenior) {
        while ($row = sqlsrv_fetch_array($stmtSenior, SQLSRV_FETCH_ASSOC)) {
            $listaSolicitacoes[] = $row;
        }
    }
} else {
    // Simulação
    $date1 = new DateTime('2025-11-14');
    $date2 = new DateTime('2025-11-10');
    
    if ($filtroInstancia) {
         $listaSolicitacoes = [['codemp'=>1, 'numsol'=>$filtroInstancia, 'seqsol'=>1, 'cplpro'=>'Item '.$filtroInstancia, 'qtdsol'=>1, 'presol'=>100.50, 'unimed'=>'UN', 'numprj'=>'PROJ-001', 'datsol'=>$date1]];
    } else {
         $listaSolicitacoes = [
            ['codemp'=>1, 'numsol'=>1060, 'seqsol'=>1, 'cplpro'=>'Servidor Rack', 'qtdsol'=>1, 'presol'=>25000.00, 'unimed'=>'UN', 'numprj'=>'INFRA-25', 'datsol'=>$date1],
            ['codemp'=>1, 'numsol'=>1050, 'seqsol'=>1, 'cplpro'=>'Notebook Dell', 'qtdsol'=>2, 'presol'=>7500.00, 'unimed'=>'UN', 'numprj'=>'ADM-01', 'datsol'=>$date2],
            ['codemp'=>1, 'numsol'=>1050, 'seqsol'=>2, 'cplpro'=>'Mouse USB', 'qtdsol'=>2, 'presol'=>100.00, 'unimed'=>'UN', 'numprj'=>'ADM-01', 'datsol'=>$date2],
        ];
    }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 12px; margin-top: 10px; }
    #tabela-solicitacoes { width: 100% !important; }
    .badge-info { background: #17a2b8; color: white; }
    .btn-danger { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    .proj-tag { background: #e9ecef; color: #495057; padding: 2px 5px; border-radius: 3px; font-weight: bold; font-size: 11px; }
</style>

<div class="modal-header">
    <?php if ($filtroInstancia): ?>
        <h2>Gerenciar Processo #<?= $filtroInstancia ?></h2>
        <p>Itens vinculados.</p>
    <?php else: ?>
        <h2>Iniciar Novo Processo</h2>
        <p>Selecione as solicitações (Agrupadas por Projeto).</p>
    <?php endif; ?>
</div>

<div style="padding: 0 10px;">
    <table id="tabela-solicitacoes" class="display" style="width:100%">
        <thead>
            <tr>
                <th width="20">
                    <?php if (!$filtroInstancia): ?><input type="checkbox" id="chk-todos-erp"><?php endif; ?>
                </th>
                <th width="90">Projeto</th>
                <th width="80">Data</th>
                <th width="80">Solicitação</th>
                <th>Produto / Descrição</th>
                <th width="100">Preço Unit.</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listaSolicitacoes as $sol): 
                $numsol = $sol['numsol'];
                $existeProcesso = isset($processosExistentes[$numsol]);
                $chaveEnvio = $sol['codemp'] . '-' . $numsol;
                
                // Formatação Data
                $dataFmt = '';
                $dataOrder = ''; 
                if (isset($sol['datsol']) && $sol['datsol'] instanceof DateTime) {
                    $dataFmt = $sol['datsol']->format('d/m/Y');
                    $dataOrder = $sol['datsol']->format('YmdHis');
                }

                // Formatação Preço (presol)
                $precoRaw = $sol['presol'] ?? 0;
                $precoFmt = number_format($precoRaw, 2, ',', '.');
            ?>
            <tr class="<?= $existeProcesso ? 'row-disabled' : '' ?>">
                <td class="text-center">
                    <?php if ($existeProcesso): ?>
                        <button type="button" class="btn-cancel-mini js-cancelar-btn" data-id="<?= $numsol ?>" title="Cancelar">
                            &times;
                        </button>
                    <?php else: ?>
                        <input type="checkbox" name="selecionados[]" value="<?= $chaveEnvio ?>" class="chk-item-erp">
                    <?php endif; ?>
                </td>
                
                <td><span class="proj-tag"><?= $sol['numprj'] ?></span></td>
                
                <td data-order="<?= $dataOrder ?>"><?= $dataFmt ?></td>
                
                <td><b><?= $numsol ?></b>-<?= $sol['seqsol'] ?></td>
                
                <td>
                    <?= $sol['cplpro'] ?><br>
                    <small style="color:#666">
                        Qtd: <?= number_format($sol['qtdsol'], 2, ',', '.') ?> <?= $sol['unimed'] ?>
                    </small>
                </td>

                <td data-order="<?= $precoRaw ?>">
                    R$ <?= $precoFmt ?>
                </td>
                
                <td>
                    <?php if ($existeProcesso): ?>
                        <span class="badge badge-info">Vinculado</span>
                    <?php else: ?>
                        <span class="badge badge-success">Novo</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-footer" style="display: flex; justify-content: space-between;">
    <div>
        <?php if ($filtroInstancia): ?>
            <button class="btn-danger" id="btn-deletar-processo" data-id="<?= $filtroInstancia ?>">Excluir Processo</button>
        <?php endif; ?>
    </div>
    <div>
        <button class="btn-cancel" id="btn-fechar-modal-erp">Fechar</button>
        <?php if (!$filtroInstancia): ?>
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
            "order": [[ 1, "asc" ], [ 2, "desc" ]], 
            "searching": <?= $filtroInstancia ? 'false' : 'true' ?>,
            "paging": <?= $filtroInstancia ? 'false' : 'true' ?>,
            "info": <?= $filtroInstancia ? 'false' : 'true' ?>,
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
                if (res.sucesso) { alert(res.msg); document.getElementById('modal-overlay').classList.add('hidden'); }
                else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Falha: ' + err.message); } 
            finally { btnGerar.disabled = false; btnGerar.innerText = "Gerar Processos"; }
        });
    }

    const btnDeletar = document.getElementById('btn-deletar-processo');
    if (btnDeletar) {
        btnDeletar.addEventListener('click', async function() {
            const idSol = this.dataset.id;
            if (!confirm('ATENÇÃO: Deseja excluir permanentemente o Processo #' + idSol + '?')) return;

            const formData = new FormData();
            formData.append('acao', 'cancelar');
            formData.append('id_processo', idSol);

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) { alert('Processo excluído.'); window.location.href = '/'; } 
                else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Erro: ' + err.message); }
        });
    }
    
    $('#tabela-solicitacoes tbody').on('click', '.js-cancelar-btn', async function() {
        const idProcesso = $(this).data('id');
        if (!confirm('Cancelar Processo #' + idProcesso + '?')) return;
    });

})();
</script>