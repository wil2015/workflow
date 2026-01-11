<template>
  <div class="bpmn-wrapper">
    
    <div class="top-bar">
      <div class="left">
        <button class="btn-voltar" @click="voltar">
           ← Voltar
        </button>
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
            <iframe v-if="iframeUrl" :src="iframeUrl" class="iframe-legado"></iframe>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';

const canvasRef = ref(null);
const modalAberto = ref(false);
const iframeUrl = ref('');
const titulo = ref('Carregando fluxo...');
let viewer = null;

const instanceId = ref(null);
const fluxoId = ref(null);
const novoXml = ref(null);

onMounted(async () => {
    const data = window.VIEW_DATA || {};
    instanceId.value = data.instance_id || null;
    fluxoId.value = data.fluxo_id || null;
    novoXml.value = data.novo_xml || null;

    viewer = new BpmnNavigatedViewer({ container: canvasRef.value });
    
    const eventBus = viewer.get('eventBus');
    eventBus.on('element.click', (e) => {
        const type = e.element.type;
        if (type.toLowerCase().includes('task') || type.toLowerCase().includes('event')) {
            clicarTarefa(e.element.id);
        }
    });

    if (instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    } else if (novoXml.value) {
        titulo.value = "Iniciando Novo Processo";
        await carregarDiagrama(novoXml.value);
        console.log("Fluxo Novo detectado.");
        setTimeout(() => { clicarTarefa('Activity_SelecionarSolicitacao'); }, 500); 
    } else {
        window.location.href = '/';
    }
});

// --- FUNÇÃO DE EXCLUSÃO ---
async function confirmarCancelamento() {
    if (!confirm("Tem certeza que deseja EXCLUIR este processo?\nIsso apagará todos os dados e não tem volta.")) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('acao', 'cancelar_processo');
        formData.append('id_processo', instanceId.value);

        const res = await fetch('/backend/modulos/ExecucaoFluxo/FluxoController.php', {
            method: 'POST',
            body: formData
        });

        if (!res.ok) throw new Error("Erro de rede ao excluir");

        const json = await res.json();
        if (json.erro) throw new Error(json.erro);

        window.location.replace('/'); 

    } catch (e) {
        alert("Erro ao cancelar: " + e.message);
    }
}

async function carregarProcessoExistente(id) {
    try {
        // Removemos o texto "Carregando..." para evitar piscar o título se for apenas um refresh
        // titulo.value = "Carregando dados..."; 
        
        const url = `/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=ler_tarefa&id_instancia=${id}`;
        
        const res = await fetch(url); 
        
        if (!res.ok) {
            console.warn("Processo não encontrado. Voltando para home.");
            window.location.href = '/'; 
            return;
        }

        const json = await res.json();
        if (json.erro) {
            console.warn(json.erro);
            window.location.href = '/';
            return;
        }

        fluxoId.value = json.fluxo_id; 
        titulo.value = `Processo #${id} - ${json.nome_fluxo}`;
        
        if (json.arquivo_xml) await carregarDiagrama(json.arquivo_xml);
        else throw new Error("XML não definido.");

        // Destaque visual da tarefa atual (Pintar de Verde)
        if (viewer && json.tarefa && json.tarefa.id_xml) {
            const canvas = viewer.get('canvas');
            const elementRegistry = viewer.get('elementRegistry');
            const graphicsFactory = viewer.get('graphicsFactory');
            
            // Limpa cores anteriores (opcional, mas bom pra garantir)
            // Aqui estamos simplificando reimportando o XML acima, que já limpa.
            
            // Adiciona marcador CSS se quiser ou usa overlay
            canvas.addMarker(json.tarefa.id_xml, 'highlight-current');
        }

    } catch (e) {
        console.error(e);
        if (!e.message.includes('não encontrado')) {
             titulo.value = "Erro: " + e.message;
        } else {
             window.location.href = '/';
        }
    }
}

async function carregarDiagrama(xmlFilename) {
    try {
        const res = await fetch('/public/' + xmlFilename); 
        if(!res.ok) throw new Error("XML não acessível: " + xmlFilename);
        const xml = await res.text();
        
        // Importa XML (Isso desenha o fluxo)
        await viewer.importXML(xml);
        
        // Ajusta Zoom apenas se for a primeira carga
        // Se já tiver zoom definido pelo usuário, tenta manter (opcional)
        viewer.get('canvas').zoom('fit-viewport');

    } catch (err) {
        console.error(err);
        alert("Erro visual: " + err.message);
    }
}

async function clicarTarefa(taskId) {
    if (!instanceId.value) {
        if (!taskId.toLowerCase().includes('solicitacao')) {
             alert("⚠️ Salve o processo primeiro.");
             return; 
        }
    }
    const urlFetch = `/backend/router.php?task_id=${taskId}&fluxo_id=${fluxoId.value}`;
    try {
        const res = await fetch(urlFetch);
        if (res.status === 404) return; 

        const config = await res.json();
        
        if (config.sucesso && config.url) {
            const separator = config.url.includes('?') ? '&' : '?';
            const idParaUrl = instanceId.value ? instanceId.value : '';
            iframeUrl.value = `${config.url}${separator}instance_id=${idParaUrl}&fluxo_id=${fluxoId.value}`;
            modalAberto.value = true;
        } else if (config.erro) {
            alert(config.erro);
        }
    } catch (e) {
        console.error(e);
    }
}

// --- AQUI ESTÁ A CORREÇÃO (REFRESH SUAVE) ---
async function fecharModal() {
    modalAberto.value = false;
    iframeUrl.value = '';
    
    // Se temos um processo aberto, apenas recarregamos os dados (JSON)
    // Sem dar reload na página inteira (F5)
    if(instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    }
}

function voltar() {
    window.location.href = '/';
}
</script>

<style scoped>
.bpmn-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
.top-bar { height: 50px; background: #fff; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 10; }
.btn-voltar { cursor: pointer; border: 1px solid #d1d5db; background: #f9fafb; padding: 6px 12px; border-radius: 6px; font-weight: 600; color: #374151; }
.btn-cancelar { cursor: pointer; border: 1px solid #fca5a5; background: #fef2f2; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: 600; }
.btn-cancelar:hover { background: #fee2e2; border-color: #ef4444; }
.center { display: flex; flex-direction: column; align-items: center; line-height: 1.2; }
.titulo { font-weight: 700; font-size: 15px; color: #111827; }
.badge-id { font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 4px; font-weight: 600; }
.canvas-container { flex: 1; background: #f3f4f6; position: relative; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px); display: flex; justify-content: center; align-items: center; }
.modal-content { background: white; width: 95%; height: 95%; max-width: 1400px; border-radius: 8px; position: relative; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
.modal-close { position: absolute; top: -12px; right: -12px; width: 32px; height: 32px; border-radius: 50%; background: #ef4444; color: white; border: 2px solid #fff; font-size: 20px; cursor: pointer; z-index: 1001; display: flex; align-items: center; justify-content: center; }
.modal-body { flex: 1; overflow: hidden; border-radius: 8px; background: #fff; }
.iframe-legado { width: 100%; height: 100%; border: none; display: block; }

/* Destaque para a tarefa atual no diagrama */
:deep(.highlight-current:not(.djs-connection) .djs-visual > :nth-child(1)) {
    stroke: #10b981 !important; /* Verde Borda */
    stroke-width: 3px !important;
    fill: #ecfdf5 !important;   /* Verde Fundo Suave */
}
</style>