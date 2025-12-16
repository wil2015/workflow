<?php
// backend/views/reserva_recursos.php
$idProcesso = $_GET['instance_id'] ?? null;
?>

<style>
    .papel-doc {
        background: white;
        padding: 40px;
        border: 1px solid #ccc;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        font-family: "Times New Roman", serif;
        color: #000;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.5;
    }
    .doc-titulo {
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
    }
    .doc-campo-input {
        border: none;
        border-bottom: 1px dashed #000;
        background: #fdfdfd;
        width: 100%;
        padding: 5px;
        font-family: "Courier New", monospace;
        font-weight: bold;
        color: #0056b3;
        font-size: 16px;
    }
    .doc-campo-input:focus { outline: none; background: #e8f0fe; }
    .doc-assinatura {
        margin-top: 40px;
        text-align: center;
    }
    .doc-rodape {
        font-size: 10px;
        text-align: center;
        margin-top: 50px;
        color: #666;
        border-top: 1px solid #ccc;
        padding-top: 10px;
    }
</style>

<div class="modal-header">
    <h2>Reserva Orçamentária</h2>
    <p>Preencha a previsão de recursos conforme modelo oficial.</p>
</div>

<div style="padding: 20px; background: #e9ecef; overflow-y:auto; max-height:75vh;">
    
    <div class="papel-doc">
        
        <div style="text-align:center; margin-bottom:20px;">
            <span style="font-weight:bold; font-size:18px;">FUNDUNESP</span><br>
            <span style="font-size:12px;">Unidade de Compras e Importação</span>
        </div>

        <div class="doc-titulo">Previsão para Recursos Financeiros</div>

        <p>
            <strong>Referência:</strong> PROCESSO DE COMPRAS Nº <span id="txt-proc-num">...</span><br>
            <strong>Assunto:</strong> Aquisição de materiais/serviços diversos.
        </p>

        <p style="text-align: justify; margin-top: 20px;">
            Venho por meio desta, solicitar de V.S.ª, a gentileza de providenciar a reserva de recursos financeiros no valor total de 
            <strong>R$ <span id="txt-valor">...</span></strong>, referente à aquisição acima mencionada.
        </p>
        
        <p>Após informação, o processo deve retornar à Unidade de Compras.</p>

        <div class="doc-assinatura">
            ____________________________________<br>
            <strong>Solicitante (Automático)</strong><br>
            Unidade de Compras e Importação
        </div>

        <hr style="margin: 30px 0; border: 1px dashed #999;">

        <div style="background-color: #fffde7; padding: 15px; border: 1px solid #f0e68c;">
            <p><strong>INFORMAÇÃO:</strong></p>
            
            <p>
                Informo que há recursos e que as despesas devem ocorrer por conta do processo:
            </p>
            
            <div style="margin: 15px 0;">
                <label style="font-size:12px; font-weight:bold;">C.C.P. Nº / Projeto / Convênio:</label>
                <input type="text" id="dotacao" class="doc-campo-input" placeholder="Digite o nº do C.C.P. ou Projeto (Ex: 3744/2024)...">
            </div>

            <div style="margin: 15px 0;">
                <label style="font-size:12px; font-weight:bold;">Observações Adicionais:</label>
                <textarea id="obs_financas" class="doc-campo-input" style="height: 60px; border:1px solid #ccc;" placeholder="Outras informações..."></textarea>
            </div>
            
            <p>Retorna-se o processo à Unidade de Compras e Importação.</p>
        </div>

        <div class="doc-assinatura">
            ____________________________________<br>
            <strong>Responsável Financeiro / Convênios</strong><br>
            <span style="font-size:11px; color:#666">(Assinatura Eletrônica no Clique do Botão)</span>
        </div>

        <div class="doc-rodape">
            Rua Líbero Badaró, 377 - Centro - São Paulo - SP - www.fundunesp.org.br
        </div>
    </div>
    </div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-cancelar-reserva">Cancelar</button>
    <button class="btn-save" id="btn-confirmar-reserva" disabled style="background-color: #ccc; cursor: not-allowed; padding: 10px 20px; border: none; border-radius: 4px; color: white;">
        Carregando...
    </button>
</div>

<script>
(function() {
    const idProcesso = '<?= $idProcesso ?>';
    const btnConfirmar = document.getElementById('btn-confirmar-reserva');

    // 1. CARREGA DADOS
    async function carregarDocumento() {
        try {
            const req = await fetch(`/backend/api_reserva_recursos.php?instance_id=${idProcesso}`);
            const res = await req.json();

            if (res.sucesso) {
                // Preenche o Texto do Documento
                document.getElementById('txt-proc-num').innerText = res.processo_numero;
                document.getElementById('txt-valor').innerText = res.total_formatado;

                // Preenche inputs se já existir salvo
                if (res.dados_salvos) {
                    document.getElementById('dotacao').value = res.dados_salvos.dotacao || '';
                    document.getElementById('obs_financas').value = res.dados_salvos.observacao || '';
                }

                if (res.total_valor > 0) {
                    habilitarBotao();
                } else {
                    btnConfirmar.innerText = "Sem Valor Consolidado";
                    alert('Aviso: O valor deste processo está zerado. Verifique a Grade Comparativa.');
                }
            }
        } catch (err) {
            console.error(err);
            alert('Erro ao carregar dados do documento.');
        }
    }

    function habilitarBotao() {
        btnConfirmar.disabled = false;
        btnConfirmar.style.backgroundColor = '#28a745';
        btnConfirmar.style.cursor = 'pointer';
        btnConfirmar.innerHTML = '✅ Assinar e Confirmar Reserva';
    }

    // 2. SALVAR
    btnConfirmar.addEventListener('click', async function() {
        const dotacao = document.getElementById('dotacao').value;
        const obs = document.getElementById('obs_financas').value;

        if (!dotacao) {
            alert('É obrigatório informar o número do C.C.P. ou Projeto para prosseguir.');
            document.getElementById('dotacao').focus();
            return;
        }

        if (!confirm('Confirma a disponibilidade de recursos no projeto informado?\nEssa ação registrará sua assinatura eletrônica.')) return;

        this.innerText = "Processando...";
        this.disabled = true;

        const fd = new FormData();
        fd.append('acao', 'salvar_reserva');
        fd.append('id_processo', idProcesso);
        fd.append('dotacao', dotacao); // Aqui vai o C.C.P.
        fd.append('observacao', obs);

        try {
            const req = await fetch('/backend/acoes/gerenciar_reserva.php', { method: 'POST', body: fd });
            const res = await req.json();

            if (res.sucesso) {
                alert('Previsão Financeira registrada com sucesso!');
                document.getElementById('modal-overlay').classList.add('hidden');
                document.getElementById('modal-body').innerHTML = '';
            } else {
                alert('Erro: ' + res.erro);
                habilitarBotao();
            }
        } catch (e) {
            alert('Erro de conexão.');
            habilitarBotao();
        }
    });

    document.getElementById('btn-cancelar-reserva').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    carregarDocumento();
})();
</script>