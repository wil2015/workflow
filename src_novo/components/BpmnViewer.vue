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
      <div class="right"></div> 
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

// Importa o mapa de tarefas
import { getComponentForTask } from './taskMapper.js';

const canvasRef = ref(null);
const modalAberto = ref(false);

// 'shallowRef' é ideal para componentes dinâmicos
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

    // 2. Fallback para dados legados (se houver)
    if (!instanceId.value && window.VIEW_DATA) {
        instanceId.value = window.VIEW_DATA.instance_id;
    }

    // 3. Inicializar BPMN
    viewer = new BpmnNavigatedViewer({ container: canvasRef.value });
    const eventBus = viewer.get('eventBus');
    
    eventBus.on('element.click', (e) => {
        const type = e.element.type;
        // Só abre se for Tarefa (UserTask)
        if (type.toLowerCase().includes('task') || type.toLowerCase().includes('catchevent'))  {
            clicarTarefa(e.element.id);
        }
    });

    // 4. Carregar Dados
    if (instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    } else if (novoXml.value) {
        titulo.value = "Iniciando Novo Processo";
        await carregarDiagrama(novoXml.value);
        // Abre a primeira tarefa automaticamente para facilitar
        setTimeout(() => { clicarTarefa('Activity_SelecionarSolicitacao'); }, 500); 
    } else {
        window.location.href = '/';
    }
});

// --- LÓGICA DE ABERTURA ---
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
    
    // Atualiza status do diagrama (pinta de verde, etc) ao voltar
    if(instanceId.value) {
        await carregarProcessoExistente(instanceId.value);
    }
}

// --- CARREGAMENTO DO PROCESSO (COM TRATAMENTO DE ERRO ROBUSTO) ---
async function carregarProcessoExistente(id) {
    try {
        const url = `/backend/modulos/ExecucaoFluxo/FluxoController.php?acao=ler_tarefa&id_instancia=${id}`;
        const res = await fetch(url);
        
        // Tenta ler o JSON independentemente do status HTTP (o PHP manda erro 500 com JSON)
        let json;
        try {
            json = await res.json();
        } catch (e) {
            // Se falhar o parse (ex: erro fatal do PHP estourando HTML), json fica undefined
        }

        // Verifica se houve erro HTTP (ex: 500 Erro de Conexão, 404 Não Encontrado)
        if (!res.ok) {
            // Se o backend mandou uma mensagem explicativa, usamos ela!
            if (json && json.erro) {
                throw new Error(json.erro); 
            }
            // Se for 404 sem mensagem, aí sim é "Não encontrado"
            if (res.status === 404) {
                alert("Processo não encontrado.");
                window.location.href = '/';
                return;
            }
            // Outros erros genéricos
            throw new Error(`Erro HTTP ${res.status}: Falha ao comunicar com o servidor.`);
        }

        // Verifica erro lógico no JSON (mesmo com status 200)
        if (json && json.erro) {
            throw new Error(json.erro);
        }

        // Sucesso!
        fluxoId.value = json.fluxo_id; 
        titulo.value = `Processo #${id} - ${json.nome_fluxo}`;
        
        if (json.arquivo_xml) await carregarDiagrama(json.arquivo_xml);

        if (viewer && json.tarefa && json.tarefa.id_xml) {
            const canvas = viewer.get('canvas');
            canvas.addMarker(json.tarefa.id_xml, 'highlight-current');
        }
    } catch (e) {
        console.error(e);
        // AQUI ESTÁ O POP-UP QUE VOCÊ QUERIA
        alert("ERRO NO SISTEMA:\n" + e.message);
        
        // Opcional: Só volta pra home se for erro de "não encontrado", 
        // caso contrário deixa na tela pro dev ver o erro.
        if (e.message.includes('não encontrado')) {
             window.location.href = '/';
        } else {
             titulo.value = "Erro: " + e.message;
        }
    }
}
async function carregarDiagrama(xmlName) {
    try {
        const res = await fetch('/public/' + xmlName); 
        const xml = await res.text();
        await viewer.importXML(xml);
        viewer.get('canvas').zoom('fit-viewport');
    } catch (err) { console.error(err); }
}

function voltar() {
    window.location.href = '/'; 
}
</script>

<style scoped>
.bpmn-wrapper { height: 100vh; display: flex; flex-direction: column; background: #fff; overflow: hidden; font-family: 'Segoe UI', sans-serif; }

/* Barra Superior */
.top-bar { 
    height: 50px; background: #fff; border-bottom: 1px solid #e0e0e0; 
    display: flex; align-items: center; justify-content: space-between; 
    padding: 0 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 10; 
}

.btn-voltar { 
    cursor: pointer; border: 1px solid #d1d5db; background: #f9fafb; 
    padding: 6px 12px; border-radius: 6px; font-weight: 600; color: #374151; 
}
.btn-voltar:hover { background: #e5e7eb; }

.titulo { font-weight: 700; font-size: 15px; color: #111827; }
.badge-id { font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 4px; font-weight: 600; margin-left: 8px; }

/* Canvas do Diagrama */
.canvas-container { flex: 1; background: #f3f4f6; position: relative; }

/* Modal */
.modal-overlay { 
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px); 
    display: flex; justify-content: center; align-items: center; 
}
.modal-content { 
    background: white; width: 95%; height: 95%; max-width: 1400px; 
    border-radius: 8px; position: relative; display: flex; flex-direction: column; 
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
}
.modal-close { 
    position: absolute; top: -12px; right: -12px; width: 32px; height: 32px; 
    border-radius: 50%; background: #ef4444; color: white; border: 2px solid #fff; 
    font-size: 20px; cursor: pointer; z-index: 1001; display: flex; 
    align-items: center; justify-content: center; 
}
.modal-body { 
    flex: 1; overflow: hidden; border-radius: 8px; background: #fff; 
    display: flex; flex-direction: column; 
}

/* Destaque Verde na Tarefa Atual */
:deep(.highlight-current:not(.djs-connection) .djs-visual > :nth-child(1)) {
    stroke: #10b981 !important; stroke-width: 3px !important; fill: #ecfdf5 !important;
}
</style>