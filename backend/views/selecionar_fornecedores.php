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
    function initDT() {
        $('#tb-fornecedores').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
            "pageLength": 10,
            "order": [[ 1, "asc" ]],
            "columnDefs": [ { "orderable": false, "targets": 0 } ]
        });

        $('#chk-all-forn').on('click', function(){
            var rows = $('#tb-fornecedores').DataTable().rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
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
                // AQUI: Força reload para mostrar os itens salvos com o botão vermelho
                document.getElementById('modal-overlay').classList.add('hidden');
                // Se preferir reabrir o modal automaticamente, seria mais complexo.
                // O reload da página é o jeito mais seguro de atualizar o estado visual.
                window.location.reload(); 
            } else {
                alert('Erro: ' + res.erro);
            }
        } catch (err) { 
            // Se der erro de JSON, mostra o texto cru para debug
            console.error(err);
            alert('Falha ao salvar. Verifique o console.'); 
        }
        finally { btnSave.disabled = false; btnSave.innerText = "Salvar Seleção"; }
    });

    // Lógica do Botão Vermelho (Remover)
    $('#tb-fornecedores tbody').on('click', '.js-rm-forn', async function() {
        const codFornecedor = $(this).data('cod');
        if (!confirm('Remover este fornecedor da lista de participantes?')) return;

        const fd = new FormData();
        fd.append('acao', 'remover_participante');
        fd.append('id_processo', '<?= $idProcesso ?>');
        fd.append('cod_fornecedor', codFornecedor);

        try {
            const req = await fetch('/backend/acoes/gerenciar_participantes.php', { method: 'POST', body: fd });
            const res = await req.json();
            if (res.sucesso) {
                // Atualiza a tabela (remove a formatação de selecionado)
                // O ideal é recarregar a tela para restaurar o checkbox
                window.location.reload();
            } else { alert('Erro: ' + res.erro); }
        } catch (err) { alert('Erro: ' + err.message); }
    });

})();
</script>