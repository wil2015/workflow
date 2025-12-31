<template>
  <div class="app-container">
    
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

  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue';

// Importação segura: Se os componentes não existirem, não quebra a tela branca
const Dashboard = defineAsyncComponent(() => import('./components/Dashboard.vue'));
const BpmnViewer = defineAsyncComponent(() => import('./components/BpmnViewer.vue'));

const modoAtual = ref('dashboard');
const urlXmlAtual = ref('');
const tituloAtual = ref('');
const fluxoIdAtual = ref(null);
const instanceIdAtual = ref(null);
const componenteCarregado = ref(true);


function iniciarNovo(fluxo) {
  console.log("Iniciando NOVO fluxo:", fluxo);

  modoAtual.value = 'viewer';
  urlXmlAtual.value = '/public/' + fluxo.arquivo_xml;
  tituloAtual.value = fluxo.nome_do_fluxo;
  
  fluxoIdAtual.value = fluxo.fluxo_id; 
  
  // --- A CORREÇÃO ESSENCIAL ---
  instanceIdAtual.value = null; // <--- LIMPA O ID ANTIGO!
  // ----------------------------
  
  // Atualiza URL sem ID de instância
  window.history.pushState({}, '', `?novo=${fluxo.arquivo_xml}&fluxo_id=${fluxo.fluxo_id}`);
}

function abrirProcesso(proc) {
  // Debug para garantir que o objeto 'proc' tem os dados
  console.log("Abrindo processo existente:", proc);

  modoAtual.value = 'viewer';
  urlXmlAtual.value = '/public/' + proc.arquivo_xml;
  tituloAtual.value = `Proc. #${proc.id}`;
  
  // --- AS LINHAS QUE FALTAVAM ---
  fluxoIdAtual.value = proc.fluxo_id; // Garante que o fluxo não fique nulo
  instanceIdAtual.value = proc.id;    // <--- O MAIS IMPORTANTE: Passa o ID 33
  // ------------------------------

  // Atualiza a URL do navegador para você poder dar F5 se precisar
  window.history.pushState({}, '', `?id=${proc.id}`);
}

function voltarAoDashboard() {
  modoAtual.value = 'dashboard';
  // Limpa tudo para evitar bugs na próxima navegação
  instanceIdAtual.value = null;
  fluxoIdAtual.value = null;
  urlXmlAtual.value = '';
  window.history.pushState({}, '', '/'); // Limpa a URL do navegador
}
</script>

<style>
body { margin: 0; font-family: sans-serif; background: #f4f6f9; }
.toolbar { background: #333; color: white; padding: 10px; display: flex; gap: 20px; }
.btn-voltar { cursor: pointer; }
</style>