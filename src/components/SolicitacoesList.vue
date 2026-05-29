<template>
  <div class="ordens-compra-wrapper">
    <div class="header-actions">
      <div class="titulo-box">
        <h2 v-if="instanceId">Gerenciar Processo #{{ instanceId }}</h2>
        <h2 v-else>Nova Ordem de Compra</h2>
      </div>
    </div>

    <div class="tabela-container">
      <DataTable 
        class="display stripe hover cell-border order-column compact"
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

// --- LÓGICA (Mantida igual) ---
const props = defineProps({ instanceId: String });
const params = new URLSearchParams(window.location.search);
const urlId = params.get('instance_id');
const fluxoId = ref(params.get('fluxo_id'));
const idCalculado = props.instanceId || ((urlId === 'null' || !urlId) ? null : urlId);
const instanceId = ref(idCalculado);

const dt = ref(null); 
const idsSelecionados = reactive(new Set());

// --- DEFINIÇÃO DE COLUNAS (Visual Ajustado) ---
const columns = [
  { 
    data: null, title: '', orderable: false, searchable: false, width: '30px', className: 'dt-center',
    render: (data, type, row) => {
        if (row.status === 'vinculado') {
            // Botão X quadrado com borda vermelha (Estilo Legacy)
            return `<button class="btn-rm-legacy" onclick="window.remItem('${row.id_unico}')" title="Remover">×</button>`;
        } else if (row.status === 'bloqueado') {
            return `<span style="font-size:16px">🔒</span>`;
        } else {
            const checked = idsSelecionados.has(row.id_unico) ? 'checked' : '';
            return `<input type="checkbox" class="chk-legacy" value="${row.id_unico}" ${checked} onchange="window.toggleVue('${row.id_unico}')">`;
        }
    }
  },
  { data: 'data_ordem_compra', title: 'Data OC', width: '90px', className: 'dt-body-center', render: d => d ? d.split('-').reverse().join('/') : '' },
  { data: 'ordem_compra_label', title: 'Ordem de Compra', width: '105px', className: 'dt-body-center bold-text' },
  { data: 'item_ordem_label', title: 'Item OC', width: '95px', className: 'dt-body-center' },
  { data: 'descricao_item', title: 'Item / Descrição',
    render: (d,t,r) => `<div class="prod-desc">${d}</div><div class="prod-sub">Cod. ${r.codigo_item || '-'} - Seq. ${r.sequencia_original || '-'} - ${parseFloat(r.quantidade).toLocaleString('pt-BR')}</div>`
  },
  { data: 'preco_unitario', title: 'Preço Unit.', width: '100px', className: 'dt-body-right', render: d => `R$ ${parseFloat(d).toLocaleString('pt-BR', {minimumFractionDigits: 2})}` },
  { 
    data: 'status', title: 'Status', width: '100px', className: 'dt-center',
    render: (d, t, r) => {
      if(d==='vinculado') return `<span class="badge-vinc">Vinculado</span>`;
      if(d==='bloqueado') return `<span class="badge-bloq">Proc. #${r.proc_bloqueador}</span>`;
      return `<span class="badge-disp">Disponível</span>`;
    }
  }
];

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
    order: [[ 2, "desc" ]],
    scrollY: 'calc(100vh - 240px)', 
    scrollCollapse: true,
    ajax: {
        url: '/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=listar_ordens_compra',
        data: (d) => { 
            d.instance_id = instanceId.value; 
        },
        // --- AQUI ESTÁ A MÁGICA DO POP-UP ---
        error: function (xhr, error, thrown) {
            console.error("Erro DataTables:", xhr);
            
            let msg = "Erro desconhecido ao carregar dados.";
            
            // Tenta ler o JSON de erro que o PHP mandou
            if (xhr.responseJSON && xhr.responseJSON.erro) {
                msg = xhr.responseJSON.erro;
            } else if (xhr.responseText) {
                // Se não for JSON (ex: erro fatal do PHP), pega o texto
                try {
                    const json = JSON.parse(xhr.responseText);
                    if(json.erro) msg = json.erro;
                } catch(e) {
                    msg = "Erro fatal no servidor (Verifique o Console/Network).";
                }
            }

            alert("ERRO NO SISTEMA:\n" + msg);
            
            // Para o loading infinito
            // (Hack: força o processing a parar injetando HTML vazio ou recriando a tabela se necessário)
            // Mas o alert ja resolve a necessidade de expor o erro.
        }
    },
    drawCallback: () => {
        idsSelecionados.forEach(id => {
            const el = document.querySelector(`.chk-legacy[value="${id}"]`);
            if(el) el.checked = true;
        });
    }
};

onMounted(() => {
  window.toggleVue = (id) => {
      if(idsSelecionados.has(id)) idsSelecionados.delete(id);
      else idsSelecionados.add(id);
  };
  window.remItem = (id) => {
     const parts = id.split('-'); 
     removerItem({ordem_compra_label: `OC ${parts[1]} / item ${parts[2]}`, id_unico: id});
  };
});

async function salvar() {
    const listaIds = Array.from(idsSelecionados);
    if (listaIds.length === 0) return;
    
    const formData = new FormData();
    formData.append('acao', 'vincular');
    formData.append('id_fluxo_definicao', fluxoId.value || 1);
    if (instanceId.value) formData.append('id_processo_instancia', instanceId.value);
    listaIds.forEach(id => formData.append('selecionados[]', id));

    try {
        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: formData });
        const json = await res.json();
        if(json.sucesso) {
            alert(json.msg);
            idsSelecionados.clear();
            dt.value.dt.ajax.reload(null, false);
            if(!instanceId.value) window.location.href = `/?instance_id=${json.id_processo}`;
        } else { alert('Erro: ' + json.erro); }
    } catch (e) { alert('Erro na requisição'); }
}

async function removerItem(item) {
    if(!confirm(`Remover item ${item.ordem_compra_label}?`)) return;
    const parts = item.id_unico.split('-');
    const formData = new FormData();
    formData.append('acao', 'remover_item');
    formData.append('id_processo', instanceId.value);
    formData.append('numero_oc', parts[1]);
    formData.append('sequencia_oc', parts[2]);

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
.ordens-compra-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-actions { padding: 15px 20px; background: #fff; border-bottom: 1px solid #ddd; }
.titulo-box h2 { margin: 0; color: #333; font-size: 20px; font-weight: 700; }

.tabela-container { flex: 1; padding: 10px 20px; overflow: hidden; } /* Overflow hidden pois o DataTables gerencia o scroll interno */

.footer-actions { padding: 15px 20px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; background: #f8f9fa; }

/* Botões */
.btn-salvar { background: #28a745; color: white; border: 1px solid #218838; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; }
.btn-salvar:hover:not(:disabled) { background: #218838; }
.btn-salvar:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-danger { background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; }

/* --- ESTILIZAÇÃO PROFUNDA DA TABELA (LEGACY LOOK) --- */
:deep(table.dataTable) { 
    border-collapse: collapse !important; 
    width: 100% !important; 
    font-size: 12px;
    border: 1px solid #ccc;
}

/* Cabeçalho Cinza e Negrito */
:deep(table.dataTable thead th) { 
    background: #eaebed; /* Cinza do print */
    border-bottom: 2px solid #ccc; 
    border-right: 1px solid #ddd;
    color: #333; 
    padding: 10px; 
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
}

/* Células com bordas verticais */
:deep(table.dataTable tbody td) { 
    padding: 8px 10px; 
    border-bottom: 1px solid #eee; 
    border-right: 1px solid #eee;
    vertical-align: middle;
    color: #444;
}

/* Zebra (Linhas impares/pares) - DataTables Stripe */
:deep(table.dataTable.stripe tbody tr.odd) { background-color: #f9f9f9; }
:deep(table.dataTable.stripe tbody tr.even) { background-color: #ffffff; }
:deep(table.dataTable.hover tbody tr:hover) { background-color: #e8f4ff; } /* Azul claro no hover */

/* Elementos Internos */
:deep(.btn-rm-legacy) {
    background: white; border: 1px solid #dc3545; color: #dc3545;
    width: 24px; height: 24px; cursor: pointer; font-weight: bold;
    display: flex; align-items: center; justify-content: center;
    border-radius: 3px; font-size: 16px; line-height: 1;
}
:deep(.btn-rm-legacy:hover) { background: #dc3545; color: white; }

:deep(.chk-legacy) { transform: scale(1.2); cursor: pointer; }

:deep(.bold-text) { font-weight: 700; color: #333; }
:deep(.prod-desc) { font-weight: 500; color: #333; line-height: 1.3; }
:deep(.prod-sub) { font-size: 10px; color: #666; margin-top: 2px; }

/* Badges de Status */
:deep(.badge-vinc) { color: #28a745; font-weight: 700; background: #e6f9ed; padding: 2px 6px; border-radius: 4px; font-size: 11px; border: 1px solid #c3e6cb; }
:deep(.badge-bloq) { color: #666; font-weight: 700; background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
:deep(.badge-disp) { color: #007bff; font-weight: 600; font-size: 11px; }
</style>
