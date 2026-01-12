<template>
  <div class="solicitacoes-wrapper">
    <div class="header-actions">
      <div class="titulo-box">
        <h2 v-if="instanceId">Gerenciar Processo #{{ instanceId }}</h2>
        <h2 v-else>Nova Solicitação de Compra</h2>
      </div>
    </div>

    <div class="tabela-container">
      <DataTable 
        class="display"
        :columns="columns" 
        :options="dtOptions"
        ref="dt"
      />
    </div>

    <div class="footer-actions">
      <div class="left-actions">
          <button v-if="instanceId" @click="excluirProcesso" class="btn-danger">Excluir Processo Inteiro</button>
      </div>
      <div class="right-actions">
          <button @click="salvar" class="btn-salvar" :disabled="idsSelecionados.size === 0">
            {{ instanceId ? 'Salvar Novos Itens' : 'Gerar Processo' }} 
            <span v-if="idsSelecionados.size > 0">({{ idsSelecionados.size }})</span>
          </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.min.css'; 

DataTable.use(DataTablesCore);

// --- 1. LÓGICA HÍBRIDA (Modal vs Dashboard) ---

// Recebe props (quando aberto pelo Modal do BpmnViewer)
const props = defineProps({
    instanceId: String 
});

// Lê URL (quando aberto pelo Dashboard para criar novo)
const params = new URLSearchParams(window.location.search);
const urlId = params.get('instance_id');
const fluxoId = ref(params.get('fluxo_id'));

// Decide qual ID usar: Prioridade para Props > URL
// Se urlId for a string "null" ou vazio, vira null real
const idCalculado = props.instanceId || ((urlId === 'null' || !urlId) ? null : urlId);
const instanceId = ref(idCalculado);

const dt = ref(null); 
const idsSelecionados = reactive(new Set());

// --- 2. DEFINIÇÃO DE COLUNAS ---
const columns = [
  { 
    data: null, title: '', orderable: false, searchable: false, width: '30px',
    render: (data, type, row) => {
        if (row.status === 'vinculado') {
            // Se já está vinculado a ESTE processo, permite remover
            // Nota: O backend deve validar se pertence mesmo a este ID
            return `<button class="btn-rm-item" data-id="${row.id_unico}" onclick="window.remItem('${row.id_unico}')">&times;</button>`;
        } else if (row.status === 'bloqueado') {
            return `🔒`;
        } else {
            const checked = idsSelecionados.has(row.id_unico) ? 'checked' : '';
            return `<input type="checkbox" class="chk-vue" value="${row.id_unico}" ${checked} onchange="window.toggleVue('${row.id_unico}')">`;
        }
    }
  },
  { data: 'projeto', title: 'Projeto' },
  { data: 'data_solicitacao', title: 'Data', render: d => d ? d.split('-').reverse().join('/') : '' },
  { data: 'id_solicitacao_senior', title: 'Solicitação' },
  { data: 'descricao_produto', title: 'Produto', render: (d,t,r) => `<div>${d}</div><small style="color:#666">${r.quantidade} ${r.unidade}</small>` },
  { data: 'preco_unitario', title: 'Preço', render: d => `R$ ${parseFloat(d).toLocaleString('pt-BR', {minimumFractionDigits: 2})}` },
  { 
    data: 'status', title: 'Status',
    render: (d, t, r) => {
      if(d==='vinculado') return `<span style="color:green;font-weight:bold">Vinculado</span>`;
      if(d==='bloqueado') return `<span style="color:#666;font-weight:bold">Proc. #${r.proc_bloqueador}</span>`;
      return `<span style="color:blue;font-weight:bold">Disponível</span>`;
    }
  }
];

// --- 3. OPÇÕES DA TABELA ---
const dtOptions = {
    language: {
        sEmptyTable: "Nenhum registro encontrado",
        sInfo: "Mostrando de _START_ até _END_ de _TOTAL_ registros",
        sLengthMenu: "_MENU_ por página",
        sLoadingRecords: "Carregando...",
        sProcessing: "Processando...",
        sZeroRecords: "Nenhum registro encontrado",
        sSearch: "Pesquisar",
        oPaginate: { sNext: "Próx", sPrevious: "Ant", sFirst: "Prim", sLast: "Últ" }
    },
    pageLength: 10,
    serverSide: true,
    processing: true,
    order: [[ 6, "desc" ]], // Ordena por Status (Vinculados primeiro)

    ajax: {
        url: '/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=listar_solicitacoes',
        data: (d) => { 
            d.instance_id = instanceId.value; 
        }
    },
    drawCallback: () => {
        idsSelecionados.forEach(id => {
            const el = document.querySelector(`.chk-vue[value="${id}"]`);
            if(el) el.checked = true;
        });
    }
};

// --- 4. FUNÇÕES GLOBAIS E LIFECYCLE ---
onMounted(() => {
  // Expondo funções para o HTML injetado pelo DataTables (checkboxes e botões)
  window.toggleVue = (id) => {
      if(idsSelecionados.has(id)) idsSelecionados.delete(id);
      else idsSelecionados.add(id);
  };
  
  window.remItem = (id) => {
     const parts = id.split('-'); // codemp-num-seq
     // Chama a função interna do Vue
     removerItem({id_solicitacao_senior: `${parts[1]}-${parts[2]}`, id_unico: id});
  };
});

// --- AÇÕES DO USUÁRIO ---

async function salvar() {
    const listaIds = Array.from(idsSelecionados);
    if (listaIds.length === 0) return;
    
    const formData = new FormData();
    formData.append('acao', 'vincular');
    formData.append('id_fluxo_definicao', fluxoId.value || 1);
    
    if (instanceId.value) {
        formData.append('id_processo_instancia', instanceId.value);
    }

    listaIds.forEach(id => formData.append('selecionados[]', id));

    try {
        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if(json.sucesso) {
            alert(json.msg);
            idsSelecionados.clear();
            dt.value.dt.ajax.reload(null, false);
            
            // Se foi criação de processo novo (estava sem ID), recarrega a página para entrar no modo edição
            if(!instanceId.value) {
                 // Redireciona para o modo de visualização do novo processo
                 // (Aqui a arquitetura SPA assumiria se tivéssemos emitido evento, 
                 // mas como é criação inicial, reload é mais seguro para limpar estados)
                 window.location.href = `/?instance_id=${json.id_processo}`;
            }
        } else { 
            alert('Erro: ' + json.erro); 
        }
    } catch (e) { 
        alert('Erro na requisição'); 
        console.error(e);
    }
}

async function removerItem(item) {
    if(!confirm(`Remover item ${item.id_solicitacao_senior} deste processo?`)) return;
    const parts = item.id_unico.split('-');
    
    const formData = new FormData();
    formData.append('acao', 'remover_item');
    formData.append('id_processo', instanceId.value);
    formData.append('num_solicitacao', parts[1]);
    formData.append('seq_solicitacao', parts[2]);

    try {
        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.sucesso) dt.value.dt.ajax.reload(null, false);
        else alert('Erro: ' + json.erro);
    } catch (e) { alert('Erro ao remover'); }
}

async function excluirProcesso() {
    if(!confirm("Tem certeza que deseja EXCLUIR ESTE PROCESSO INTEIRO?\nIsso apagará todas as cotações e vínculos.")) return;
    
    const formData = new FormData();
    formData.append('acao', 'cancelar_processo');
    formData.append('id_processo', instanceId.value);
    
    try {
        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.sucesso) { 
            alert('Processo excluído com sucesso.'); 
            window.location.href='/'; // Volta para a home
        } else {
            alert('Erro: ' + json.erro);
        }
    } catch (e) { alert('Erro de conexão'); }
}
</script>

<style scoped>
.solicitacoes-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-actions { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.titulo-box h2 { margin: 0; color: #333; font-size: 18px; }
.tabela-container { flex: 1; padding: 20px; overflow-y: auto; }
.footer-actions { padding: 15px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; background: #f8f9fa; }

.btn-rm-item { border: 1px solid #dc3545; color: #dc3545; background: #fff; border-radius: 4px; cursor: pointer; font-weight: bold; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
.btn-rm-item:hover { background: #dc3545; color: white; }

.btn-salvar { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; }
.btn-salvar:disabled { background: #94d3a2; cursor: not-allowed; }
.btn-salvar:hover:not(:disabled) { background: #218838; }

.btn-danger { background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-danger:hover { background: #c82333; }

/* Ajustes finos do Datatable */
:deep(table.dataTable) { border-collapse: collapse !important; width: 100% !important; }
:deep(table.dataTable thead th) { background: #f1f3f5; border-bottom: 2px solid #dee2e6; color: #495057; padding: 10px; font-size: 13px; }
:deep(table.dataTable tbody td) { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle; }
</style>