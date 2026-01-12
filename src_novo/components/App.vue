<template>
  <BpmnViewer 
    v-if="modoFluxo" 
  />

  <Dashboard 
    v-else 
  />
  
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Dashboard from './components/Dashboard.vue';
import BpmnViewer from './components/BpmnViewer.vue';

// Estado reativo
const instanceId = ref(null);
const isNovo = ref(false);

// Computed: Define se devemos mostrar o Fluxo ou o Dashboard
const modoFluxo = computed(() => {
    // Se tiver ID (editando) ou flag 'novo' (criando), mostra o fluxo
    return instanceId.value !== null || isNovo.value === true;
});

onMounted(() => {
    // 1. Lê os parâmetros da URL atual
    // Ex: http://localhost/?instance_id=58
    const params = new URLSearchParams(window.location.search);
    
    const idUrl = params.get('instance_id');
    const novoUrl = params.get('novo'); // Vem o nome do XML ou ID do fluxo

    // 2. Atualiza o estado
    if (idUrl) {
        instanceId.value = idUrl;
    }
    
    if (novoUrl) {
        isNovo.value = true;
    }
    
    // 3. Suporte a Legado (caso venha de algum PHP antigo injetando variável global)
    if (!instanceId.value && window.VIEW_DATA && window.VIEW_DATA.instance_id) {
        instanceId.value = window.VIEW_DATA.instance_id;
    }
});
</script>

<style>
/* Reset Global Simples para garantir tela cheia e fonte padrão */
body { 
    margin: 0; 
    padding: 0; 
    font-family: 'Segoe UI', sans-serif; 
    background: #f4f6f9; 
}
</style>