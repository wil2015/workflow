<template>
  <div class="fornecedores-wrapper">
    <div class="header-actions">
        <div class="titulo-box">
            <h2>Selecionar Fornecedores</h2>
            <p v-if="instanceId">Processo Workflow: <strong>#{{ instanceId }}</strong></p>
        </div>
    </div>

    <div class="tabela-container">
      <DataTable 
        v-if="instanceId"
        class="display"
        :columns="columns" 
        :options="dtOptions"
        ref="dt"
      >
        <template #action="{ rowData }">
            <div class="cell-center">
                <input 
                    type="checkbox" 
                    class="chk-big"
                    :checked="rowData.vinculado"
                    @change="toggleFornecedor(rowData, $event)"
                    :disabled="loadingId === rowData.cod"
                />
            </div>
        </template>

        <template #status="{ rowData }">
            <span v-if="loadingId === rowData.cod" class="badge-loading">Processando...</span>
            <span v-else-if="rowData.vinculado" class="badge-ok">Selecionado</span>
            <span v-else class="badge-dispo">Disponível</span>
        </template>
      </DataTable>
    </div>

    <div class="footer-actions">
        <button class="btn-fechar" @click="fechar">Concluir / Voltar</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.min.css';

DataTable.use(DataTablesCore);

// --- MUDANÇA 1: Recebe Props e Emits ---
const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

// Usamos uma ref local inicializada com o prop
const instanceId = ref(props.instanceId);

const dt = ref(null);
const loadingId = ref(null);

const columns = [
  { data: null, title: '#', orderable: false, width: '40px', render: '#action' },
  { data: 'nome', title: 'Razão Social / Nome', render: (d, t, r) => `<strong>${d}</strong><br><small style="color:#666">Cód: ${r.cod}</small>` },
  { data: 'doc', title: 'CNPJ / CPF' },
  { data: 'cidade_uf', title: 'Cidade/UF' },
  { data: null, title: 'Status', width: '100px', render: '#status' }
];

const dtOptions = {
    language: { sSearch: "Pesquisar", sZeroRecords: "Nada encontrado", sProcessing: "Carregando..." },
    pageLength: 10,
    serverSide: true,
    processing: true,
    order: [[ 1, "asc" ]],
    ajax: {
        url: '/backend/modulos/Fornecedores/FornecedoresController.php',
        data: (d) => { 
            d.acao = 'listar'; 
            d.instance_id = instanceId.value; 
        }
    }
};

function fechar() {
    // --- MUDANÇA 2: Emite evento para o pai fechar o modal ---
    emit('fechar');
}

async function toggleFornecedor(row, event) {
    const isChecked = event.target.checked;
    loadingId.value = row.cod; 
    
    const payload = {
        id_processo: instanceId.value,
        acao: isChecked ? 'salvar_lote' : 'remover'
    };

    if (isChecked) payload.participantes = [row.json_full];
    else payload.cod_fornecedor = row.cod;

    try {
        const req = await fetch('/backend/modulos/Fornecedores/FornecedoresController.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload) 
        });
        const res = await req.json();

        if (res.sucesso) {
            dt.value.dt.ajax.reload(null, false);
        } else {
            throw new Error(res.erro || "Erro desconhecido.");
        }
    } catch (e) {
        alert("Ops! " + e.message); 
        event.target.checked = !isChecked;
    } finally {
        loadingId.value = null; 
    }
}
</script>

<style scoped>
.fornecedores-wrapper { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-actions { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.tabela-container { flex: 1; padding: 20px; overflow-y: auto; }
.footer-actions { padding: 15px; border-top: 1px solid #ddd; text-align: right; }
.chk-big { transform: scale(1.3); cursor: pointer; }
.cell-center { display: flex; justify-content: center; align-items: center; height: 100%; }
.badge-ok { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.badge-dispo { background: #e9ecef; color: #666; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-loading { background: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.btn-fechar { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
</style>