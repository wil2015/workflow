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

        <div v-else>
          <h3 class="titulo-item">
            Item {{ itemAtual.num }}-{{ itemAtual.seq }}
          </h3>
          
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
                  :value="formatMoney(forn.valor)"
                  @input="aplicarMascara($event, forn)"
                  @blur="salvarValor(forn)"
                  :class="getStatusClass(forn)"
                  placeholder="0,00"
                />
              </div>
            </div>
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

// Status de salvamento para cada fornecedor (cod -> 'saving' | 'ok' | 'error')
const statusMap = ref({});

onMounted(() => {
    // Pega ID do PHP
    instanceId.value = window.INSTANCE_ID || new URLSearchParams(window.location.search).get('instance_id');
    carregarItens();
});

async function carregarItens() {
    try {
        const req = await fetch(`/backend/api_lancamento.php?acao=itens&instance_id=${instanceId.value}`);
        itens.value = await req.json();
    } catch (e) {
        alert("Erro ao carregar itens");
    } finally {
        loadingItens.value = false;
    }
}

async function selecionarItem(item) {
    itemAtual.value = item;
    loadingCotacao.value = true;
    fornecedores.value = [];
    statusMap.value = {}; // Limpa status anteriores

    try {
        const url = `/backend/api_lancamento.php?acao=cotacao&instance_id=${instanceId.value}&num=${item.num}&seq=${item.seq}`;
        const req = await fetch(url);
        fornecedores.value = await req.json();
    } catch (e) {
        alert("Erro ao carregar cotação");
    } finally {
        loadingCotacao.value = false;
    }
}

// --- LÓGICA DE MÁSCARA (Idêntica ao Legado) ---
function formatMoney(val) {
    if (!val && val !== 0) return '';
    // Converte float 1234.56 para "1.234,56"
    return parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function aplicarMascara(e, forn) {
    let v = e.target.value.replace(/\D/g, "");
    if (!v) {
        e.target.value = "";
        forn.valor_temp = ""; // Valor limpo
        return;
    }
    v = (v / 100).toFixed(2) + "";
    v = v.replace(".", ",");
    v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    e.target.value = v;
    
    // Guarda o valor "cru" (pt-BR) para enviar ao backend
    forn.valor_temp = v;
}

// --- AUTO SAVE ---
async function salvarValor(forn) {
    // Se não mexeu, não salva (usa o valor original se temp não existir)
    const valorParaSalvar = forn.valor_temp !== undefined ? forn.valor_temp : formatMoney(forn.valor);
    
    statusMap.value[forn.cod] = 'saving';

    const formData = new FormData();
    formData.append('acao', 'salvar_unitario');
    formData.append('id_processo', instanceId.value);
    formData.append('num_solicitacao', itemAtual.value.num);
    formData.append('seq_solicitacao', itemAtual.value.seq);
    formData.append('cod_fornecedor', forn.cod);
    formData.append('valor', valorParaSalvar); // Backend espera formato BR "1.000,00"

    try {
        // Usa o endpoint de ação que JÁ EXISTE no seu sistema legado
        const req = await fetch('/backend/acoes/salvar_cotacoes.php', { method: 'POST', body: formData });
        const res = await req.json();
        
        if (res.sucesso) {
            statusMap.value[forn.cod] = 'ok';
            // Atualiza o valor oficial
            forn.valor = parseFloat(valorParaSalvar.replace(/\./g,'').replace(',','.'));
        } else {
            statusMap.value[forn.cod] = 'error';
        }
    } catch (e) {
        statusMap.value[forn.cod] = 'error';
    }
}

function getStatusClass(forn) {
    const s = statusMap.value[forn.cod];
    if (s === 'saving') return 'border-warning';
    if (s === 'ok') return 'border-success';
    if (s === 'error') return 'border-danger';
    return '';
}
</script>

<style scoped>
.cotacao-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: sans-serif; overflow: hidden; }
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
.detalhe-item { flex: 1; padding: 30px; overflow-y: auto; background: #fff; }
.empty-state { color: #999; text-align: center; margin-top: 100px; font-size: 18px; }
.titulo-item { margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; color: #333; }

.form-group { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0; }
.input-wrapper { display: flex; align-items: center; gap: 8px; }
.input-money { padding: 8px; text-align: right; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; width: 140px; transition: border 0.3s; }
.input-money:focus { outline: none; border-color: #80bdff; }

/* Feedback Visual */
.border-warning { border-color: #ffc107 !important; background: #fff3cd; }
.border-success { border-color: #28a745 !important; background: #d4edda; }
.border-danger { border-color: #dc3545 !important; background: #f8d7da; }
</style>