<?php $idProcesso = $_GET['instance_id'] ?? null; ?>

<style>
    .oc-container { display: flex; flex-direction: column; gap: 20px; padding: 10px; max-height: 65vh; overflow-y: auto; }
    .card-fornecedor { border: 1px solid #ddd; border-radius: 8px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .card-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .card-title { font-size: 16px; font-weight: bold; color: #333; }
    .card-subtitle { font-size: 12px; color: #666; margin-top: 4px; }
    
    /* Área de Status do Envio */
    .envio-status { font-size: 11px; margin-right: 10px; text-align: right; }
    .status-ok { color: #28a745; font-weight: bold; }
    .status-pendente { color: #dc3545; }

    .table-oc { width: 100%; border-collapse: collapse; font-size: 13px; }
    .table-oc th { background: #fff; border-bottom: 2px solid #eee; text-align: left; padding: 10px; }
    .table-oc td { padding: 10px; border-bottom: 1px solid #eee; }
    
    .col-num { text-align: right; font-family: monospace; }
    .total-row { background: #f1f8e9; font-weight: bold; text-align: right; padding: 12px; color: #2e7d32; }
    
    .btn-send { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
    .btn-send:hover { background: #0056b3; }
    .btn-send.resent { background: #6c757d; } /* Cor para reenvio */
</style>

<div class="modal-header">
    <h2>Autorização de Fornecimento</h2>
    <p>Envie a autorização oficial para os vencedores.</p>
</div>

<div class="oc-container" id="container-ocs">
    <div style="text-align:center; padding:50px; color:#666">Carregando...</div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-oc">Fechar</button>
</div>

<script>
(function() {
    const idProcesso = '<?= $idProcesso ?>';
    const container = document.getElementById('container-ocs');

    async function carregarAutorizacoes() {
        try {
            const req = await fetch(`/backend/api_autorizacao.php?instance_id=${idProcesso}`);
            const dados = await req.json();

            if (dados.erro) { container.innerHTML = dados.erro; return; }
            if (dados.length === 0) { container.innerHTML = 'Nenhum vencedor.'; return; }

            renderizarCards(dados);
        } catch (err) { console.error(err); }
    }

    function renderizarCards(lista) {
        container.innerHTML = ''; 

        lista.forEach(forn => {
            const card = document.createElement('div');
            card.className = 'card-fornecedor';

            let linhasItens = '';
            forn.itens.forEach(item => {
                linhasItens += `<tr>
                    <td style="color:#666; font-size:11px; width:80px">${item.solicitacao}</td>
                    <td>${item.produto}</td>
                    <td class="col-num">${item.qtd_fmt}</td>
                    <td class="col-num">${item.preco_fmt}</td>
                    <td class="col-num" style="font-weight:bold">${item.total_fmt}</td>
                </tr>`;
            });

            // Lógica de Status de Envio
            let statusHtml = '';
            let btnTexto = '✉️ Enviar Autorização';
            let btnClass = 'btn-send';

            if (forn.data_envio) {
                statusHtml = `<div class="envio-status status-ok">
                                ✔️ Enviado em: ${forn.data_envio}
                              </div>`;
                btnTexto = '🔄 Reenviar';
                btnClass = 'btn-send resent';
            } else {
                statusHtml = `<div class="envio-status status-pendente">
                                ⚠️ Ainda não enviado
                              </div>`;
            }

            card.innerHTML = `
                <div class="card-header">
                    <div>
                        <div class="card-title">${forn.razao_social}</div>
                        <div class="card-subtitle">CNPJ: ${forn.doc}</div>
                    </div>
                    <div style="display:flex; align-items:center">
                        ${statusHtml}
                        <button class="${btnClass} js-enviar-oc" data-forn="${forn.id_fornecedor}">
                            ${btnTexto}
                        </button>
                    </div>
                </div>
                <table class="table-oc">
                    <thead><tr><th>Sol.</th><th>Produto</th><th>Qtd</th><th>Unit.</th><th>Total</th></tr></thead>
                    <tbody>${linhasItens}</tbody>
                </table>
                <div class="total-row">Total: R$ ${forn.total_geral_fmt}</div>
            `;
            container.appendChild(card);
        });

        ativarBotoesEnvio();
    }

    function ativarBotoesEnvio() {
        document.querySelectorAll('.js-enviar-oc').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Confirmar envio?')) return;
                const idForn = this.dataset.forn;
                this.innerText = "Enviando..."; this.disabled = true;

                const fd = new FormData();
                fd.append('acao', 'registrar_envio');
                fd.append('id_processo', idProcesso);
                fd.append('id_fornecedor', idForn);

                try {
                    const req = await fetch('/backend/acoes/enviar_autorizacao.php', { method: 'POST', body: fd });
                    const res = await req.json();
                    if (res.sucesso) {
                        alert(res.msg);
                        carregarAutorizacoes(); // Recarrega para atualizar a data
                    } else { alert(res.erro); this.disabled = false; }
                } catch (e) { alert('Erro.'); this.disabled = false; }
            });
        });
    }

    document.getElementById('btn-fechar-oc').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    carregarAutorizacoes();
})();
</script>