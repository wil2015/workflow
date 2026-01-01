<template>
  <div class="app-container">
    
    <template v-if="areaAtual">
       <FornecedoresList v-if="areaAtual === 'fornecedores'" />
       <SolicitacoesList v-else-if="areaAtual === 'solicitacoes'" />
       <CotacaoValores v-else-if="areaAtual === 'lancamento'" />
       <GradeComparativa v-else-if="areaAtual === 'grade'" />
       <div v-else class="erro-modulo">Módulo não encontrado: {{ areaAtual }}</div>
    </template>


    <template v-else>
      
      <div v-if="modoAtual === 'viewer'" class="viewer-mode">
        <div class="toolbar">
          <button @click="voltarAoDashboard" class="btn-voltar">← Voltar ao Painel</button>
          <span class="titulo-fluxo">{{ tituloAtual }}</span>
        </div>
        
        <BpmnViewer 
          v-if="componenteCarregado"
          :xmlUrl="urlXmlAtual" 
          :fluxoId="fluxoIdAtual"
          :instanceId="instanceIdAtual"
        />
        <div v-else>Carregando componente BPMN...</div>
      </div>

      <Dashboard v-else @abrir-processo="abrirProcesso" @iniciar-novo="iniciarNovo" />
      
    </template>

  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue';

// --- IMPORTAÇÃO DOS MÓDULOS (Componentes que abrem nos Iframes) ---
// Use defineAsyncComponent para não pesar o carregamento inicial do Dashboard
const SolicitacoesList = defineAsyncComponent(() => import('./components/SolicitacoesList.vue'));
const FornecedoresList = defineAsyncComponent(() => import('./components/FornecedoresList.vue'));
const CotacaoValores = defineAsyncComponent(() => import('./components/CotacaoValores.vue'));
const GradeComparativa = defineAsyncComponent(() => import('./components/GradeComparativa.vue'));

const Dashboard = defineAsyncComponent(() => import('./components/Dashboard.vue'));
const BpmnViewer = defineAsyncComponent(() => import('./components/BpmnViewer.vue'));

// --- LÓGICA DE DETECÇÃO (A Chave do Sucesso) ---
// Lê a variável que injetamos no PHP (selecionar_fornecedores.php)
// Se for null/undefined, significa que estamos no Dashboard principal
const areaAtual = ref(window.AREA_ATUAL || null);


// --- SUAS VARIÁVEIS ORIGINAIS (Mantidas) ---
const modoAtual = ref('dashboard');
const urlXmlAtual = ref('');
const tituloAtual = ref('');
const fluxoIdAtual = ref(null);
const instanceIdAtual = ref(null);
const componenteCarregado = ref(true);

// --- SUAS FUNÇÕES ORIGINAIS (Mantidas) ---
function iniciarNovo(fluxo) {
  console.log("Iniciando NOVO fluxo:", fluxo);
  modoAtual.value = 'viewer';
  urlXmlAtual.value = '/public/' + fluxo.arquivo_xml;
  tituloAtual.value = fluxo.nome_do_fluxo;
  fluxoIdAtual.value = fluxo.fluxo_id; 
  instanceIdAtual.value = null; 
  window.history.pushState({}, '', `?novo=${fluxo.arquivo_xml}&fluxo_id=${fluxo.fluxo_id}`);
}

function abrirProcesso(proc) {
  console.log("Abrindo processo existente:", proc);
  modoAtual.value = 'viewer';
  urlXmlAtual.value = '/public/' + proc.arquivo_xml;
  tituloAtual.value = `Proc. #${proc.id}`;
  fluxoIdAtual.value = proc.fluxo_id; 
  instanceIdAtual.value = proc.id;    
  window.history.pushState({}, '', `?id=${proc.id}`);
}

function voltarAoDashboard() {
  modoAtual.value = 'dashboard';
  instanceIdAtual.value = null;
  fluxoIdAtual.value = null;
  urlXmlAtual.value = '';
  window.history.pushState({}, '', '/');
}
</script>

<style>
body { margin: 0; font-family: sans-serif; background: #f4f6f9; }
.toolbar { background: #333; color: white; padding: 10px; display: flex; gap: 20px; }
.btn-voltar { cursor: pointer; }
.erro-modulo { padding: 20px; color: red; font-weight: bold; }
</style>