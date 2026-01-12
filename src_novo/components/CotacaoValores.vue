<template>
  <div class="cotacao-wrapper">
    <div class="header-cotacao">
      <h2>Lançamento de Valores</h2>
      <p>Selecione um item à esquerda para informar os preços.</p>
    </div>

    <div class="layout-split">
      <div class="lista-itens">
        <div v-if="loadingItens" class="loading-msg">Carregando itens...</div>
        <div v-for="item in itens" :key="`${item.num}-${item.seq}`" class="item-row" :class="{ active: itemAtual && itemAtual.num === item.num }" @click="selecionarItem(item)">
          <div class="item-cod">Sol: {{ item.num }}-{{ item.seq }}</div>
          <div class="item-desc">{{ item.desc }}</div>
          <div class="item-qtd">{{ item.qtd }} {{ item.unid }}</div>
        </div>
      </div>

      <div class="detalhe-item">
        <div v-if="!itemAtual" class="empty-state">← Clique em um item para lançar valores.</div>
        <div v-else class="form-container">
          <div class="form-header">
             <h3 class="titulo-item">Item {{ itemAtual.num }}-{{ itemAtual.seq }}</h3>
             <span class="badge-status" v-if="modificado">⚠️ Alterações não salvas</span>
          </div>
          <div v-if="loadingCotacao" class="loading-msg">Carregando fornecedores...</div>
          <div v-else class="lista-inputs">
            <div v-for="forn in fornecedores" :key="forn.cod" class="form-group">
              <div class="forn-info"><strong>{{ forn.nome }}</strong></div>
              <div class="input-wrapper">
                <span>R$</span>
                <input type="text" class="input-money" :value="forn.valor_temp !== undefined ? forn.valor_temp : formatMoney(forn.valor)" @input="aplicarMascara($event, forn)" placeholder="0,00" />
              </div>
            </div>
          </div>
          <div class="form-footer">
              <button class="btn-cancel" @click="$emit('fechar')">Cancelar</button>
              <button class="btn-salvar" :disabled="salvando" @click="salvarTudoManual">
                {{ salvando ? 'Salvando...' : '✅ Salvar' }}
              </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// --- MUDANÇA 1: Props e Emits ---
const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

const instanceId = ref(props.instanceId);
const itens = ref([]);
const itemAtual = ref(null);
const fornecedores = ref([]);
const loadingItens = ref(true);
const loadingCotacao = ref(false);
const salvando = ref(false);
const modificado = ref(false);

const API_URL = '/backend/modulos/Cotacao/CotacaoController.php';

onMounted(() => {
    // --- MUDANÇA 2: Não lê mais window.location ---
    if (instanceId.value) {
        carregarItens();
    } else {
        alert("Erro: ID do processo não recebido.");
    }
});

async function carregarItens() {
    try {
        const req = await fetch(`${API_URL}?acao=listar_itens&instance_id=${instanceId.value}`);
        const res = await req.json();
        if (res.erro) throw new Error(res.erro);
        itens.value = res;
    } catch (e) { alert("Erro: " + e.message); } 
    finally { loadingItens.value = false; }
}

async function selecionarItem(item) {
    if (modificado.value && !confirm("Descartar alterações não salvas?")) return;
    itemAtual.value = item;
    loadingCotacao.value = true;
    modificado.value = false;
    fornecedores.value = [];

    try {
        const url = `${API_URL}?acao=listar_cotacoes&id_processo=${instanceId.value}&num=${item.num}&seq=${item.seq}`;
        const req = await fetch(url);
        const res = await req.json();
        if (res.erro) throw new Error(res.erro);
        fornecedores.value = res;
    } catch (e) { alert("Erro: " + e.message); } 
    finally { loadingCotacao.value = false; }
}

function formatMoney(val) {
    if (!val && val !== 0) return '';
    return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function aplicarMascara(e, forn) {
    modificado.value = true;
    let v = e.target.value.replace(/\D/g, "");
    if (!v) { e.target.value = ""; forn.valor_temp = ""; return; }
    v = (v / 100).toFixed(2) + "";
    v = v.replace(".", ",");
    v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    e.target.value = v;
    forn.valor_temp = v;
}

async function salvarTudoManual() {
    salvando.value = true;
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
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) 
        });
        const res = await req.json();
        
        if (res.sucesso) {
            alert("✅ Valores salvos!");
            modificado.value = false;
            // Opcional: emit('fechar') se quiser fechar ao salvar
        } else {
            alert("Erro: " + (res.erro || res.msg));
        }
    } catch (e) { alert("Erro de conexão."); } 
    finally { salvando.value = false; }
}
</script>

<style scoped>
/* Use o mesmo CSS que você já tinha no arquivo CotacaoValores.vue */
.cotacao-wrapper { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-cotacao { padding: 15px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
.layout-split { display: flex; flex: 1; overflow: hidden; }
.lista-itens { width: 300px; border-right: 1px solid #ddd; overflow-y: auto; background: #f8f9fa; }
.item-row { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; }
.item-row.active { background: #007bff; color: white; }
.detalhe-item { flex: 1; display: flex; flex-direction: column; padding: 0; }
.form-container { flex: 1; overflow-y: auto; padding: 20px; }
.form-group { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
.input-money { text-align: right; padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
.form-footer { margin-top: 20px; text-align: right; border-top: 1px solid #eee; padding-top: 15px; }
.btn-salvar { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
.btn-cancel { background: #ccc; margin-right: 10px; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
</style>