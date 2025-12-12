<?php
// backend/views/enviar_1doc.php
$idProcesso = $_GET['instance_id'] ?? null;
?>

<style>
    .grade-wrapper { overflow-x: auto; margin-top: 10px; border: 1px solid #ddd; max-height: 60vh; }
    .table-grade { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1000px; }
    .table-grade th, .table-grade td { padding: 8px; border: 1px solid #eee; text-align: center; vertical-align: middle; }
    
    .table-grade th { background-color: #f8f9fa; color: #333; font-weight: 600; font-size: 11px; }
    .col-prod { text-align: left !important; width: 300px; min-width: 250px; background: #fff; position: sticky; left: 0; z-index: 2; border-right: 2px solid #ddd !important; }
    
    /* Cores de Status */
    .winner { background-color: #d4edda; color: #155724; border: 2px solid #c3e6cb !important; font-weight: bold; }
    .tie { background-color: #fff3cd; color: #856404; border: 2px solid #ffeeba !important; font-weight: bold; }
    .loser { color: #999; }
    .empty { background-color: #f9f9f9; color: #ccc; }

    .total-box { margin-top: 20px; padding: 15px; background: #e9ecef; text-align: right; font-size: 16px; border-radius: 4px; border-left: 5px solid #28a745; }
    .loading-msg { text-align: center; padding: 40px; color: #666; font-size: 14px; }
</style>

<div class="modal-header">
    <h2>Grade Comparativa</h2>
    <p>Os itens marcados representam o menor preço encontrado.</p>
</div>

<div style="padding: 10px;">
    
    <div id="container-grade">
        <div class="loading-msg">Carregando dados e calculando vencedores...</div>
    </div>

    <div class="total-box" id="box-total" style="display:none">
        <b>Total Estimado:</b> <span id="valor-total">...</span>
    </div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-grade">Voltar</button>
    <button class="btn-save" id="btn-enviar-1doc" disabled>Gerar Documento</button>
</div>

<script>
(function() {
    const idProcesso = '<?= $idProcesso ?>';
    const container = document.getElementById('container-grade');
    const boxTotal = document.getElementById('box-total');
    const spanTotal = document.getElementById('valor-total');
    const btnEnviar = document.getElementById('btn-enviar-1doc');

    // 1. Carrega Dados da API
    async function carregarGrade() {
        try {
            const req = await fetch(`/backend/api_grade_comparativa.php?instance_id=${idProcesso}`);
            const dados = await req.json();

            if (dados.erro) {
                container.innerHTML = `<div style="color:red; padding:20px">${dados.erro}</div>`;
                return;
            }

            if (!dados.cabecalho || dados.cabecalho.length === 0) {
                container.innerHTML = '<div style="padding:20px; text-align:center">⚠️ Nenhum fornecedor participando.</div>';
                return;
            }

            renderizarTabela(dados);

        } catch (err) {
            container.innerHTML = '<div style="color:red; padding:20px">Erro de conexão com API.</div>';
            console.error(err);
        }
    }

    // 2. Renderiza HTML
    function renderizarTabela(dados) {
        let html = `<div class="grade-wrapper"><table class="table-grade">`;
        
        // Cabeçalho
        html += `<thead><tr>`;
        html += `<th class="col-prod">Item / Produto</th>`;
        html += `<th style="width: 90px; background:#e2e6ea;">Melhor Preço</th>`;
        
        dados.cabecalho.forEach(f => {
            html += `<th title="${f.nome_completo}">
                        ${f.nome_curto}<br>
                        <small style="font-weight:normal; color:#666">Cód: ${f.id}</small>
                     </th>`;
        });
        html += `</tr></thead><tbody>`;

        // Linhas
        dados.linhas.forEach(linha => {
            html += `<tr>`;
            // Coluna Produto Fixa
            html += `<td class="col-prod">
                        <span style="font-weight:bold; color:#555; font-size:10px">${linha.chave}</span><br>
                        ${linha.produto}<br>
                        <small>Qtd: ${linha.qtd_fmt}</small>
                     </td>`;
            
            // Coluna Melhor Preço
            html += `<td style="background:#f1f3f5; font-weight:bold;">${linha.melhor_fmt}</td>`;

            // Colunas Fornecedores
            dados.cabecalho.forEach(f => {
                const celula = linha.celulas[f.id];
                let conteudo = celula.valor_fmt;
                
                // Adiciona badge se for vencedor/empate
                if (celula.status === 'winner') conteudo += `<br><span style='font-size:9px'>★ VENCEU</span>`;
                if (celula.status === 'tie')    conteudo += `<br><span style='font-size:9px'>EMPATE</span>`;

                html += `<td class="${celula.status}">${conteudo}</td>`;
            });

            html += `</tr>`;
        });

        html += `</tbody></table></div>`;
        
        container.innerHTML = html;
        spanTotal.innerText = "R$ " + dados.total_fmt;
        boxTotal.style.display = 'block';
        btnEnviar.disabled = false;
    }

    // Inicializa
    carregarGrade();

    // Eventos
    document.getElementById('btn-fechar-grade').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });
    
    btnEnviar.addEventListener('click', function() {
        alert('Aqui chamaria a integração com 1Doc...');
    });
// ... código anterior ...

    document.getElementById('btn-enviar-1doc').addEventListener('click', async function() {
        if (!confirm('Deseja consolidar os vencedores e gerar o documento?')) return;
        
        // Botão Feedback
        this.disabled = true; this.innerText = "Salvando...";

        const fd = new FormData();
        fd.append('acao', 'consolidar_vencedores');
        fd.append('id_processo', '<?= $idProcesso ?>');

        try {
            // CHAMA A AÇÃO DE SALVAR
            const req = await fetch('/backend/acoes/consolidar_grade.php', { method: 'POST', body: fd });
            const res = await req.json();

            if (res.sucesso) {
                alert('Valores salvos! O processo seguirá para Reserva de Recursos.\nValor Final: R$ ' + res.valor_gravado);
                document.getElementById('modal-overlay').classList.add('hidden');
                document.getElementById('modal-body').innerHTML = '';
            } else {
                alert('Erro ao salvar: ' + res.erro);
                this.disabled = false;
            }
        } catch (e) {
            alert('Erro de conexão.');
            this.disabled = false;
        }
    });
})();
</script>