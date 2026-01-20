<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt'; 
import 'datatables.net-dt/css/dataTables.dataTables.min.css';

DataTable.use(DataTablesCore);

const definicoes = ref([]);
const instancias = ref([]);
const loading = ref(true);
const erro = ref(null);

// --- COLUNAS CORRIGIDAS ---
const columns = [
  // 1. ID Visual (61/2026)
  { data: 'id_visual', title: 'ID', width: '70px' },

  // 2. Campos Originais (Respeitando nomes do Service)
  { data: 'nome_do_fluxo', title: 'Fluxo' },
  { 
    data: 'id_processo_senior', 
    title: 'Solicitação', 
    render: d => `<strong style="color:#0056b3">${d || '-'}</strong>` 
  },

  // 3. Novas Colunas de Data
  { data: 'prev_cotacao', title: 'Prev. Cotação', width: '90px' },
  { data: 'prev_entrega', title: 'Prev. Entrega', width: '90px' },

  { data: 'data_formatada', title: 'Criação' }, // Original
  { 
    data: 'status_atual', // Original
    title: 'Status', 
    render: d => {
        let bg = d === 'Finalizado' ? '#d4edda' : '#e3f2fd';
        let color = d === 'Finalizado' ? '#155724' : '#0d47a1';
        return `<span style="background:${bg}; color:${color}; padding:4px 8px; border-radius:10px; font-size:11px; font-weight:bold; text-transform:uppercase">${d}</span>`;
    }
  },
  { data: null, title: 'Ação', render: '#action', orderable: false }
];

const dtOptions = {
    language: { sSearch: "Pesquisar:", sZeroRecords: "Nada encontrado" },
    pageLength: 10,
    order: [[0, 'desc']] 
};

function iniciarFluxo(fluxo) {
    const url = new URL(window.location.href);
    url.searchParams.delete('instance_id');
    url.searchParams.set('novo', fluxo.arquivo_xml);
    url.searchParams.set('fluxo_id', fluxo.id);
    window.location.href = url.toString();
}

function abrirProcesso(proc) {
    const url = new URL(window.location.href);
    url.searchParams.delete('novo');
    url.searchParams.delete('fluxo_id');
    url.searchParams.set('instance_id', proc.id); // ID real numérico para o link
    window.location.href = url.toString();
}

onMounted(async () => {
  try {
    const res = await fetch('backend/modulos/Dashboard/DashboardController.php?acao=home');
    if (!res.ok) throw new Error(`Erro HTTP: ${res.status}`);
    const json = await res.json();
    if (json.erro) throw new Error(json.erro);

    definicoes.value = json.fluxos || [];
    instancias.value = json.tarefas || [];
  } catch (e) { 
    erro.value = e.message;
    console.error(e);
  } finally { 
    loading.value = false; 
  }
});
</script>

<template>
  <div class="dashboard-container">
    <div class="section-title">Painel de Compras (SPA)</div>
    
    <div v-if="erro" class="alert-error">
        <h3>Erro:</h3>
        <p>{{ erro }}</p>
    </div>

    <div v-if="loading" class="loading-msg">Carregando...</div>
    
    <div v-else-if="!erro">
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
            <DataTable :data="instancias" :columns="columns" class="display" :options="dtOptions">
                <template #action="{ rowData }">
                    <button class="btn-abrir" @click="abrirProcesso(rowData)">Abrir</button>
                </template>
            </DataTable>
        </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-container { padding: 30px; background: #f4f6f9; min-height: 100vh; display: block; }
.cards-container { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
.card-fluxo { background: white; padding: 20px; width: 240px; border-radius: 8px; cursor: pointer; border-left: 5px solid #007bff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s; }
.card-fluxo:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
.card-fluxo h3 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
.card-fluxo p { margin: 0; font-weight: bold; font-size: 12px; text-transform: uppercase; }
.btn-abrir { background: #007bff; color: white; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
.alert-error { background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fca5a5; }
.section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
.section-title.no-border { border: none; }
</style>