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
      <div class="right"></div> </div>

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

// IMPORTAÇÃO CORRETA DO CSS (Via NPM)
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';

// Referências
const canvasRef = ref(null);
const modalAberto = ref(false);
const iframeUrl = ref('');
const titulo = ref('Carregando fluxo...');
let viewer = null;

// Dados de Estado
const instanceId = ref(null);
const fluxoId = ref(null);
const novoXml = ref(null);


// src_novo/components/BpmnViewer.vue

onMounted(async () => {
    // 1. LEITURA LIMPA DA CONFIGURAÇÃO
    const data = window.VIEW_DATA || {};
    instanceId.value = data.instance_id || null;
    fluxoId.value = data.fluxo_id || null;
    novoXml.value = data.novo_xml || null;

    // 2. INICIALIZAÇÃO DO VIEWER (Isso é o que desenha a tela branca)
    viewer = new BpmnNavigatedViewer({ container: canvasRef.value });
    
    // Configura eventos de clique
    const eventBus = viewer.get('eventBus');
    eventBus.on('element.click', (e) => {
        const type = e.element.type;
        // Só permite clicar em Tarefas ou Eventos
        if (type.toLowerCase().includes('task') || type.toLowerCase().includes('event')) {
            clicarTarefa(e.element.id);
        }
    });

    // 3. CARREGAMENTO INTELIGENTE + AUTO-START
    if (instanceId.value) {
        // --- MODO EDIÇÃO (Processo já existe) ---
        await carregarProcessoExistente(instanceId.value);
    
    } else if (novoXml.value) {
        // --- MODO NOVO (Aqui entra a mágica) ---
        titulo.value = "Iniciando Novo Processo";
        
        // A. Primeiro desenha o diagrama
        await carregarDiagrama(novoXml.value);

        // B. Depois que desenhou, tenta clicar sozinho na Solicitação
        console.log("Fluxo Novo detectado. Tentando abrir solicitação...");
        
        setTimeout(() => {
            // ATENÇÃO: Verifique se o ID da sua tarefa no XML é este mesmo ('Activity_Solicitacao')
            // Se não abrir, olhe no console qual o ID correto ou use o inspetor.
            clicarTarefa('Activity_SelecionarSolicitacao'); 
        }, 500); // Pequeno delay para garantir que o diagrama "acordou"

    } else {
        titulo.value = "Erro: Nenhum dado fornecido.";
    }
});

async function carregarProcessoExistente(id) {
    try {
        // Busca na API para pegar o nome do arquivo XML correto
        const resp = await fetch('/backend/api_dashboard.php?acao=instancias');
        const dados = await resp.json();
        const proc = dados.find(p => p.id == id);

        if (proc) {
            fluxoId.value = proc.fluxo_id; // Garante que temos o ID do fluxo pai
            titulo.value = `Processo #${id} - ${proc.nome_do_fluxo}`;
            await carregarDiagrama(proc.arquivo_xml);
            
            // Futuro: Aqui você pode colorir as tarefas concluídas (overlays)
        } else {
            titulo.value = `Processo #${id} não encontrado.`;
        }
    } catch (e) {
        console.error(e);
        titulo.value = "Erro ao carregar detalhes do processo.";
    }
}


async function carregarDiagrama(xmlFilename) {
    try {
        const res = await fetch('/public/' + xmlFilename); 
        if(!res.ok) throw new Error("XML não encontrado");
        
        const xml = await res.text();
        await viewer.importXML(xml);
        
        // --- LÓGICA DE PINTURA (NOVA) ---
        // Só aplicamos a cor se for um NOVO fluxo (para não pintar fluxos antigos em consulta)
        if (novoXml.value) {
            const elementRegistry = viewer.get('elementRegistry');
            const canvas = viewer.get('canvas');

            // Filtra tudo que é "Shape" (Formas) e ignora "Connection" (Setas)
            // Também ignoramos o 'bpmn:Process' (que é o container invisível)
            const elementosParaPintar = elementRegistry.filter(element => {
                return element.type !== 'bpmn:SequenceFlow' && // Ignora setas
                       element.type !== 'bpmn:Process' &&      // Ignora o canvas
                       element.type !== 'bpmn:Collaboration' && 
                       element.type !== 'label';               // Ignora etiquetas de texto soltas
            });

            elementosParaPintar.forEach(el => {
                canvas.addMarker(el.id, 'tema-novo-fluxo');
            });
        }
        // --------------------------------

        viewer.get('canvas').zoom('fit-viewport');
        
    } catch (err) {
        console.error(err);
        alert("Erro ao desenhar: " + err.message);
    }
}
async function clicarTarefa(taskId) {
    // --- NOVO BLOQUEIO DE SEGURANÇA ---
    if (!instanceId.value) {
        // DICA: Se a tarefa de "Solicitação" tiver um ID fixo (ex: 'Activity_Solicitacao'),
        // você pode liberar ela adicionando: && taskId !== 'ID_DA_SUA_TAREFA'
        
        // Aqui verificamos se o nome da tarefa (ou ID) sugere que é a etapa inicial
        // Se NÃO for a etapa inicial, bloqueamos:
        if (!taskId.toLowerCase().includes('solicitacao')) {
             alert("⚠️ O processo ainda não foi criado.\n\nPor favor, preencha a 'Solicitação' e salve o fluxo antes de acessar fornecedores ou grades.");
             return; // <--- O PULO DO GATO: Para tudo aqui e não deixa dar erro!
        }
    }
    // ----------------------------------

    // Consulta o Router PHP para saber qual tela abrir
    const urlFetch = `/backend/router.php?task_id=${taskId}&fluxo_id=${fluxoId.value}`;
    
    try {
        const res = await fetch(urlFetch);
        if (res.status === 404) {
            console.warn(`Tarefa ${taskId} sem rota configurada.`);
            return; 
        }

        const config = await res.json();
        
        if (config.sucesso && config.url) {
            const separator = config.url.includes('?') ? '&' : '?';
            // Se for novo, instanceId é vazio string ''
            const idParaUrl = instanceId.value ? instanceId.value : '';
            
            iframeUrl.value = `${config.url}${separator}instance_id=${idParaUrl}&fluxo_id=${fluxoId.value}`;
            modalAberto.value = true;
        } else if (config.erro) {
            alert(config.erro);
        }
    } catch (e) {
        console.error("Erro no router:", e);
        alert("Erro de comunicação ao buscar rota.");
    }
}
function fecharModal() {
    modalAberto.value = false;
    iframeUrl.value = '';
    // Opcional: Recarregar dados do processo para atualizar status
}

function voltar() {
    window.location.href = '/';
}
</script>

<style scoped>
.bpmn-wrapper { 
    height: 100vh; display: flex; flex-direction: column; 
    background: #fff; overflow: hidden; font-family: 'Segoe UI', sans-serif;
}

/* --- TOP BAR --- */
.top-bar {
    height: 50px; background: #fff; border-bottom: 1px solid #e0e0e0;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); z-index: 10;
}
.btn-voltar {
    cursor: pointer; border: 1px solid #d1d5db; background: #f9fafb;
    padding: 6px 12px; border-radius: 6px; font-weight: 600; color: #374151;
    transition: 0.2s; display: flex; align-items: center; gap: 5px;
}
.btn-voltar:hover { background: #e5e7eb; border-color: #9ca3af; }

.center { display: flex; flex-direction: column; align-items: center; line-height: 1.2; }
.titulo { font-weight: 700; font-size: 15px; color: #111827; }
.badge-id { font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 1px 6px; border-radius: 4px; font-weight: 600; }

/* --- CANVAS --- */
.canvas-container { flex: 1; background: #f3f4f6; position: relative; }

/* --- MODAL --- */
.modal-overlay { 
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px);
    display: flex; justify-content: center; align-items: center; 
}
.modal-content { 
    background: white; width: 95%; height: 95%; max-width: 1400px;
    border-radius: 8px; position: relative; display: flex; flex-direction: column; 
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-close { 
    position: absolute; top: -12px; right: -12px; 
    width: 32px; height: 32px; border-radius: 50%;
    background: #ef4444; color: white; border: 2px solid #fff; 
    font-size: 20px; cursor: pointer; z-index: 1001; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #dc2626; transform: scale(1.1); }
.modal-body { flex: 1; overflow: hidden; border-radius: 8px; background: #fff; }
.iframe-legado { width: 100%; height: 100%; border: none; display: block; }
/* src_novo/components/BpmnViewer.vue */

/* ... outros estilos ... */

/* ESTILO PARA FLUXO NOVO (Aplica em tudo: Tarefas, Eventos, Gateways) */
:deep(.tema-novo-fluxo .djs-visual > :first-child) {
    fill: #e8f5e9 !important;   /* Fundo Verde Muito Claro (Mint) */
    stroke: #2e7d32 !important; /* Borda Verde Floresta */
    stroke-width: 2px !important;
}

/* Opcional: Quando passar o mouse, fica mais escuro */
:deep(.tema-novo-fluxo:hover .djs-visual > :first-child) {
    fill: #c8e6c9 !important;
    stroke: #1b5e20 !important;
}
</style>