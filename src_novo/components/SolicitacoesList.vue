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

// --- 1. LEITURA IMEDIATA DOS PARÂMETROS (Mudança Crucial) ---
// Fazemos isso fora do onMounted para que a variável exista ANTES da tabela carregar
const params = new URLSearchParams(window.location.search);
const urlId = params.get('instance_id');

// Garante que se vier a string "null" ou vazio, vire null real
const instanceId = ref((urlId === 'null' || !urlId) ? null : urlId);
const fluxoId = ref(params.get('fluxo_id'));

const dt = ref(null); 
const idsSelecionados = reactive(new Set());

// --- 2. DEFINIÇÃO DE COLUNAS ---
const columns = [
  { 
    data: null, title: '', orderable: false, searchable: false, width: '30px',
    render: (data, type, row) => {
        if (row.status === 'vinculado') {
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

// --- 3. OPÇÕES DA TABELA (Ordenação Corrigida) ---
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
    
    // MUDANÇA AQUI: Ordena pela coluna 6 (Status) DESCendente (Vinculados no topo)
    order: [[ 6, "desc" ]], 

    ajax: {
        url: '/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=listar_solicitacoes',
        data: (d) => { 
            // Como instanceId já foi lido lá em cima, ele vai correto na 1ª chamada
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

// --- 4. ONMOUNTED (Só para funções globais) ---
onMounted(() => {
  // Não precisamos ler URL aqui, já lemos no topo.
  
  // Funções para os botões HTML (onclick)
  window.toggleVue = (id) => {
      if(idsSelecionados.has(id)) idsSelecionados.delete(id);
      else idsSelecionados.add(id);
  };
  window.remItem = (id) => {
     const parts = id.split('-');
     removerItem({id_solicitacao_senior: `${parts[1]}-${parts[2]}`, id_unico: id});
  };
});

// --- AÇÕES ---

async function salvar() {
    const listaIds = Array.from(idsSelecionados);
    if (listaIds.length === 0) return;
    
    const formData = new FormData();
    formData.append('acao', 'vincular');
    formData.append('id_fluxo_definicao', fluxoId.value || 1);
    
    // Envia o ID se estiver editando (Evita criar processo novo duplicado)
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
            
            // Se foi criação (não tinha ID), recarrega a página para pegar o novo ID
            if(!instanceId.value) window.location.reload(); 
        } else { 
            alert('Erro: ' + json.erro); 
        }
    } catch (e) { alert('Erro na requisição'); }
}

async function removerItem(item) {
    if(!confirm(`Remover item ${item.id_solicitacao_senior}?`)) return;
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
    if(!confirm("Excluir PROCESSO INTEIRO?")) return;
    const formData = new FormData();
    formData.append('acao', 'cancelar_processo');
    formData.append('id_processo', instanceId.value);
    
    try {
        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.sucesso) { alert('Excluído'); window.location.href='/'; }
    } catch (e) { alert('Erro'); }
}
</script>

<style scoped>
.solicitacoes-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: sans-serif; }
.header-actions { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.tabela-container { flex: 1; padding: 20px; overflow-y: auto; }
.footer-actions { padding: 15px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; }
.btn-rm-item { border: 1px solid #dc3545; color: #dc3545; background: #fff; border-radius: 4px; cursor: pointer; font-weight: bold; width: 24px; }
.btn-salvar { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.btn-salvar:disabled { opacity: 0.5; }
.btn-danger { background: #dc3545; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; }
</style>