<?php
$idProcesso = $_GET['instance_id'] ?? null;
?>

<style>
    .memo-container { display: flex; flex-direction: column; gap: 20px; padding: 10px; max-height: 65vh; overflow-y: auto; }
    .card-memo { border: 1px solid #ddd; border-radius: 8px; background: #fff; margin-bottom: 20px; }
    .card-header-memo { background: #e3f2fd; padding: 15px; display: flex; justify-content: space-between; align-items: center; color: #0d47a1; border-bottom: 1px solid #bbdefb; }
    
    .table-memo { width: 100%; border-collapse: collapse; font-size: 13px; }
    .table-memo th { background: #fff; border-bottom: 2px solid #eee; text-align: left; padding: 10px; color: #555; }
    .table-memo td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    
    .badge-ok { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
    .row-disabled { background-color: #f9f9f9; color: #999; }
    
    .btn-gerar { background: #009688; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; }
    .btn-gerar:hover { background: #00796b; }
</style>

<div class="modal-header">
    <h2>Gerar Memorando (Por Nota Fiscal)</h2>
    <p>Selecione os itens constantes na Nota Fiscal recebida para gerar o pagamento.</p>
</div>

<div id="container-memos" class="memo-container">
    <div style="text-align:center; padding:40px; color:#666">Carregando itens...</div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-memo">Fechar</button>
</div>

<script>
(function() {
    const idProcesso = '<?= $idProcesso ?>';
    const container = document.getElementById('container-memos');

    async function carregarDados() {
        try {
            const req = await fetch(`/backend/api_gerar_memorando.php?instance_id=${idProcesso}`);
            const dados = await req.json();

            if (dados.erro) {
                container.innerHTML = `<div style="color:red; padding:20px">${dados.erro}</div>`;
                return;
            }
            if (dados.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding:40px">Nenhum item vencedor encontrado.</div>`;
                return;
            }
            renderizar(dados);
        } catch (err) {
            container.innerHTML = 'Erro na API.';
        }
    }

    function renderizar(lista) {
        container.innerHTML = '';

        lista.forEach(grupo => {
            const forn = grupo.fornecedor;
            const div = document.createElement('div');
            div.className = 'card-memo';
            
            let htmlItens = '';
            let temPendencias = false; // Controle para saber se mostra o botão

            grupo.itens.forEach(item => {
                const isDone = (item.status === 'CONCLUIDO');
                const rowClass = isDone ? 'row-disabled' : '';
                
                if (!isDone) temPendencias = true;

                // Coluna de Ação: Checkbox ou Badge "OK"
                let inputHtml = '';
                if (isDone) {
                    inputHtml = '<span class="badge-ok">Já Pago</span>';
                } else {
                    // Checkbox value carrega todos os dados necessários
                    const dadosItem = JSON.stringify({
                        num: item.num,
                        seq: item.seq,
                        qtd: item.qtd,
                        preco: item.preco
                    });
                    inputHtml = `<input type="checkbox" class="chk-item" value='${dadosItem}'>`;
                }

                htmlItens += `
                    <tr class="${rowClass}">
                        <td width="50" style="text-align:center">${inputHtml}</td>
                        <td>
                            <span style="font-weight:bold">${item.produto}</span><br>
                            <small>Sol: ${item.num}-${item.seq}</small>
                        </td>
                        <td>${item.qtd} ${item.unid}</td>
                        <td>R$ ${parseFloat(item.total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });

            // Só mostra botão se tiver itens pendentes
            const btnHtml = temPendencias 
                ? `<button class="btn-gerar js-btn-gerar" data-forn="${forn.id_fornecedor_senior}">Gerar Memorando</button>`
                : `<span style="font-size:12px; color:#28a745">✅ Todos os itens processados</span>`;

            div.innerHTML = `
                <div class="card-header-memo">
                    <div>
                        <strong>${forn.nome_do_fornecedor}</strong><br>
                        <small>CNPJ: ${forn.cnpj_cpf}</small>
                    </div>
                    <div>${btnHtml}</div>
                </div>
                <table class="table-memo">
                    <thead>
                        <tr>
                            <th>Seleção</th>
                            <th>Produto / Item</th>
                            <th>Qtd</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>${htmlItens}</tbody>
                </table>
            `;
            container.appendChild(div);
        });

        ativarBotoes();
    }

    function ativarBotoes() {
        document.querySelectorAll('.js-btn-gerar').forEach(btn => {
            btn.addEventListener('click', async function() {
                const idForn = this.dataset.forn;
                const card = this.closest('.card-memo');
                const checks = card.querySelectorAll('.chk-item:checked');

                if (checks.length === 0) { alert('Selecione os itens desta Nota Fiscal.'); return; }

                if (!confirm('Gerar Memorando para os itens selecionados?')) return;

                // Coleta dados dos checkboxes
                const itensSelecionados = [];
                checks.forEach(chk => {
                    itensSelecionados.push(JSON.parse(chk.value));
                });

                this.disabled = true; this.innerText = "Processando...";
                
                const fd = new FormData();
                fd.append('acao', 'gerar_memorando');
                fd.append('id_processo', idProcesso);
                fd.append('id_fornecedor', idForn);
                fd.append('itens', JSON.stringify(itensSelecionados));

                try {
                    const req = await fetch('/backend/acoes/salvar_memorando.php', { method: 'POST', body: fd });
                    const res = await req.json();
                    
                    if (res.sucesso) {
                        alert(res.msg);
                        carregarDados(); // Recarrega para bloquear os itens
                    } else {
                        alert('Erro: ' + res.erro);
                        this.disabled = false; this.innerText = "Gerar Memorando";
                    }
                } catch (e) {
                    alert('Erro de conexão.');
                    this.disabled = false;
                }
            });
        });
    }

    document.getElementById('btn-fechar-memo').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    carregarDados();
})();
</script>