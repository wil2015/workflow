<template>
  <div class="dashboard-container">
    
    <div class="section-title">Iniciar Novo Fluxo (Módulo Novo)</div>
    <div v-if="loading" class="loading-msg">Carregando painel...</div>
    
    <div class="cards-container">
      <div 
        v-for="fluxo in definicoes" 
        :key="fluxo.id" 
        class="card-fluxo"
        :style="{ borderLeftColor: fluxo.cor_ui || '#007bff' }"
        @click="iniciarFluxo(fluxo)"
      >
        <h3>{{ fluxo.nome_do_fluxo }}</h3>
        <p :style="{ color: fluxo.cor_ui || '#007bff' }">+ Iniciar Novo</p>
      </div>
    </div>

    <div class="mt-4">
      <div class="section-title no-border mb-3">Processos Recentes</div>
      
      <DataTable 
        :data="instancias" 
        :columns="columns" 
        class="display"
        :options="dtOptions"
      >
        <template #action="{ rowData }">
             <button class="btn-abrir" @click="abrirProcesso(rowData)">
                Abrir
             </button>
        </template>
      </DataTable>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt'; 
import 'datatables.net-dt/css/dataTables.dataTables.min.css';

DataTable.use(DataTablesCore);

const definicoes = ref([]);
const instancias = ref([]);
const loading = ref(true);

// --- NAVEGAÇÃO ---
// --- NAVEGAÇÃO SPA (SEM ARQUIVOS PHP) ---

function iniciarFluxo(fluxo) {
    // Pega a URL atual do navegador
    const url = new URL(window.location.href);
    // Limpa parâmetros antigos
    url.searchParams.delete('instance_id');
    // Define o novo parâmetro (Isso faz o App.vue trocar para o BpmnViewer)
    url.searchParams.set('novo', fluxo.arquivo_xml); // Passa o XML ou ID do fluxo
    url.searchParams.set('fluxo_id', fluxo.id);
    
    // Atualiza a URL (O App.vue vai detectar e recarregar a tela certa)
    window.location.href = url.toString();
}

function abrirProcesso(proc) {
    const url = new URL(window.location.href);
    // Limpa parâmetros de "novo"
    url.searchParams.delete('novo');
    url.searchParams.delete('fluxo_id');
    // Define o ID do processo existente
    url.searchParams.set('instance_id', proc.id);
    
    window.location.href = url.toString();
}
// --- DEFINIÇÃO DAS COLUNAS (Ajustada para o novo Backend) ---
const columns = [
  { data: 'id', title: 'ID' },
  { data: 'nome_do_fluxo', title: 'Fluxo' },
  { 
    data: 'id_processo_senior', // Agora vem do Repo atualizado
    title: 'Solicitação (Senior)',
    render: (data) => `<span style="color:#0056b3; font-weight:bold">${data || '-'}</span>`
  },
  { data: 'data_formatada', title: 'Data Início' }, // O Service já formata isso
  { 
    data: 'status_atual', // CORRIGIDO: Banco usa 'status', antigo usava 'estatus'
    title: 'Status',
    render: (data) => {
        let cor = '#666'; 
        let bg = '#eee';
        if(data === 'Em Andamento') { cor = '#0d47a1'; bg = '#e3f2fd'; }
        if(data === 'Finalizado') { cor = '#155724'; bg = '#d4edda'; }
        return `<span style="padding:4px 8px; border-radius:12px; font-size:11px; font-weight:bold; text-transform:uppercase; background:${bg}; color:${cor}">${data}</span>`;
    }
  },
  { 
    data: null, 
    title: 'Ação', 
    orderable: false, 
    searchable: false,
    render: '#action' 
  },
];

const dtOptions = {
    language: {
        search: "Pesquisar:",
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "Nenhum processo encontrado",
        info: "Página _PAGE_ de _PAGES_",
        paginate: { first: "First", last: "Last", next: ">", previous: "<" }
    },
    pageLength: 10,
    order: [[0, 'desc']], 
    responsive: true
};

// --- CARREGAMENTO UNIFICADO (NOVA ARQUITETURA) ---
onMounted(async () => {
  try {
    // 1. Chama o Controller Novo
    const res = await fetch('/backend/modulos/Dashboard/DashboardController.php?acao=home');
    const json = await res.json();

    if (json.erro) throw new Error(json.erro);

    // 2. Distribui os dados (O Controller já manda tudo separado)
    definicoes.value = json.fluxos;
    instancias.value = json.tarefas;

  } catch (e) { 
    console.error("Erro ao carregar dashboard:", e);
    alert(e.message);
  } finally { 
    loading.value = false; 
  }
});
</script>

<style scoped>
/* Mantive seus estilos originais */
.dashboard-container { padding: 20px; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; }
.section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
.section-title.no-border { border: none; margin: 0; padding: 0; }
.mb-3 { margin-bottom: 1rem; }
.mt-4 { margin-top: 2rem; }
.cards-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
.card-fluxo { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 250px; cursor: pointer; transition: 0.2s; border-left: 4px solid #007bff; }
.card-fluxo:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.card-fluxo h3 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
.card-fluxo p { margin: 0; color: #007bff; font-weight: 600; font-size: 13px; text-transform: uppercase; }
.btn-abrir { background: #007bff; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
.btn-abrir:hover { background: #0056b3; }
:deep(table.dataTable) { border-collapse: collapse !important; width: 100% !important; background: white; border-radius: 8px; overflow: hidden; }
:deep(table.dataTable thead th) { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; padding: 12px 10px; }
:deep(table.dataTable tbody td) { padding: 10px; border-bottom: 1px solid #eee; }
</style>