<h2>Selecionar Fornecedores</h2>
<p>Selecione o fornecedor para gerar o artefato de cotação.</p>

<form id="form-cotacao">
    <input type="hidden" name="id_processo" value="PROCESSO-2025-888">
    
    <ul>
        <li>
            <label>
                <input type="radio" name="fornecedor" value="Kalunga Comércio" checked> 
                Kalunga Comércio
            </label>
        </li>
        <li>
            <label>
                <input type="radio" name="fornecedor" value="Dell Computadores"> 
                Dell Computadores
            </label>
        </li>
    </ul>

    <button type="button" id="btn-gerar-doc" class="btn-primary">Gerar Documento de Cotação</button>
</form>

<script>
    // Script local da View (que será carregado no Modal)
    document.getElementById('btn-gerar-doc').addEventListener('click', async () => {
        
        const form = document.getElementById('form-cotacao');
        const formData = new FormData(form);
        const fornecedorSelecionado = formData.get('fornecedor');
        const idProcesso = formData.get('id_processo');

        // Monta o objeto de dados (incluindo itens simulados)
        const dadosParaEnvio = {
            id_processo: idProcesso,
            fornecedor: fornecedorSelecionado,
            itens: [
                { nome: 'Notebook i7', qtd: '2', desc: 'Dell Latitude' },
                { nome: 'Mouse USB', qtd: '5', desc: 'Logitech Basic' }
            ]
        };

        // Chama o PHP gerador
        const btn = document.getElementById('btn-gerar-doc');
        btn.innerText = "Gerando...";
        btn.disabled = true;

        try {
            // Atenção: A URL deve bater com seu proxy no vite.config.js
            const response = await fetch('backend/gerar_artefato.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(dadosParaEnvio)
            });

            const resultado = await response.json();

            if (resultado.sucesso) {
                alert('Documento Gerado: ' + resultado.arquivo);
                // Aqui você poderia abrir o link para download
                // window.open('backend/output/' + resultado.arquivo);
            } else {
                alert('Erro: ' + resultado.erro);
            }

        } catch (err) {
            alert('Erro na comunicação: ' + err.message);
        } finally {
            btn.innerText = "Gerar Documento de Cotação";
            btn.disabled = false;
        }
    });
</script>

<style>
    .btn-primary { background: #007bff; color: white; padding: 10px; border: none; cursor: pointer; margin-top: 10px;}
    .btn-primary:disabled { background: #ccc; }
</style>