<?php
require  '../db_conexao.php';
require  '../db_senior.php';

$filtroInstancia = $_GET['instance_id'] ?? null;
if ($filtroInstancia === 'null' || $filtroInstancia === '') $filtroInstancia = null;

$numSolTitulo = null;

// Mapeamento
$itensVinculados = [];
try {
    $stmt = $pdo->query("SELECT id_processo_instancia, num_solicitacao, seq_solicitacao FROM processos_itens");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chave = $row['num_solicitacao'] . '-' . $row['seq_solicitacao'];
        $itensVinculados[$chave] = $row['id_processo_instancia'];
    }
    
    if ($filtroInstancia) {
        $stmt = $pdo->prepare("SELECT id_processo_senior FROM processos_instancia WHERE id = ?");
        $stmt->execute([$filtroInstancia]);
        $res = $stmt->fetch();
        if ($res) $numSolTitulo = $res['id_processo_senior'];
    }
} catch (Exception $e) { }

// Busca Senior
$listaSolicitacoes = [];
if ($connSenior) {
    if ($filtroInstancia && $numSolTitulo) {
        $sql = "SELECT codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol 
                FROM Sapiens.sapiens.e405sol WHERE numsol = ? ORDER BY seqsol ASC"; 
        $params = [$numSolTitulo];
    } else {
        $sql = "SELECT TOP 1000 codemp, numsol, seqsol, cplpro, qtdsol, presol, unimed, numprj, datsol 
                FROM Sapiens.sapiens.e405sol 
                WHERE sitsol IN (1, 2) 
                ORDER BY numprj ASC, datsol DESC, numsol DESC, seqsol ASC";
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
    $numSimulado = $numSolTitulo ? $numSolTitulo : 1060;
    if ($filtroInstancia) {
         $listaSolicitacoes = [['codemp'=>1, 'numsol'=>$numSimulado, 'seqsol'=>1, 'cplpro'=>'Item 1', 'qtdsol'=>1, 'presol'=>100, 'unimed'=>'UN', 'numprj'=>'PROJ-001', 'datsol'=>$date1]];
    } else {
         $listaSolicitacoes = [['codemp'=>1, 'numsol'=>1060, 'seqsol'=>1, 'cplpro'=>'Item Teste', 'qtdsol'=>1, 'presol'=>25000, 'unimed'=>'UN', 'numprj'=>'INFRA-25', 'datsol'=>$date1]];
    }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 12px; margin-top: 10px; }
    #tabela-solicitacoes { width: 100% !important; }
    .btn-rm-item { background: #fff; border: 1px solid #dc3545; color: #dc3545; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-rm-item:hover { background: #dc3545; color: white; }
    .btn-danger-big { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    .proj-tag { background: #e9ecef; color: #495057; padding: 2px 5px; border-radius: 3px; font-weight: bold; font-size: 11px; }
    .status-ok { color: #28a745; font-weight: bold; font-size: 11px; }
    .status-new { color: #007bff; font-weight: bold; font-size: 11px; }
</style>

<div class="modal-header">
    <?php if ($filtroInstancia): ?>
        <h2>Gerenciar Processo #<?= $filtroInstancia ?></h2>
        <p>Marque novos itens para adicionar ou clique no X para remover.</p>
    <?php else: ?>
        <h2>Iniciar Novo Processo</h2>
        <p>Selecione as solicitações.</p>
    <?php endif; ?>
</div>

<div style="padding: 0 10px;">
    <table id="tabela-solicitacoes" class="display" style="width:100%">
        <thead>
            <tr>
                <th width="30">
                    <?php if (!$filtroInstancia): ?><input type="checkbox" id="chk-todos-erp"><?php endif; ?>
                </th>
                <th width="100">Projeto</th>
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
                $seqsol = $sol['seqsol'];
                $chaveCheck = $numsol . '-' . $seqsol;
                
                $idDono = $itensVinculados[$chaveCheck] ?? null;
                $vinculadoAqui = ($filtroInstancia && $idDono == $filtroInstancia);
                $vinculadoOutro = ($idDono && $idDono != $filtroInstancia);
                
                $chaveEnvio = $sol['codemp'] . '-' . $numsol . '-' . $seqsol;
                
                $dataFmt = ''; $dataOrder = '';
                if (isset($sol['datsol']) && $sol['datsol'] instanceof DateTime) {
                    $dataFmt = $sol['datsol']->format('d/m/Y');
                    $dataOrder = $sol['datsol']->format('YmdHis');
                }
                $precoFmt = number_format($sol['presol'] ?? 0, 2, ',', '.');
            ?>
            <tr class="<?= ($vinculadoAqui || $vinculadoOutro) ? 'row-disabled' : '' ?>">
                <td class="text-center">
                    <?php if ($vinculadoAqui): ?>
                        <button type="button" 
                                class="btn-rm-item js-rm-item" 
                                data-num="<?= $numsol ?>" 
                                data-seq="<?= $seqsol ?>" 
                                data-chave="<?= $chaveEnvio ?>"
                                title="Remover item">
                            &times;
                        </button>
                    <?php elseif ($vinculadoOutro): ?>
                        <span style="color:#ccc">🔒</span>
                    <?php else: ?>
                        <input type="checkbox" name="selecionados[]" value="<?= $chaveEnvio ?>" class="chk-item-erp">
                    <?php endif; ?>
                </td>
                
                <td><span class="proj-tag"><?= $sol['numprj'] ?></span></td>
                <td data-order="<?= $dataOrder ?>"><?= $dataFmt ?></td>
                <td><b><?= $numsol ?></b>-<?= $seqsol ?></td>
                <td><?= $sol['cplpro'] ?><br><small>Qtd: <?= number_format($sol['qtdsol'], 2, ',', '.') ?> <?= $sol['unimed'] ?></small></td>
                <td>R$ <?= $precoFmt ?></td>
                
                <td class="col-status">
                    <?php if ($vinculadoAqui): ?>
                        <span class="status-ok">Vinculado</span>
                    <?php elseif ($vinculadoOutro): ?>
                        <span style="color:#999; font-size:10px">Em Proc. #<?= $idDono ?></span>
                    <?php else: ?>
                        <span class="status-new">Disponível</span>
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
            <button class="btn-danger-big" id="btn-cancelar-tudo" data-id="<?= $filtroInstancia ?>">Excluir Processo Inteiro</button>
        <?php endif; ?>
    </div>
    <div>
        <button class="btn-cancel" id="btn-fechar-modal-erp">Fechar</button>
        <button class="btn-save" id="btn-gerar-processo-erp">
            <?= $filtroInstancia ? 'Salvar Novos Itens' : 'Gerar Processos' ?>
        </button>
    </div>
</div>

<script>
(function() {
    function initDataTable() {
        $('#tabela-solicitacoes').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
            "pageLength": 10,
            "order": [[ 1, "asc" ], [ 2, "desc" ]], 
            "columnDefs": [ { "orderable": false, "targets": 0 } ]
        });
        $('#chk-todos-erp').on('click', function(){
            var rows = $('#tabela-solicitacoes').DataTable().rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
        });
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

            if (checkedInputs.length === 0) { alert('Selecione ao menos um item novo.'); return; }

            btnGerar.disabled = true; btnGerar.innerText = "Salvando...";
            const formData = new FormData();
            formData.append('acao', 'vincular');
            checkedInputs.each(function() { formData.append('selecionados[]', this.value); });

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) { 
                    alert(res.msg); 
                    document.getElementById('modal-overlay').classList.add('hidden'); 
                } else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Falha: ' + err.message); } 
            finally { btnGerar.disabled = false; btnGerar.innerText = "Salvar / Gerar"; }
        });
    }

    // A CORREÇÃO VISUAL + LÓGICA ESTÁ AQUI
    $('#tabela-solicitacoes tbody').on('click', '.js-rm-item', async function() {
        const btn = $(this);
        const numSol = btn.data('num'); // Agora pega o NUMSOL corretamente
        const seqSol = btn.data('seq');
        const chaveEnvio = btn.data('chave');
        const idProc = '<?= $filtroInstancia ?>'; 

        if (!confirm('Remover o item ' + seqSol + ' da Sol. ' + numSol + '?')) return;

        const formData = new FormData();
        formData.append('acao', 'remover_item');
        formData.append('id_processo', idProc);
        formData.append('num_solicitacao', numSol);
        formData.append('seq_solicitacao', seqSol);

        try {
            const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
            
            // Tratamento de erro 500
            let res;
            const text = await req.text();
            try { res = JSON.parse(text); } 
            catch(e) { alert("Erro fatal no servidor:\n" + text); return; }

            if (res.sucesso) {
                // Atualização Visual Imediata (Sem reload)
                var row = btn.closest('tr');
                row.removeClass('row-disabled'); 
                
                var cellAcao = btn.parent();
                cellAcao.html('<input type="checkbox" name="selecionados[]" value="' + chaveEnvio + '" class="chk-item-erp">');

                var cellStatus = row.find('.col-status');
                if(cellStatus.length === 0) cellStatus = row.find('td:last');
                cellStatus.html('<span class="status-new">Disponível</span>');

            } else { alert('Erro: ' + res.erro); }
        } catch (err) { alert('Erro: ' + err.message); }
    });

    const btnDeletarTudo = document.getElementById('btn-cancelar-tudo');
    if (btnDeletarTudo) {
        btnDeletarTudo.addEventListener('click', async function() {
            const idProc = this.dataset.id;
            if (!confirm('ATENÇÃO: Deseja excluir o PROCESSO INTEIRO?')) return;
            const formData = new FormData();
            formData.append('acao', 'cancelar_processo');
            formData.append('id_processo', idProc);
            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) { alert('Processo excluído.'); window.location.href = '/'; } 
                else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Erro: ' + err.message); }
        });
    }
})();
</script>