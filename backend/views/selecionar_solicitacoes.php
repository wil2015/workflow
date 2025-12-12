<?php
require  '../db_conexao.php';
$idFluxo = $_GET['fluxo_id'] ?? 1; // Pega da URL ou usa 1 se falhar

$filtroInstancia = $_GET['instance_id'] ?? null;
if ($filtroInstancia === 'null' || $filtroInstancia === '') $filtroInstancia = null;

$numSolTitulo = "---";
if ($filtroInstancia) {
    try {
        $stmt = $pdo->prepare("SELECT id_processo_senior FROM processos_instancia WHERE id = ?");
        $stmt->execute([$filtroInstancia]);
        $res = $stmt->fetch();
        if ($res && $res['id_processo_senior']) $numSolTitulo = $res['id_processo_senior'];
    } catch (Exception $e) { }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 12px; margin-top: 10px; }
    #tabela-solicitacoes { width: 100% !important; }
    .btn-rm-item { background: #fff; border: 1px solid #dc3545; color: #dc3545; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-rm-item:hover { background: #dc3545; color: white; }
    .btn-danger-big { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    .proj-tag { background: #e9ecef; color: #495057; padding: 2px 5px; border-radius: 3px; font-weight: bold; font-size: 11px; white-space: nowrap; }
    .status-ok { color: #28a745; font-weight: bold; font-size: 11px; }
    .status-new { color: #007bff; font-weight: bold; font-size: 11px; }
    
    /* Cursor de mão nos cabeçalhos ordenáveis */
    th.sorting, th.sorting_asc, th.sorting_desc { cursor: pointer; }
</style>
<input type="hidden" name="id_fluxo_definicao" value="<?= $idFluxo ?>">
<div class="modal-header">
    <?php if ($filtroInstancia): ?>
        <h2>Gerenciar Processo #<?= $filtroInstancia ?></h2>
        <p>Solicitação Principal: <strong>#<?= $numSolTitulo ?></strong></p>
    <?php else: ?>
        <h2>Iniciar Novo Processo</h2>
        <p>Selecione as solicitações.</p>
    <?php endif; ?>
</div>

<div style="padding: 0 10px;">
    <table id="tabela-solicitacoes" class="display" style="width:100%">
        <thead>
            <tr>
                <th width="30"><input type="checkbox" id="chk-todos-erp" disabled></th>
                <th width="100">Projeto</th>
                <th width="80">Data</th>
                <th width="80">Solicitação</th>
                <th>Produto / Descrição</th>
                <th width="100">Preço Unit.</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody></tbody>
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
    var table;

    function initDataTable() {
        table = $('#tabela-solicitacoes').DataTable({
            "processing": true,
            "serverSide": true,
            "ordering": true, // ATIVA A ORDENAÇÃO
            "ajax": {
                "url": "/backend/api_solicitacoes.php",
                "data": function (d) { d.instance_id = "<?= $filtroInstancia ?>"; }
            },
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
            "pageLength": 10,
            
            // Ordem Inicial: Projeto DESC
            // (Mas o backend vai forçar os Selecionados para o topo primeiro)
            "order": [[ 1, "desc" ]], 
            
            "columns": [
                { "data": "acao", "orderable": false }, // 0: Ação (Sem ordenar)
                { "data": "projeto" },                  // 1: Projeto
                { "data": "data" },                     // 2: Data
                { "data": "sol" },                      // 3: Solicitação
                { "data": "prod" },                     // 4: Produto
                { "data": "preco" },                    // 5: Preço
                { "data": "status", "orderable": false } // 6: Status (Sem ordenar)
            ]
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
            var checked = $('#tabela-solicitacoes input.chk-item-erp:checked');
            if (checked.length === 0) { alert('Selecione ao menos um item.'); return; }

            btnGerar.disabled = true; btnGerar.innerText = "Salvando...";
            const formData = new FormData();
            formData.append('acao', 'vincular');
            checked.each(function() { formData.append('selecionados[]', $(this).val()); });

            try {
                const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
                const res = await req.json();
                if (res.sucesso) { alert(res.msg); table.ajax.reload(null, false); } 
                else { alert('Erro: ' + res.erro); }
            } catch (err) { alert('Falha: ' + err.message); } 
            finally { btnGerar.disabled = false; btnGerar.innerText = "Salvar / Gerar"; }
        });
    }

    $('#tabela-solicitacoes tbody').on('click', '.js-rm-item', async function() {
        const btn = $(this);
        const numSol = btn.data('num');
        const seqSol = btn.data('seq');
        const idProc = '<?= $filtroInstancia ?>'; 

        if (!confirm('Remover o item ' + seqSol + ' da Sol. ' + numSol + '?')) return;

        const formData = new FormData();
        formData.append('acao', 'remover_item');
        formData.append('id_processo', idProc);
        formData.append('num_solicitacao', numSol);
        formData.append('seq_solicitacao', seqSol);

        try {
            const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
            const res = await req.json();
            if (res.sucesso) { table.ajax.reload(null, false); } 
            else { alert('Erro: ' + res.erro); }
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