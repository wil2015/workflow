<template>
  <div class="solicitacoes-wrapper">
    
    <div v-if="instanceId" style="background: #ffecb3; padding: 5px; text-align: center; font-size: 12px; color: #664d03; border-bottom: 1px solid #e6dbb9;">
       🔧 MODO EDIÇÃO DETECTADO: ID {{ instanceId }}
    </div>
    
    <div class="header-actions">
      <h2>Nova Solicitação de Compra</h2>
      <input 
        v-model="termoBusca" 
        type="text" 
        placeholder="🔍 Pesquisar por projeto, item ou valor..." 
        class="search-box"
      >
    </div>

    <div v-if="loading" class="loading">
      <div class="spinner"></div> Carregando dados...
    </div>

    <div v-else class="tabela-container">
      <table class="tabela-vue">
        <thead>
          <tr>
            <th width="40"><input type="checkbox" @change="toggleTodos" :checked="todosSelecionados"></th>
            <th>Projeto</th>
            <th>Data</th>
            <th>Solicitação</th>
            <th>Produto / Descrição</th>
            <th>Preço Unit.</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="item in itensFiltrados" 
            :key="item.id" 
            :class="{ linha_selecionada: item.selecionado }"
            @click="toggleItem(item)"
          >
            <td class="text-center">
              <input type="checkbox" :checked="item.selecionado" @click.stop="toggleItem(item)">
            </td>
            
            <td><strong>{{ item.projeto }}</strong></td>
            <td>{{ formatarData(item.data_solicitacao) }}</td>
            <td>{{ item.id_solicitacao_senior || '-' }}</td>
            
            <td class="desc-cell">
              <div class="desc-texto">{{ item.descricao_produto }}</div>
              <small class="qtd-badge">Qtd: {{ item.quantidade }}</small>
            </td>
            
            <td class="no-wrap">{{ formatarMoeda(item.preco_unitario) }}</td>
            
            <td>
              <span :class="['status-badge', item.status === 'disponivel' ? 'verde' : 'vermelho']">
                {{ item.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="itensFiltrados.length === 0" class="empty-state">
        Nenhum item encontrado para "{{ termoBusca }}"
      </div>
    </div>

    <div class="footer-actions">
      <span>{{ itensSelecionados.length }} itens selecionados</span>
      <button @click="salvar" class="btn-salvar" :disabled="itensSelecionados.length === 0">
        Confirmar Seleção
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const itens = ref([]);
const loading = ref(true);
const termoBusca = ref('');

// --- 1. CARREGAMENTO ---
onMounted(async () => {
  try {
    const res = await fetch('/backend/api_solicitacoes.php');
    const json = await res.json();
    
    // Mapeando dados para garantir que o Vue não quebre se faltar campo
    itens.value = json.data.map(i => ({
      ...i,
      selecionado: false,
      // Garante que campos numéricos sejam números para formatação
      preco_unitario: parseFloat(i.preco_unitario || 0),
      quantidade: parseFloat(i.quantidade || 0)
    }));
  } catch (e) {
    console.error("Erro ao carregar", e);
    alert("Erro de comunicação com o backend.");
  } finally {
    loading.value = false;
  }
});

// --- 2. LÓGICA DE FILTRO (Substitui o Search do DataTables) ---
const itensFiltrados = computed(() => {
  if (!termoBusca.value) return itens.value;
  
  const termo = termoBusca.value.toLowerCase();
  return itens.value.filter(item => 
    (item.projeto && item.projeto.toLowerCase().includes(termo)) ||
    (item.descricao_produto && item.descricao_produto.toLowerCase().includes(termo)) ||
    (item.id_solicitacao_senior && item.id_solicitacao_senior.toLowerCase().includes(termo))
  );
});

const itensSelecionados = computed(() => itens.value.filter(i => i.selecionado));
const todosSelecionados = computed(() => itensFiltrados.value.length > 0 && itensFiltrados.value.every(i => i.selecionado));

// --- 3. AÇÕES ---
function toggleItem(item) {
  item.selecionado = !item.selecionado;
}

function toggleTodos() {
  const valorAlvo = !todosSelecionados.value;
  itensFiltrados.value.forEach(i => i.selecionado = valorAlvo);
}

function salvar() {
  alert(`Salvando ${itensSelecionados.value.length} itens...`);
  // Lógica de salvar aqui
}

// --- 4. FORMATADORES ---
function formatarMoeda(valor) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
}

function formatarData(dataSql) {
  if (!dataSql) return '-';
  const [ano, mes, dia] = dataSql.split(' ')[0].split('-');
  return `${dia}/${mes}/${ano}`;
}
</script>

<style scoped>
/* Estilos para imitar o visual "Enterprise" */
.solicitacoes-wrapper { font-family: 'Segoe UI', sans-serif; display: flex; flex-direction: column; height: 100vh; background: #f8f9fa; }
.header-actions { padding: 20px; background: white; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.search-box { padding: 10px; width: 300px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }

.tabela-container { flex: 1; overflow-y: auto; padding: 20px; }
.tabela-vue { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-size: 13px; }
.tabela-vue th { background: #e9ecef; padding: 12px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; position: sticky; top: 0; }
.tabela-vue td { padding: 12px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
.tabela-vue tr:hover { background-color: #f1f3f5; cursor: pointer; }
.linha_selecionada { background-color: #e8f0fe !important; }

.desc-texto { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.qtd-badge { background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #666; }
.no-wrap { white-space: nowrap; }

.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
.status-badge.verde { background: #d4edda; color: #155724; }
.status-badge.vermelho { background: #f8d7da; color: #721c24; }

.footer-actions { padding: 15px 20px; background: white; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
.btn-salvar { background: #007bff; color: white; border: none; padding: 10px 25px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.2s; }
.btn-salvar:disabled { background: #ccc; cursor: not-allowed; }
.btn-salvar:hover:not(:disabled) { background: #0056b3; }

.loading, .empty-state { text-align: center; padding: 40px; color: #666; font-size: 16px; }
</style>