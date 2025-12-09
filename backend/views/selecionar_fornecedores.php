<?php
require '../db_conexao.php';

$idProcesso = $_GET['instance_id'] ?? null;

if (!$idProcesso) {
    echo "<div style='padding:20px; color:red'>Erro: Processo não identificado.</div>";
    exit;
}

// Busca Info Header
$numSolSenior = "---";
try {
    $stmt = $pdo->prepare("SELECT id_processo_senior FROM processos_instancia WHERE id = ?");
    $stmt->execute([$idProcesso]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res && $res['id_processo_senior']) $numSolSenior = $res['id_processo_senior'];
} catch (Exception $e) {}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper { font-size: 13px; margin-top: 10px; }
    #tb-fornecedores { width: 100% !important; }
    
    .badge-ok { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size:11px; font-weight:bold; }
    .badge-dispo { color: #999; font-size:11px; }
    
    /* Feedback visual de loading na linha */
    .row-loading { opacity: 0.5; pointer-events: none; background: #f8f9fa; }
</style>

<div class="modal-header">
    <h2>Selecionar Fornecedores</h2>
    <p>
        Processo Workflow: <strong>#<?= $idProcesso ?></strong> | 
        Solicitação Senior: <strong style="color:#0056b3">#<?= $numSolSenior ?></strong>
    </p>
    <p style="font-size: 12px; color: #666;">
        <i style="color:#007bff">ℹ️</i> Marque ou desmarque as caixas para adicionar/remover fornecedores instantaneamente.
    </p>
</div>

<div style="padding: 0 10px;">
    <table id="tb-fornecedores" class="display">
        <thead>
            <tr>
                <th width="30">#</th>
                <th>Razão Social / Nome</th>
                <th width="140">CNPJ / CPF</th>
                <th width="150">Cidade/UF</th>
                <th width="100">Status</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-close-forn">Fechar / Concluir</button>
</div>

<script>
(function() {
    var table;

    function initDT() {
        table = $('#tb-fornecedores').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "/backend/api_fornecedores.php",
                "data": function (d) { d.instance_id = "<?= $idProcesso ?>"; }
            },
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
            "pageLength": 10,
            "order": [[ 1, "asc" ]], 
            "columns": [
                { "data": "acao", "orderable": false },
                { "data": "nome" },
                { "data": "doc" },
                { "data": "cidade" },
                { "data": "status" }
            ]
        });
    }

    if (typeof jQuery == 'undefined') {
        var s = document.createElement("script"); s.src = "https://code.jquery.com/jquery-3.7.0.min.js";
        s.onload = function() {
            var sd = document.createElement("script"); sd.src = "https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js";
            sd.onload = initDT; document.head.appendChild(sd);
        }; document.head.appendChild(s);
    } else { initDT(); }

    document.getElementById('btn-close-forn').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // --- LÓGICA DE TOGGLE INTELIGENTE ---
    $('#tb-fornecedores tbody').on('change', '.chk-forn', async function() {
        const checkbox = $(this);
        const isChecked = checkbox.is(':checked');
        const row = checkbox.closest('tr');
        const cellStatus = row.find('td:last'); // Coluna Status
        
        // Dados
        const dadosJson = checkbox.val(); // Value tem o JSON completo {cod, nome, doc}
        const codFornecedor = checkbox.data('cod');
        
        // Feedback visual imediato (Bloqueia linha enquanto processa)
        row.addClass('row-loading');

        const fd = new FormData();
        fd.append('id_processo', '<?= $idProcesso ?>');

        let urlDestino = '';
        
        if (isChecked) {
            // ADICIONAR
            fd.append('acao', 'salvar_participantes');
            fd.append('participantes[]', dadosJson); // Backend espera array de JSONs
        } else {
            // REMOVER
            fd.append('acao', 'remover_participante');
            fd.append('cod_fornecedor', codFornecedor);
        }

        try {
            const req = await fetch('/backend/acoes/gerenciar_participantes.php', { method: 'POST', body: fd });
            const res = await req.json();

            if (res.sucesso) {
                // Atualiza Status visualmente sem reload total
                if (isChecked) {
                    cellStatus.html('<span class="badge-ok">Selecionado</span>');
                } else {
                    cellStatus.html('<span class="badge-dispo">Disponível</span>');
                }
            } else {
                // Erro: Desfaz a marcação visual
                alert('Erro: ' + res.erro);
                checkbox.prop('checked', !isChecked); // Volta ao estado anterior
            }
        } catch (err) {
            console.error(err);
            alert('Erro de comunicação.');
            checkbox.prop('checked', !isChecked);
        } finally {
            row.removeClass('row-loading');
        }
    });

})();
</script>