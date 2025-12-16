<?php
// backend/views/dispensa_resumo.php
// Apenas recebe o ID, sem conectar no banco aqui
$idProcesso = $_GET['instance_id'] ?? null;
?>

<div class="modal-header">
    <h2>Dispensa de Procedimento</h2>
    <p>Conferência dos dados para emissão do Termo de Ratificação.</p>
</div>

<div style="padding: 20px; max-height: 70vh; overflow-y: auto;">

    <div style="background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <h4 style="margin-top:0; font-size:14px; color:#555; text-transform:uppercase; border-bottom:1px solid #ddd; padding-bottom:5px;">Dados Editáveis</h4>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label style="font-size:12px; font-weight:bold; display:block">Número Informação AJ (Jurídico):</label>
                <input type="text" id="info_aj" class="form-control" placeholder="Carregando..." style="width:100%; padding:5px;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:bold; display:block">Artigo Base (Regulamento):</label>
                <input type="text" id="artigo_base" class="form-control" value="14º" style="width:100%; padding:5px;">
            </div>
             <div>
                <label style="font-size:12px; font-weight:bold; display:block">Inciso Base:</label>
                <input type="text" id="inciso_base" class="form-control" value="Inciso IV" style="width:100%; padding:5px;">
            </div>
             <div>
                <label style="font-size:12px; font-weight:bold; display:block">Valor Total:</label>
                <input type="text" id="display_valor" disabled value="..." style="width:100%; padding:5px; background:#e9ecef; border:1px solid #ccc;">
            </div>
        </div>
        <div style="text-align:right; margin-top:10px;">
            <button id="btn-atualizar-texto" class="btn-small" style="background:#6c757d; color:white; border:none; padding:5px 10px; cursor:pointer;">🔄 Reaplicar no Texto</button>
        </div>
    </div>

    <div id="papel-documento" style="border: 1px solid #999; padding: 30px; background: white; font-family: 'Times New Roman', serif; color: black; box-shadow: 0 0 10px rgba(0,0,0,0.1); opacity: 0.5;">
        
        <div style="text-align:center; margin-bottom:20px;">
            <strong>DISPENSA DE PROCEDIMENTO</strong><br>
            <span style="font-size: 12px;">Processo Nº <span id="txt-proc-id">...</span>/<span id="txt-ano">...</span></span>
        </div>

        <p style="text-align: justify; line-height: 1.6;">
            Uma vez cumprida a exigência contida no <strong>Parágrafo 2º do Artigo 15º</strong> do Regulamento de Compras, 
            consubstanciada na Informação AJ nº <span class="var-aj" style="color:blue; font-weight:bold;">_____</span>, 
            autorizando a dispensa do procedimento, com fundamento no 
            <span class="var-inciso" style="color:blue; font-weight:bold;">Inciso IV</span> do 
            <span class="var-artigo" style="color:blue; font-weight:bold;">Artigo 14º</span> do referido Regulamento, 
            encaminho o presente processo, propondo a <strong>Ratificação da Dispensa de Procedimento</strong> 
            para a aquisição fornecida pela empresa <strong><span id="txt-fornecedor">...</span></strong>, 
            pelo valor total de <strong>R$ <span id="txt-valor">...</span></strong>.
        </p>

        <br><br>
        
        <div style="margin-bottom: 20px;">
            Ao Senhor Diretor Presidente com proposta de ratificação, na forma do Parágrafo 2º do Artigo 15º do citado Regulamento.
        </div>

        <div style="margin-top: 40px; text-align: center;">
            ________________________________________<br>
            <strong>GERENTE ADMINISTRATIVO E FINANCEIRO</strong>
        </div>

        <hr style="margin: 30px 0; border-top: 2px dashed #ccc;">

        <div style="text-align:center; margin-bottom:20px;">
            <strong>DESPACHO DA PRESIDÊNCIA</strong>
        </div>

        <p style="text-align: justify; line-height: 1.6;">
            Tendo em vista as manifestações contidas no presente processo, e a vista do disposto no 
            Parágrafo 2º do Artigo 15º do Regulamento de Compras da Fundunesp, 
            <strong>RATIFICO</strong> a dispensa do procedimento com base no 
            <span class="var-inciso-2">Inciso IV</span> do 
            <span class="var-artigo-2">Artigo 14º</span> do citado Regulamento.
        </p>

        <div style="margin-top: 40px; text-align: center;">
            ________________________________________<br>
            <strong>DIRETOR PRESIDENTE</strong>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar">Fechar</button>
    <button class="btn-save" id="btn-salvar-dispensa" disabled style="background:#28a745; color:white; opacity: 0.5; cursor: not-allowed;">
        Carregando...
    </button>
</div>

<script>
(function() {
    const idProcesso = '<?= $idProcesso ?>';
    let dadosAPI = null;

    // 1. CHAMA A API AO ABRIR
    async function carregarDadosAPI() {
        try {
            const req = await fetch(`/backend/api_dispensa_resumo.php?instance_id=${idProcesso}`);
            const res = await req.json();

            if (res.sucesso) {
                dadosAPI = res.dados;
                preencherTela();
            } else {
                alert('Erro ao carregar dados: ' + res.erro);
            }
        } catch (e) {
            alert('Erro de conexão com API.');
            console.error(e);
        }
    }

    // 2. PREENCHE A TELA COM OS DADOS
    function preencherTela() {
        if (!dadosAPI) return;

        // Preenche Inputs
        document.getElementById('display_valor').value = "R$ " + dadosAPI.valor_fmt;
        document.getElementById('info_aj').placeholder = "Digite o nº do Parecer...";

        // Preenche Texto do Documento
        document.getElementById('txt-proc-id').innerText = dadosAPI.processo_senior;
        document.getElementById('txt-ano').innerText = dadosAPI.ano;
        document.getElementById('txt-fornecedor').innerText = dadosAPI.fornecedor_nome;
        document.getElementById('txt-valor').innerText = dadosAPI.valor_fmt;

        // Libera a tela visualmente
        document.getElementById('papel-documento').style.opacity = '1';
        const btn = document.getElementById('btn-salvar-dispensa');
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.innerText = "✅ Confirmar Dados";

        atualizarTextoDinamico();
    }

    // 3. ATUALIZA TEXTO CONFORME DIGITAÇÃO
    function atualizarTextoDinamico() {
        const aj = document.getElementById('info_aj').value || '_____';
        const artigo = document.getElementById('artigo_base').value || '...';
        const inciso = document.getElementById('inciso_base').value || '...';

        document.querySelector('.var-aj').innerText = aj;
        
        document.querySelectorAll('.var-artigo, .var-artigo-2').forEach(el => el.innerText = 'Artigo ' + artigo);
        document.querySelectorAll('.var-inciso, .var-inciso-2').forEach(el => el.innerText = inciso);
    }

    // EVENTOS
    document.getElementById('btn-atualizar-texto').addEventListener('click', atualizarTextoDinamico);
    document.getElementById('info_aj').addEventListener('input', atualizarTextoDinamico);
    document.getElementById('artigo_base').addEventListener('input', atualizarTextoDinamico);
    document.getElementById('inciso_base').addEventListener('input', atualizarTextoDinamico);

    document.getElementById('btn-fechar').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    document.getElementById('btn-salvar-dispensa').addEventListener('click', async function() {
        const aj = document.getElementById('info_aj').value;
        if (!aj) { 
            alert('Por favor, informe o número da Informação AJ (Parecer Jurídico) antes de confirmar.'); 
            document.getElementById('info_aj').focus();
            return; 
        }

        if (!confirm('Os dados conferem? Isso habilitará a geração do documento final.')) return;

        // Aqui você pode chamar outra API para salvar o número do parecer no banco
        alert('Dados confirmados com sucesso!');
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // Inicia
    carregarDadosAPI();
})();
</script>