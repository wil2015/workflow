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
        <button class="btn-fechar" @click="fechar">Fechar / Concluir</button>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.min.css';

DataTable.use(DataTablesCore);

const instanceId = ref(null);
const dt = ref(null);
const loadingId = ref(null);

const columns = [
  { data: null, title: '#', orderable: false, width: '40px', render: '#action' },
  { data: 'nome', title: 'Razão Social / Nome', render: (d, t, r) => `<strong>${d}</strong><br><small style="color:#666">Cód: ${r.cod}</small>` },
  { data: 'doc', title: 'CNPJ / CPF' },
  { data: 'cidade_uf', title: 'Cidade/UF' },
  { data: null, title: 'Status', width: '100px', render: '#status' }
];

// --- AQUI ESTÁ A CORREÇÃO ---
// Removemos a URL externa e colocamos a tradução "Hardcoded"
const dtOptions = {
    language: {
        "sEmptyTable": "Nenhum registro encontrado",
        "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
        "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
        "sInfoFiltered": "(Filtrados de _MAX_ registros)",
        "sInfoPostFix": "",
        "sInfoThousands": ".",
        "sLengthMenu": "_MENU_ resultados por página",
        "sLoadingRecords": "Carregando...",
        "sProcessing": "Processando...",
        "sZeroRecords": "Nenhum registro encontrado",
        "sSearch": "Pesquisar",
        "oPaginate": {
            "sNext": "Próximo",
            "sPrevious": "Anterior",
            "sFirst": "Primeiro",
            "sLast": "Último"
        },
        "oAria": {
            "sSortAscending": ": Ordenar colunas de forma ascendente",
            "sSortDescending": ": Ordenar colunas de forma descendente"
        }
    },
    pageLength: 10,
    serverSide: true,
    processing: true,
    order: [[ 1, "asc" ]],
    ajax: {
        url: '/backend/api_fornecedores.php',
        data: (d) => { d.instance_id = instanceId.value; }
    }
};

onMounted(() => {
    // 1. Pega variáveis injetadas pelo PHP (Jeito mais seguro no Iframe)
    if (window.INSTANCE_ID) {
        instanceId.value = window.INSTANCE_ID;
    } else {
        // Fallback para URL se o window falhar
        const params = new URLSearchParams(window.location.search);
        instanceId.value = params.get('instance_id');
    }
});

function fechar() {
    window.location.href = '/'; 
}

async function toggleFornecedor(row, event) {
    const isChecked = event.target.checked;
    loadingId.value = row.cod; 
    
    const formData = new FormData();
    formData.append('id_processo', instanceId.value);

    if (isChecked) {
        formData.append('acao', 'salvar_participantes');
        formData.append('participantes[]', row.json_full);
    } else {
        formData.append('acao', 'remover_participante');
        formData.append('cod_fornecedor', row.cod);
    }

    try {
        const req = await fetch('/backend/acoes/gerenciar_participantes.php', { method: 'POST', body: formData });
        const res = await req.json();

        if (res.sucesso) {
            dt.value.dt.ajax.reload(null, false);
        } else {
            alert('Erro: ' + res.erro);
            event.target.checked = !isChecked;
        }
    } catch (e) {
        alert('Erro de conexão.');
        event.target.checked = !isChecked;
    } finally {
        loadingId.value = null; 
    }
}
</script>

<style scoped>
.fornecedores-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
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