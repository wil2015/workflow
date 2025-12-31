<template>
  <div class="solicitacoes-wrapper">
    
    <div class="header-actions">
      <div class="titulo-box">
        <h2 v-if="instanceId">Gerenciar Processo #{{ instanceId }}</h2>
        <h2 v-else>Nova Solicitação de Compra</h2>
        <span class="badge-total">{{ totalRegistros }} itens encontrados</span>
      </div>
      <input 
        v-model="termoBusca" 
        @input="onBuscaInput" 
        type="text" 
        placeholder="🔍 Pesquisar no servidor..." 
        class="search-box"
      >
    </div>

    <div class="tabela-container">
      <table class="tabela-vue">
        <thead>
          <tr>
            <th width="50" class="text-center">
                <input 
                  type="checkbox" 
                  @change="toggleTodosDaPagina" 
                  :checked="todosDaPaginaSelecionados" 
                  v-if="!instanceId"
                  :disabled="loading"
                >
                <span v-else>Ação</span>
            </th>
            <th>Projeto</th>
            <th>Data</th>
            <th>Solicitação</th>
            <th>Produto / Descrição</th>
            <th>Preço Unit.</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
             <td colspan="7" class="text-center p-20">
                <div class="spinner"></div> Buscando dados...
             </td>
          </tr>
          
          <tr v-else-if="itens.length === 0">
             <td colspan="7" class="text-center p-20">Nenhum registro encontrado.</td>
          </tr>

          <tr 
            v-for="item in itens" 
            :key="item.id" 
            :class="{ linha_selecionada: item.selecionado, vinculada: item.status === 'vinculado' }"
            @click="toggleItem(item)"
          >
            <td class="text-center cell-acao">
                <button v-if="item.status === 'vinculado'" class="btn-x-legacy" @click.stop="removerItem(item)" title="Remover">&times;</button>
                <span v-else-if="item.status === 'bloqueado'" title="Indisponível">🔒</span>
                <input v-else type="checkbox" :checked="item.selecionado" @click.stop="toggleItem(item)">
            </td>
            
            <td><strong>{{ item.projeto }}</strong></td>
            <td>{{ formatarData(item.data_solicitacao) }}</td>
            <td>{{ item.id_solicitacao_senior }}</td>
            <td class="desc-cell">
              <div class="desc-texto" :title="item.descricao_produto">{{ item.descricao_produto }}</div>
              <small class="qtd-badge">{{ item.quantidade }} {{ item.unidade }}</small>
            </td>
            <td class="no-wrap">{{ formatarMoeda(item.preco_unitario) }}</td>
            
            <td>
              <span v-if="item.status === 'vinculado'" class="status-texto verde">Vinculado</span>
              <span v-else-if="item.status === 'bloqueado'" class="status-texto cinza">{{ item.proc_bloqueador }}</span>
              <span v-else class="status-texto azul">Disponível</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="footer-actions">
      <div class="left-actions">
          <button v-if="instanceId" @click="excluirProcesso" class="btn-danger">Excluir Processo</button>
      </div>
      
      <div class="paginacao-controls">
        <button @click="mudarPagina(-1)" :disabled="paginaAtual === 1 || loading">Anterior</button>
        <span>Página {{ paginaAtual }} de {{ totalPaginas }}</span>
        <button @click="mudarPagina(1)" :disabled="paginaAtual >= totalPaginas || loading">Próxima</button>
      </div>

      <div class="right-actions">
          <button @click="salvar" class="btn-salvar" :disabled="itensSelecionados.length === 0">
            {{ instanceId ? 'Salvar Novos Itens' : 'Gerar Processo' }}
          </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';

const itens = ref([]);
const loading = ref(true);
const termoBusca = ref('');
const paginaAtual = ref(1);
const totalRegistros = ref(0);
const itensPorPagina = 10;
const instanceId = ref(null);
const fluxoId = ref(null);
let timeoutBusca = null; // Para o debounce

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  instanceId.value = params.get('instance_id') === 'null' ? null : params.get('instance_id');
  fluxoId.value = params.get('fluxo_id');
  
  carregarDados(); // Primeira carga
});

// Função Principal de Carga
async function carregarDados() {
  loading.value = true;
  try {
    // Monta a URL com paginação e busca
    const url = `/backend/api_solicitacoes.php?instance_id=${instanceId.value || ''}&page=${paginaAtual.value}&limit=${itensPorPagina}&search=${encodeURIComponent(termoBusca.value)}`;
    
    const res = await fetch(url);
    const json = await res.json();
    
    if (json.sucesso) {
        itens.value = json.data.map(i => ({
          ...i, selecionado: false, // Checkbox começa falso
          preco_unitario: parseFloat(i.preco_unitario || 0),
          quantidade: parseFloat(i.quantidade || 0)
        }));
        totalRegistros.value = json.total;
    } else {
        alert("Erro no backend: " + json.erro);
    }
  } catch (e) { console.error(e); } finally { loading.value = false; }
}

// --- Paginação Server-Side ---
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / itensPorPagina) || 1);

function mudarPagina(delta) {
    const novaPagina = paginaAtual.value + delta;
    if (novaPagina >= 1 && novaPagina <= totalPaginas.value) {
        paginaAtual.value = novaPagina;
        carregarDados(); // Chama o servidor
    }
}

// --- Busca com Delay (Debounce) ---
function onBuscaInput() {
    clearTimeout(timeoutBusca);
    timeoutBusca = setTimeout(() => {
        paginaAtual.value = 1; // Volta pra pág 1 quando busca
        carregarDados();
    }, 500); // Espera 500ms antes de buscar
}

// --- Seleção Local ---
const itensSelecionados = computed(() => itens.value.filter(i => i.selecionado));
const todosDaPaginaSelecionados = computed(() => itens.value.length > 0 && itens.value.every(i => i.selecionado || i.status !== 'disponivel'));

function toggleItem(item) {
  if (item.status !== 'disponivel') return;
  item.selecionado = !item.selecionado;
}
function toggleTodosDaPagina() {
  const alvo = !todosDaPaginaSelecionados.value;
  itens.value.forEach(item => { if (item.status === 'disponivel') item.selecionado = alvo; });
}

// --- Ações ---
async function salvar() {
  if (itensSelecionados.value.length === 0) return;
  alert("Simulação: Salvando...");
}
async function removerItem(item) {
    if(confirm(`Remover item ${item.id_solicitacao_senior}?`)) alert("Simulação: Removido.");
}
async function excluirProcesso() {
    if(confirm("Excluir processo?")) alert("Simulação: Excluído.");
}

// --- Formatadores ---
function formatarMoeda(val) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val); }
function formatarData(dt) { return dt ? dt.split('-').reverse().join('/') : '-'; }
</script>

<style scoped>
/* Mesmos estilos de antes (Legado) */
.solicitacoes-wrapper { font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; flex-direction: column; background: #fff; }
.header-actions { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.tabela-container { flex: 1; overflow-y: auto; }
.tabela-vue { width: 100%; border-collapse: collapse; font-size: 13px; color: #333; }
.tabela-vue th { background: #f1f1f1; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold; position: sticky; top: 0; }
.tabela-vue td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
.btn-x-legacy { background: #fff; border: 1px solid #dc3545; color: #dc3545; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.btn-x-legacy:hover { background: #dc3545; color: white; }
.status-texto { font-weight: 500; font-size: 12px; }
.status-texto.verde { color: #28a745; font-weight: bold; }
.status-texto.azul { color: #007bff; }
.status-texto.cinza { color: #999; }
.linha_selecionada { background-color: #e8f0fe; }
.vinculada { background-color: #f9f9f9; }
.cell-acao { display: flex; justify-content: center; align-items: center; }
.footer-actions { padding: 15px; background: #f9f9f9; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.paginacao-controls button { padding: 5px 10px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #eee; margin: 0 5px; }
.paginacao-controls button:disabled { opacity: 0.5; cursor: default; }
.btn-salvar { background: #6c757d; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; }
.btn-danger { background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
.desc-texto { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }
.qtd-badge { color: #666; font-size: 11px; display: block; }
.text-center { text-align: center; }
.p-20 { padding: 20px; }
.spinner { border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; width: 12px; height: 12px; animation: spin 1s linear infinite; display: inline-block; margin-right: 5px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>