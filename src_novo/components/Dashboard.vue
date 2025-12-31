<template>
  <div class="dashboard-container">
    
    <div class="section-title">Iniciar Novo Fluxo</div>
    
    <div v-if="loadingDefinicoes" class="loading-msg">Carregando fluxos...</div>
    
    <div class="cards-container">
      <div 
        v-for="fluxo in definicoes" 
        :key="fluxo.id" 
        class="card-fluxo"
        @click="$emit('iniciar-novo', fluxo)"
      >
        <h3>{{ fluxo.nome_do_fluxo }}</h3>
        <p>+ Iniciar Novo</p>
      </div>
    </div>

    <div class="header-tabela mt-4">
      <div class="section-title no-border">Processos Recentes</div>
      <input 
        v-model="termoBusca" 
        @input="paginaAtual = 1" 
        type="text" 
        placeholder="🔍 Pesquisar processo..." 
        class="search-box"
      >
    </div>

    <div class="tabela-container">
      <table class="tabela-vue">
        <thead>
          <tr>
            <th>ID (Wf)</th>
            <th>Fluxo</th>
            <th>Solicitação (Senior)</th>
            <th>Data Início</th>
            <th>Status</th>
            <th width="80" class="text-center">Ação</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loadingInstancias">
            <td colspan="6" class="text-center p-20">
               <div class="spinner"></div> Carregando...
            </td>
          </tr>
          
          <tr v-else-if="itensPaginados.length === 0">
            <td colspan="6" class="text-center p-20">Nenhum processo encontrado.</td>
          </tr>

          <tr v-for="inst in itensPaginados" :key="inst.id" class="linha-hover">
            <td><strong>{{ inst.id }}</strong></td>
            <td>{{ inst.nome_do_fluxo }}</td>
            <td>
               <span class="senior-tag">{{ inst.id_processo_senior || '-' }}</span>
            </td>
            <td>{{ inst.data_formatada }}</td>
            <td>
              <span :class="['status-badge', getStatusClass(inst.estatus_atual)]">
                {{ inst.estatus_atual }}
              </span>
            </td>
            <td class="text-center">
              <button class="btn-abrir" @click="$emit('abrir-processo', inst)">
                Abrir
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="footer-actions">
      <div class="info-total">
        Total: {{ itensFiltrados.length }} processos
      </div>
      
      <div class="paginacao-controls" v-if="totalPaginas > 1">
        <button @click="mudarPagina(-1)" :disabled="paginaAtual === 1">Anterior</button>
        <span>Página {{ paginaAtual }} de {{ totalPaginas }}</span>
        <button @click="mudarPagina(1)" :disabled="paginaAtual === totalPaginas">Próxima</button>
      </div>
      
      <div style="width: 100px;"></div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

// Emits para o Pai (App.vue)
defineEmits(['iniciar-novo', 'abrir-processo']);

// Estado
const definicoes = ref([]);
const instancias = ref([]);
const loadingDefinicoes = ref(true);
const loadingInstancias = ref(true);

// Estado da Tabela
const termoBusca = ref('');
const paginaAtual = ref(1);
const itensPorPagina = 10;

onMounted(async () => {
  carregarDefinicoes();
  carregarInstancias();
});

async function carregarDefinicoes() {
  try {
    const res = await fetch('/backend/api_dashboard.php?acao=definicoes');
    definicoes.value = await res.json();
  } catch (e) { console.error(e); } 
  finally { loadingDefinicoes.value = false; }
}

async function carregarInstancias() {
  try {
    const res = await fetch('/backend/api_dashboard.php?acao=instancias');
    instancias.value = await res.json();
  } catch (e) { console.error(e); } 
  finally { loadingInstancias.value = false; }
}

// --- Lógica de Filtro e Paginação (Padrão Vue) ---

const itensFiltrados = computed(() => {
  if (!termoBusca.value) return instancias.value;
  const termo = termoBusca.value.toLowerCase();
  
  return instancias.value.filter(item => 
    item.id.toString().includes(termo) ||
    item.nome_do_fluxo.toLowerCase().includes(termo) ||
    (item.id_processo_senior && item.id_processo_senior.toLowerCase().includes(termo))
  );
});

const totalPaginas = computed(() => Math.ceil(itensFiltrados.value.length / itensPorPagina));

const itensPaginados = computed(() => {
  const inicio = (paginaAtual.value - 1) * itensPorPagina;
  return itensFiltrados.value.slice(inicio, inicio + itensPorPagina);
});

function mudarPagina(delta) {
  paginaAtual.value += delta;
}

// Helpers Visuais
function getStatusClass(status) {
  if(status === 'Em Andamento') return 'status-azul';
  if(status === 'Finalizado') return 'status-verde';
  return 'status-cinza';
}
</script>

<style scoped>
/* Layout Geral */
.dashboard-container { padding: 20px; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; }
.section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
.section-title.no-border { border: none; margin: 0; padding: 0; }
.mt-4 { margin-top: 30px; }

/* Cards de Novo Fluxo */
.cards-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
.card-fluxo { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 250px; cursor: pointer; transition: 0.2s; border-left: 4px solid #007bff; }
.card-fluxo:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.card-fluxo h3 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
.card-fluxo p { margin: 0; color: #007bff; font-weight: 600; font-size: 13px; text-transform: uppercase; }

/* Header Tabela e Busca */
.header-tabela { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.search-box { padding: 8px 12px; width: 250px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }

/* Tabela (Estilo unificado com SolicitacoesList) */
.tabela-container { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
.tabela-vue { width: 100%; border-collapse: collapse; font-size: 13px; color: #333; }
.tabela-vue th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057; }
.tabela-vue td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
.linha-hover:hover { background-color: #f8f9fa; }

/* Badges e Botões */
.senior-tag { color: #0056b3; font-weight: bold; }
.btn-abrir { background: #007bff; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
.btn-abrir:hover { background: #0056b3; }

.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
.status-azul { background: #e3f2fd; color: #0d47a1; }
.status-verde { background: #d4edda; color: #155724; }
.status-cinza { background: #eee; color: #666; }

/* Rodapé e Paginação */
.footer-actions { padding: 15px; background: white; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.info-total { color: #666; font-size: 13px; }
.paginacao-controls button { padding: 5px 12px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #f8f9fa; margin: 0 5px; font-size: 12px; }
.paginacao-controls button:disabled { opacity: 0.5; cursor: default; }
.paginacao-controls span { margin: 0 10px; font-size: 13px; font-weight: 600; }

/* Loading Spinner */
.text-center { text-align: center; }
.p-20 { padding: 20px; }
.spinner { border: 2px solid #f3f3f3; border-top: 2px solid #007bff; border-radius: 50%; width: 14px; height: 14px; animation: spin 1s linear infinite; display: inline-block; margin-right: 8px; vertical-align: middle; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>