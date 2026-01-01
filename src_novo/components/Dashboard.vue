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

    <div class="mt-4">
      <div class="section-title no-border mb-3">Processos Recentes</div>
      
      <DataTable 
        :data="instancias" 
        :columns="columns" 
        class="display"
        :options="dtOptions"
      >
        <template #action="{ rowData }">
             <button class="btn-abrir" @click="$emit('abrir-processo', rowData)">
                Abrir
             </button>
        </template>
      </DataTable>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// 1. IMPORTAÇÕES DO DATATABLES
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt'; // Core
import 'datatables.net-dt/css/dataTables.dataTables.min.css'; // ✅ CERTO
// Registra o Core no Componente Vue
DataTable.use(DataTablesCore);

defineEmits(['iniciar-novo', 'abrir-processo']);

const definicoes = ref([]);
const instancias = ref([]);
const loadingDefinicoes = ref(true);

// 2. CONFIGURAÇÃO DAS COLUNAS
// 'data': nome do campo no JSON
const columns = [
  { data: 'id', title: 'ID (Wf)' },
  { data: 'nome_do_fluxo', title: 'Fluxo' },
  { 
    data: 'id_processo_senior', 
    title: 'Solicitação (Senior)',
    render: (data) => `<span style="color:#0056b3; font-weight:bold">${data || '-'}</span>`
  },
  { data: 'data_formatada', title: 'Data Início' },
  { 
    data: 'estatus_atual', 
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
    // Aqui dizemos para usar o template #action definido lá em cima
    render: '#action' 
  },
];


// 3. OPÇÕES (COM TRADUÇÃO EMBUTIDA)
const dtOptions = {
    language: {
        // Em vez de baixar da internet, usamos direto aqui:
        search: "Pesquisar:",
        lengthMenu: "Mostrar _MENU_ registros por página",
        zeroRecords: "Nenhum registro encontrado",
        info: "Mostrando página _PAGE_ de _PAGES_",
        infoEmpty: "Nenhum registro disponível",
        infoFiltered: "(filtrado de _MAX_ registros no total)",
        paginate: {
            first: "Primeiro",
            last: "Último",
            next: "Próximo",
            previous: "Anterior"
        }
    },
    pageLength: 10,
    order: [[0, 'desc']], 
    lengthMenu: [5, 10, 25, 50],
    responsive: true
};
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
    const dados = await res.json();
    instancias.value = dados; // O DataTables detecta a mudança aqui e desenha sozinho
  } catch (e) { console.error(e); }
}
</script>

<style scoped>
/* Apenas estilos de layout, o resto vem do CSS do DataTables importado */
.dashboard-container { padding: 20px; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; }
.section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
.section-title.no-border { border: none; margin: 0; padding: 0; }
.mb-3 { margin-bottom: 1rem; }
.mt-4 { margin-top: 2rem; }

/* Cards */
.cards-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
.card-fluxo { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); width: 250px; cursor: pointer; transition: 0.2s; border-left: 4px solid #007bff; }
.card-fluxo:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.card-fluxo h3 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
.card-fluxo p { margin: 0; color: #007bff; font-weight: 600; font-size: 13px; text-transform: uppercase; }

/* Botão Abrir dentro da tabela */
.btn-abrir { background: #007bff; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.2s; }
.btn-abrir:hover { background: #0056b3; }

/* Personalização fina do DataTables para ficar clean */
:deep(.dataTables_wrapper .dataTables_length select) { padding: 4px; border-radius: 4px; border: 1px solid #ddd; }
:deep(.dataTables_wrapper .dataTables_filter input) { padding: 6px; border-radius: 4px; border: 1px solid #ddd; margin-left: 5px; }
:deep(table.dataTable) { border-collapse: collapse !important; width: 100% !important; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
:deep(table.dataTable thead th) { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; padding: 12px 10px; }
:deep(table.dataTable tbody td) { padding: 10px; border-bottom: 1px solid #eee; }
:deep(.dataTables_wrapper) { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>