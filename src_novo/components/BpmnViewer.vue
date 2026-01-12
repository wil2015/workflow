<template>
  <div class="bpmn-wrapper">
    
    <div class="top-bar">
      <div class="left">
        <button class="btn-voltar" @click="voltar">← Voltar</button>
      </div>
      <div class="center">
        <span class="titulo">{{ titulo }}</span>
        <span v-if="fluxoId" class="badge-id">Fluxo ID: {{ fluxoId }}</span>
      </div>
      <div class="right">
          <button v-if="instanceId" class="btn-cancelar" @click="confirmarCancelamento">
             🗑️ Cancelar Processo
          </button>
      </div> 
    </div>

    <div ref="canvasRef" class="canvas-container"></div>

    <div v-if="modalAberto" class="modal-overlay">
      <div class="modal-content">
        <button class="modal-close" @click="fecharModal" title="Fechar">&times;</button>
        
        <div class="modal-body">
            <component 
                v-if="componenteAtual"
                :is="componenteAtual"
                :instance-id="instanceId"
                :task-id="taskIdAtual"
                @fechar="fecharModal"
            />
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, shallowRef } from 'vue';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';

// Importa o nosso mapa novo
import { getComponentForTask } from './taskMapper.js';

const canvasRef = ref(null);
const modalAberto = ref(false);

// 'shallowRef' é melhor para guardar componentes inteiros
const componenteAtual = shallowRef(null);
const taskIdAtual = ref('');

const titulo = ref('Carregando fluxo...');
let viewer = null;

const instanceId = ref(null);
const fluxoId = ref(null);
const novoXml = ref(null);

onMounted(async () => {
    // 1. Ler dados da URL (SPA)
    const params = new URLSearchParams(window.location.search);
    instanceId.value = params.get('instance_id');
    novoXml.value = params.get('novo');
    fluxoId.value = params.get('fluxo_id');

    // 2. Fallback para dados injetados (se houver)
    if (!instanceId.value && window.VIEW_DATA) {
        instanceId.value = window.VIEW_DATA.instance_id;
    }

    // 3. Inicializar BPMN
    viewer = new BpmnNavigatedViewer({ container: canvasRef.value });
    const eventBus = viewer.get('eventBus');
    
    eventBus.on('element.click', (e) => {
        const type = e.element.type;
        // Só abre se for Tarefa (UserTask)
        if (type.toLowerCase().includes('task')) {
            clicarTarefa(e.element.id);
        }
    });

    // 4. Carregar Dados
    if (instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    } else if (novoXml.value) {
        titulo.value = "Iniciando Novo Processo";
        await carregarDiagrama(novoXml.value);
        // Abre a primeira tarefa automaticamente
        setTimeout(() => { clicarTarefa('Activity_SelecionarSolicitacao'); }, 500); 
    } else {
        // Se não tem ID nem XML, volta pro Dashboard
        window.location.href = '/';
    }
});

// --- LÓGICA DE ABERTURA (SEM IFRAME) ---
function clicarTarefa(taskId) {
    if (!instanceId.value && !taskId.includes('Solicitacao')) {
         alert("Salve o processo primeiro.");
         return; 
    }

    console.log("Abrindo tarefa:", taskId);
    
    // Busca o componente no mapa
    componenteAtual.value = getComponentForTask(taskId);
    taskIdAtual.value = taskId;
    
    modalAberto.value = true;
}

async function fecharModal() {
    modalAberto.value = false;
    componenteAtual.value = null; // Limpa memória
    
    // Atualiza status do diagrama (pinta de verde, etc)
    if(instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    }
}

// --- AUXILIARES (Backend Calls) ---
async function carregarProcessoExistente(id) {
    try {
        const url = `/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=ler_tarefa&id_instancia=${id}`;
        const res = await fetch(url);
        if (!res.ok) throw new Error("Erro na API");
        
        const json = await res.json();
        if (json.erro) {
            console.warn(json.erro);
            return;
        }

        fluxoId.value = json.fluxo_id; 
        titulo.value = `Processo #${id} - ${json.nome_fluxo}`;
        
        if (json.arquivo_xml) await carregarDiagrama(json.arquivo_xml);

        // Pinta tarefa atual
        if (viewer && json.tarefa && json.tarefa.id_xml) {
            const canvas = viewer.get('canvas');
            canvas.addMarker(json.tarefa.id_xml, 'highlight-current');
        }
    } catch (e) {
        console.error(e);
    }
}

async function carregarDiagrama(xmlName) {
    try {
        // Assume que o XML está na pasta public
        const res = await fetch('/public/' + xmlName); 
        const xml = await res.text();
        await viewer.importXML(xml);
        viewer.get('canvas').zoom('fit-viewport');
    } catch (err) { console.error(err); }
}

async function confirmarCancelamento() {
    if (!confirm("Tem certeza que deseja EXCLUIR este processo?")) return;
    try {
        const fd = new FormData();
        fd.append('acao', 'cancelar_processo');
        fd.append('id_processo', instanceId.value);
        await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', { method: 'POST', body: fd });
        window.location.href = '/'; 
    } catch (e) { alert(e.message); }
}

function voltar() {
    // Volta para o Dashboard limpando a URL
    window.location.href = window.location.pathname; 
}
</script>

<style scoped>
/* REAPROVEITE O SEU CSS EXISTENTE DO BPMNVIEWER */
.bpmn-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
.top-bar { height: 50px; background: #fff; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 10; }
.btn-voltar { cursor: pointer; border: 1px solid #d1d5db; background: #f9fafb; padding: 6px 12px; border-radius: 6px; font-weight: 600; color: #374151; }
.btn-cancelar { cursor: pointer; border: 1px solid #fca5a5; background: #fef2f2; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: 600; }
.canvas-container { flex: 1; background: #f3f4f6; position: relative; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px); display: flex; justify-content: center; align-items: center; }
.modal-content { background: white; width: 95%; height: 95%; max-width: 1400px; border-radius: 8px; position: relative; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
.modal-close { position: absolute; top: -12px; right: -12px; width: 32px; height: 32px; border-radius: 50%; background: #ef4444; color: white; border: 2px solid #fff; font-size: 20px; cursor: pointer; z-index: 1001; display: flex; align-items: center; justify-content: center; }
.modal-body { flex: 1; overflow: hidden; border-radius: 8px; background: #fff; display: flex; flex-direction: column; }
:deep(.highlight-current:not(.djs-connection) .djs-visual > :nth-child(1)) {
    stroke: #10b981 !important; stroke-width: 3px !important; fill: #ecfdf5 !important;
}
</style>