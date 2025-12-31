<template>
  <div class="bpmn-wrapper">
    <div ref="canvasRef" class="canvas-container"></div>
    
    <div v-if="modalAberto" class="modal-overlay">
      <div class="modal-content">
        <button class="modal-close" @click="fecharModal">&times;</button>
        <div class="modal-body">
            <div style="background: #eee; padding: 5px; font-size: 10px; color: #666; text-align: center;">
                DEBUG URL: {{ iframeUrl }}
            </div>
            
            <iframe v-if="iframeUrl" :src="iframeUrl" style="width:100%; height:100%; border:none;"></iframe>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';

// 1. RECEBE O ID DA INSTÂNCIA DO APP.VUE
const props = defineProps(['xmlUrl', 'fluxoId', 'instanceId']); 

const canvasRef = ref(null);
let viewer = null;

const modalAberto = ref(false);
const iframeUrl = ref('');

onMounted(() => {
  if (props.xmlUrl) carregarDiagrama();
});

watch(() => props.xmlUrl, () => {
    carregarDiagrama();
});

async function carregarDiagrama() {
    if (!viewer) {
        viewer = new BpmnNavigatedViewer({ container: canvasRef.value });
        const eventBus = viewer.get('eventBus');
        eventBus.on('element.click', async (e) => {
            const element = e.element;
            const type = element.type;
            if (type.toLowerCase().includes('task') || type.toLowerCase().includes('event')) {
                await clicarTarefa(element.id);
            }
        });
    }

    try {
        const res = await fetch(props.xmlUrl);
        if(!res.ok) throw new Error("XML não encontrado");
        const xml = await res.text();
        await viewer.importXML(xml);
        viewer.get('canvas').zoom('fit-viewport');
        
        // Pinta tarefas coloridas se necessário (feature futura)
    } catch (err) {
        console.error("Erro ao carregar BPMN", err);
    }
}

async function clicarTarefa(taskId) {
    // 2. BUSCA A ROTA NO BACKEND
    const urlFetch = `/backend/router.php?task_id=${taskId}&fluxo_id=${props.fluxoId}`;
    
    try {
        const res = await fetch(urlFetch);
        if (!res.ok) {
            alert(`Rota não configurada (404) para tarefa: ${taskId}`);
            return;
        }

        const config = await res.json();
        
        if (config.sucesso && config.url) {
            const separator = config.url.includes('?') ? '&' : '?';
            
            // 3. O SEGREDO: REPASSA O INSTANCE_ID PARA O IFRAME
            // Se props.instanceId for null (Novo), envia vazio.
            // Se props.instanceId for 33 (Edição), envia 33.
            const idParaUrl = props.instanceId ? props.instanceId : '';
            
            const finalUrl = `${config.url}${separator}instance_id=${idParaUrl}&fluxo_id=${props.fluxoId}`;
            
            console.log("Abrindo Iframe:", finalUrl); // Olhe no Console F12
            iframeUrl.value = finalUrl;
            modalAberto.value = true;
        } else {
            alert(config.erro || 'Erro na configuração da rota');
        }
    } catch (e) {
        console.error(e);
        alert("Erro ao buscar rota da tarefa.");
    }
}

function fecharModal() {
    modalAberto.value = false;
    iframeUrl.value = '';
}
</script>

<style scoped>
.canvas-container { height: calc(100vh - 60px); background: white; border: 1px solid #ddd; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000; }
.modal-content { background: white; width: 90%; height: 90%; padding: 0; border-radius: 8px; position: relative; display: flex; flex-direction: column; overflow: hidden;}
.modal-close { position: absolute; top: 10px; right: 10px; font-size: 30px; border: none; background: none; cursor: pointer; color: #333; z-index: 10; font-weight: bold;}
.modal-body { flex: 1; display: flex; flex-direction: column; }
</style>