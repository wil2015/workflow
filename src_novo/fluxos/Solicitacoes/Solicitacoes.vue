<template>
  <div class="solicitacoes-container">
    
    <div class="filtros-bar">
      <input v-model="termoBusca" @input="buscarDados" placeholder="Buscar projeto, produto..." class="input-search">
      <span v-if="carregando" class="loading-text">Carregando...</span>
    </div>

    <table class="tabela-vue">
      <thead>
        <tr>
          <th width="50">Ação</th>
          <th @click="ordenar('numprj')">Projeto ↕</th>
          <th @click="ordenar('datsol')">Data ↕</th>
          <th>Solicitação</th>
          <th>Produto</th>
          <th>Preço</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in lista" :key="item.chave_unica" :class="{'linha-vinculada': item.status === 'vinculado_aqui'}">
          
          <td class="col-acao">
            <button v-if="item.status === 'vinculado_aqui'" 
                    @click="removerItem(item)" 
                    class="btn-remove" title="Remover">&times;</button>
            
            <span v-else-if="item.status === 'bloqueado'" title="Em uso por outro processo">🔒</span>
            
            <input v-else type="checkbox" v-model="selecionados" :value="item.chave_unica">
          </td>

          <td><span class="tag-projeto">{{ item.projeto }}</span></td>
          <td>{{ formatarData(item.data) }}</td>
          <td><b>{{ item.solicitacao }}</b>-{{ item.sequencia }}</td>
          <td>
            {{ item.produto }}
            <div class="subtexto">{{ item.qtd }} {{ item.unid }}</div>
          </td>
          <td>{{ formatarMoeda(item.preco) }}</td>
          
          <td>
            <span v-if="item.status === 'vinculado_aqui'" class="badge-ok">Vinculado</span>
            <span v-else-if="item.status === 'bloqueado'" class="badge-lock">Proc. #{{ item.bloqueado_id }}</span>
            <span v-else class="badge-new">Novo</span>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="footer-actions">
        <div class="info-selecao">
            {{ selecionados.length }} itens selecionados
        </div>
        <button @click="salvarSelecao" :disabled="!selecionados.length || salvando" class="btn-save">
            {{ salvando ? 'Salvando...' : 'Vincular Selecionados' }}
        </button>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// --- ESTADO ---
const lista = ref([]);
const selecionados = ref([]); // Array com IDs dos checkbox marcados
const termoBusca = ref('');
const carregando = ref(false);
const salvando = ref(false);

// Props vindas da URL ou injetadas
const props = defineProps({
    instanceId: String, // ID do processo atual (se houver)
    fluxoId: String
});

// --- MÉTODOS ---
async function buscarDados() {
    carregando.value = true;
    try {
        const params = new URLSearchParams({
            instance_id: props.instanceId || '',
            search: termoBusca.value,
            // Adicione paginação aqui depois
        });
        
        const req = await fetch(`/backend/api_solicitacoes_v2.php?${params}`);
        const res = await req.json();
        lista.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        carregando.value = false;
    }
}

async function salvarSelecao() {
    salvando.value = true;
    const formData = new FormData();
    formData.append('acao', 'vincular');
    formData.append('id_fluxo_definicao', props.fluxoId);
    
    selecionados.value.forEach(val => formData.append('selecionados[]', val));

    // Aqui chamamos sua action PHP existente (ela funciona igual!)
    const req = await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
    
    // Recarrega a lista
    selecionados.value = [];
    await buscarDados();
    salvando.value = false;
}

async function removerItem(item) {
    if(!confirm('Remover este item?')) return;
    
    const formData = new FormData();
    formData.append('acao', 'remover_item');
    formData.append('id_processo', props.instanceId);
    formData.append('num_solicitacao', item.solicitacao);
    formData.append('seq_solicitacao', item.sequencia);

    await fetch('/backend/acoes/gerenciar_solicitacao.php', { method: 'POST', body: formData });
    await buscarDados();
}

// Helpers
const formatarMoeda = (val) => 'R$ ' + val.toLocaleString('pt-BR', {minimumFractionDigits: 2});
const formatarData = (iso) => iso ? iso.split('-').reverse().join('/') : '-';

// Início
onMounted(() => {
    buscarDados();
});
</script>

<style scoped>
/* Um CSS básico para não ficar feio */
.tabela-vue { width: 100%; border-collapse: collapse; margin-top: 10px; }
.tabela-vue th, .tabela-vue td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; font-size: 13px; }
.tabela-vue th { background: #f8f9fa; cursor: pointer; }
.btn-remove { color: red; border: 1px solid red; background: white; border-radius: 4px; cursor: pointer; }
.tag-projeto { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 11px; }
.footer-actions { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #ddd; padding-top: 10px; }
.btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
</style>