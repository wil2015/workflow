<template>
  <div class="cotacao-wrapper">
    <div class="header-cotacao">
      <h2>Lançamento de Valores</h2>
      <p>Selecione um item à esquerda para informar os preços.</p>
    </div>

    <div class="layout-split">
      <div class="lista-itens">
        <div v-if="loadingItens" class="loading-msg">Carregando itens...</div>
        
        <div 
          v-for="item in itens" 
          :key="`${item.num}-${item.seq}`"
          class="item-row"
          :class="{ active: itemAtual && itemAtual.num === item.num && itemAtual.seq === item.seq }"
          @click="selecionarItem(item)"
        >
          <div class="item-cod">Sol: {{ item.num }}-{{ item.seq }}</div>
          <div class="item-desc">{{ item.desc }}</div>
          <div class="item-qtd">{{ item.qtd }} {{ item.unid }}</div>
        </div>
      </div>

      <div class="detalhe-item">
        <div v-if="!itemAtual" class="empty-state">
          ← Clique em um item para lançar valores.
        </div>

        <div v-else class="form-container">
          <div class="form-header">
             <h3 class="titulo-item">Item {{ itemAtual.num }}-{{ itemAtual.seq }}</h3>
             <span class="badge-status" v-if="modificado">⚠️ Alterações não salvas</span>
          </div>
          
          <div v-if="loadingCotacao" class="loading-msg">Carregando fornecedores...</div>
          
          <div v-else class="lista-inputs">
            <div v-for="forn in fornecedores" :key="forn.cod" class="form-group">
              <div class="forn-info">
                <strong>{{ forn.nome }}</strong>
                <small>Cód: {{ forn.cod }}</small>
              </div>
              <div class="input-wrapper">
                <span>R$</span>
                
                <input 
                  type="text" 
                  class="input-money"
                  :value="forn.valor_temp !== undefined ? forn.valor_temp : formatMoney(forn.valor)"
                  @input="aplicarMascara($event, forn)"
                  placeholder="0,00"
                />
              </div>
            </div>
          </div>

          <div class="form-footer" v-if="!loadingCotacao && fornecedores.length > 0">
              <button 
                class="btn-salvar" 
                :disabled="salvando" 
                @click="salvarTudoManual"
              >
                <span v-if="salvando">💾 Salvando...</span>
                <span v-else>✅ Confirmar e Salvar Valores</span>
              </button>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const instanceId = ref(null);
const itens = ref([]);
const itemAtual = ref(null);
const fornecedores = ref([]);
const loadingItens = ref(true);
const loadingCotacao = ref(false);
const salvando = ref(false);
const modificado = ref(false); // Flag para avisar usuário que tem coisa pendente

// Aponta para o novo Controller Modulado
const API_URL = '/backend/modulos/Cotacao/CotacaoController.php';

onMounted(() => {
    // Recupera ID do objeto global ou URL
    if (window.VIEW_DATA && window.VIEW_DATA.instance_id) {
        instanceId.value = window.VIEW_DATA.instance_id;
    } else {
        instanceId.value = new URLSearchParams(window.location.search).get('instance_id');
    }
    carregarItens();
});

async function carregarItens() {
    try {
        const req = await fetch(`${API_URL}?acao=listar_itens&instance_id=${instanceId.value}`);
        const res = await req.json();
        
        if (res.erro) throw new Error(res.erro);
        itens.value = res;
    } catch (e) {
        alert("Erro ao carregar itens: " + e.message);
    } finally {
        loadingItens.value = false;
    }
}

async function selecionarItem(item) {
    // Proteção se tentar trocar de item sem salvar
    if (modificado.value) {
        if(!confirm("Você tem alterações não salvas. Deseja descartar e trocar de item?")) return;
    }

    itemAtual.value = item;
    loadingCotacao.value = true;
    modificado.value = false; // Reseta estado de edição
    fornecedores.value = [];

    try {
        const url = `${API_URL}?acao=listar_cotacoes&id_processo=${instanceId.value}&num=${item.num}&seq=${item.seq}`;
        const req = await fetch(url);
        const res = await req.json();
        
        if (res.erro) throw new Error(res.erro);
        fornecedores.value = res;
    } catch (e) {
        alert("Erro ao carregar cotação: " + e.message);
    } finally {
        loadingCotacao.value = false;
    }
}

// --- MÁSCARA MONETÁRIA (PT-BR) ---
function formatMoney(val) {
    if (!val && val !== 0) return '';
    return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function aplicarMascara(e, forn) {
    modificado.value = true; // Marca que houve edição na tela
    
    let v = e.target.value.replace(/\D/g, "");
    if (!v) {
        e.target.value = "";
        forn.valor_temp = ""; 
        return;
    }
    v = (v / 100).toFixed(2) + "";
    v = v.replace(".", ",");
    v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    
    // Atualiza input visual e variável temporária
    e.target.value = v;
    forn.valor_temp = v;
}

// --- SALVAMENTO MANUAL EM LOTE ---
async function salvarTudoManual() {
    salvando.value = true;

    // Prepara o array para enviar ao PHP
    // Se tiver valor_temp (digitado), usa ele. Se não, usa o original formatado.
    const listaParaSalvar = fornecedores.value.map(f => ({
        cod: f.cod,
        valor: f.valor_temp !== undefined ? f.valor_temp : formatMoney(f.valor)
    }));

    const payload = {
        acao: 'salvar_lote',
        id_processo: instanceId.value,
        num_solicitacao: itemAtual.value.num,
        seq_solicitacao: itemAtual.value.seq,
        cotacoes: listaParaSalvar
    };

    try {
        const req = await fetch(API_URL, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, // JSON puro para segurança
            body: JSON.stringify(payload) 
        });
        const res = await req.json();
        
        if (res.sucesso) {
            // SUCESSO: Atualiza a "Memória Oficial" com o que foi digitado
            fornecedores.value.forEach(f => {
                if (f.valor_temp !== undefined) {
                    // Converte "1.500,00" para float 1500.00
                    f.valor = parseFloat(f.valor_temp.replace(/\./g,'').replace(',','.'));
                    // Limpa o temp para o input voltar a ler de 'f.valor' (sem pular)
                    delete f.valor_temp; 
                }
            });

            modificado.value = false; // Tira o aviso amarelo
            alert("✅ Valores salvos com sucesso!");

        } else {
            console.error(res);
            alert("Erro ao salvar: " + (res.erro || res.msg));
        }
    } catch (e) {
        console.error(e);
        alert("Erro de conexão ao salvar.");
    } finally {
        salvando.value = false;
    }
}
</script>

<style scoped>
.cotacao-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; overflow: hidden; }
.header-cotacao { padding: 15px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
.layout-split { display: flex; flex: 1; overflow: hidden; }

/* Lista Esquerda */
.lista-itens { width: 350px; border-right: 1px solid #ddd; overflow-y: auto; background: #f8f9fa; }
.item-row { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: 0.2s; }
.item-row:hover { background: #e2e6ea; }
.item-row.active { background: #007bff; color: white; border-right: 4px solid #0056b3; }
.item-desc { font-size: 13px; margin: 4px 0; line-height: 1.4; }
.item-cod { font-weight: bold; font-size: 12px; }
.item-qtd { font-size: 11px; background: #6c757d; color: white; padding: 2px 6px; border-radius: 4px; display: inline-block; }

/* Form Direita */
.detalhe-item { flex: 1; display: flex; flex-direction: column; background: #fff; position: relative; }
.form-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

.form-header { 
    padding: 20px 30px 10px 30px; 
    display: flex; justify-content: space-between; align-items: center; 
    border-bottom: 2px solid #f0f0f0; 
}
.titulo-item { margin: 0; color: #333; }
.badge-status { font-size: 12px; background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold; border: 1px solid #ffeeba; }

.lista-inputs { flex: 1; overflow-y: auto; padding: 20px 30px; }
.form-group { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f9f9f9; }
.input-wrapper { display: flex; align-items: center; gap: 8px; }
.input-money { padding: 8px; text-align: right; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; width: 140px; transition: border 0.3s; }
.input-money:focus { outline: none; border-color: #80bdff; }

/* Rodapé Fixo */
.form-footer { 
    padding: 15px 30px; border-top: 1px solid #ddd; background: #f8f9fa; text-align: right; 
}
.btn-salvar {
    background: #28a745; color: white; border: none; padding: 12px 24px; 
    border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; 
    transition: background 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.btn-salvar:hover { background: #218838; transform: translateY(-1px); }
.btn-salvar:disabled { background: #94d3a2; cursor: not-allowed; transform: none; }

.empty-state { color: #999; text-align: center; margin-top: 100px; font-size: 18px; }
.loading-msg { text-align: center; padding: 20px; color: #666; font-style: italic; }
</style>