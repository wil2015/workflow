<template>
  <div class="app-root">
    <BpmnViewer 
      v-if="modoFluxo" 
    />

    <Dashboard 
      v-else 
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

// Importa os componentes da pasta components
import Dashboard from './components/Dashboard.vue';
import BpmnViewer from './components/BpmnViewer.vue';

// Estado reativo
const instanceId = ref(null);
const isNovo = ref(false);

// Computed: Define se devemos mostrar o Fluxo ou o Dashboard
const modoFluxo = computed(() => {
    return instanceId.value !== null || isNovo.value === true;
});

onMounted(() => {
    // 1. Lê a URL do navegador
    const params = new URLSearchParams(window.location.search);
    
    const idUrl = params.get('instance_id');
    const novoUrl = params.get('novo'); 

    // 2. Atualiza o estado para trocar a tela
    if (idUrl) instanceId.value = idUrl;
    if (novoUrl) isNovo.value = true;
    
    console.log("App Iniciado. ID:", instanceId.value, "Novo:", isNovo.value);
});
</script>

<style>
/* Reset Global */
body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; }
</style>