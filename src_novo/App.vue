<template>
  <div class="app-container">
    <div v-if="erro" class="tela-erro">
        <h3>⚠️ Erro de Carregamento</h3>
        <p>{{ erro }}</p>
        <small>Verifique se o arquivo existe na pasta components e se o PHP enviou o nome correto.</small>
    </div>
    
    <component :is="componenteAtual" v-else-if="componenteAtual" />
    
    <div v-else class="tela-loading">
        <div class="spinner"></div>
        <p>Carregando módulo...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, shallowRef, onMounted, defineAsyncComponent } from 'vue';

// "shallowRef" é melhor que "ref" para guardar componentes (performance)
const componenteAtual = shallowRef(null);
const erro = ref(null);

onMounted(() => {
    // 1. O PHP injetou isso no window (Lembra do visualizar_fluxo.php?)
    const data = window.VIEW_DATA || {};
    const nomeArquivo = data.componente; // Ex: 'BpmnViewer.vue'

    if (!nomeArquivo) {
        erro.value = "O Backend não informou qual componente carregar (window.VIEW_DATA.componente vazio).";
        return;
    }

    // 2. O Pulo do Gato: Importação Dinâmica
    // O Vite é inteligente: ele vê `./components/${nomeArquivo}` e prepara todos os arquivos
    // da pasta components para serem carregados sob demanda.
    componenteAtual.value = defineAsyncComponent(() => 
        import(`./components/${nomeArquivo}`)
            .catch(err => {
                console.error("Erro no import dinâmico:", err);
                erro.value = `Não foi possível carregar o arquivo: src_novo/components/${nomeArquivo}`;
            })
    );
});
</script>

<style scoped>
/* Estilos globais do container */
.app-container { min-height: 100vh; font-family: 'Segoe UI', sans-serif; }

.tela-erro { 
    padding: 40px; text-align: center; color: #721c24; 
    background-color: #f8d7da; border: 1px solid #f5c6cb; margin: 20px; border-radius: 8px; 
}

.tela-loading { 
    display: flex; flex-direction: column; align-items: center; 
    justify-content: center; height: 100vh; color: #666; 
}
.spinner {
    border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%;
    width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 15px;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>