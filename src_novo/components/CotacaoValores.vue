<template>
  <div class="cotacao-wrapper">
    <div class="header-cotacao">
      <h2>Lançamento de Valores</h2>
      <p>Selecione um item à esquerda para informar os preços dos fornecedores.</p>
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
          <div class="item-top">
              <span class="sol-label">Sol: {{ item.num }}-{{ item.seq }}</span>
          </div>
          
          <div class="item-desc">{{ item.desc }}</div>
          
          <div class="item-badge">
              {{ item.qtd }} {{ item.unid }}
          </div>
        </div>
      </div>

      <div class="detalhe-item">
        <div v-if="!itemAtual" class="empty-state">
          <span>← Clique em um item para lançar valores.</span>
        </div>

        <div v-else class="form-container">
          <div class="form-header">
             <div class="header-left">
                 <h3 class="titulo-item">Item {{ itemAtual.num }}-{{ itemAtual.seq }}</h3>
             </div>
             <span class="badge-status" v-if="modificado">⚠️ Não salvo</span>
          </div>

          <div v-if="loadingCotacao" class="loading-msg">Carregando fornecedores...</div>
          
          <div v-else class="lista-inputs">
            <div v-for="forn in fornecedores" :key="forn.cod" class="form-group">
              <div class="forn-info">
                  <div class="forn-nome">{{ forn.nome }}</div>
              </div>
              <div class="input-wrapper">
                <span class="moeda">R$</span>
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

          <div class="form-footer">
              <button class="btn-cancel" @click="$emit('fechar')">Fechar</button>
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
        } else {
            alert("Erro: " + (res.erro || res.msg));
        }
    } catch (e) { alert("Erro de conexão."); } 
    finally { salvando.value = false; }
}
</script>

<style scoped>
.cotacao-wrapper { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-cotacao { padding: 15px 20px; border-bottom: 1px solid #ddd; background: #fff; }
.header-cotacao h2 { margin: 0 0 5px 0; font-size: 20px; color: #333; }
.header-cotacao p { margin: 0; color: #666; font-size: 14px; }

.layout-split { display: flex; flex: 1; overflow: hidden; }

/* --- LISTA LATERAL (Visual Legacy) --- */
.lista-itens { 
    width: 320px; 
    border-right: 1px solid #ddd; 
    overflow-y: auto; 
    background: #fff; 
}

.item-row { 
    padding: 15px 20px; 
    border-bottom: 1px solid #eee; 
    cursor: pointer; 
    transition: 0.1s; 
    border-left: 4px solid transparent; 
}

.item-row:hover { background: #f8f9fa; }

/* ESTADO ATIVO (AZUL) */
.item-row.active { 
    background: #007bff; 
    border-left-color: #0056b3; 
}

/* Tipografia dentro do item */
.sol-label { font-weight: 700; font-size: 12px; color: #333; }
.item-desc { margin: 5px 0 8px 0; font-size: 13px; color: #555; line-height: 1.4; text-transform: uppercase; }

/* O Badge Cinza (Igual ao Print) */
.item-badge { 
    background-color: #6c757d; /* Cinza escuro */
    color: white; 
    padding: 3px 8px; 
    border-radius: 4px; 
    font-size: 11px; 
    font-weight: 600;
    display: inline-block;
}

/* Ajustes de cor quando Ativo */
.item-row.active .sol-label { color: #fff; }
.item-row.active .item-desc { color: #e9ecef; }
.item-row.active .item-badge { 
    background-color: rgba(255,255,255,0.2); 
    color: #fff; 
    border: 1px solid rgba(255,255,255,0.3);
}

/* --- LADO DIREITO (FORMULÁRIO) --- */
.detalhe-item { flex: 1; display: flex; flex-direction: column; background: #fff; position: relative; }
.empty-state { display: flex; align-items: center; justify-content: center; height: 100%; color: #999; font-size: 16px; }

.form-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.form-header { padding: 20px 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.titulo-item { margin: 0; color: #333; font-size: 18px; font-weight: 700; }
.badge-status { font-size: 12px; background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold; border: 1px solid #ffeeba; }

.lista-inputs { flex: 1; overflow-y: auto; padding: 20px 30px; }
.form-group { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f9f9f9; }

.forn-nome { font-weight: 700; font-size: 13px; color: #333; text-transform: uppercase; }

.input-wrapper { display: flex; align-items: center; }
.moeda { margin-right: 5px; color: #666; font-size: 13px; }
.input-money { padding: 6px 10px; text-align: right; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; width: 120px; transition: border 0.2s; }
.input-money:focus { outline: none; border-color: #80bdff; box-shadow: 0 0 0 2px rgba(0,123,255,0.1); }

/* Rodapé Fixo */
.form-footer { padding: 15px 30px; border-top: 1px solid #ddd; background: #fff; text-align: right; }
.btn-salvar { background: #28a745; color: white; padding: 8px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; }
.btn-salvar:hover:not(:disabled) { background: #218838; }
.btn-salvar:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-cancel { background: #e2e6ea; color: #333; margin-right: 10px; padding: 8px 20px; border: 1px solid #ced4da; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px; }
.btn-cancel:hover { background: #dae0e5; }

.loading-msg { text-align: center; padding: 40px; color: #666; font-style: italic; }
</style>