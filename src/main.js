import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {
  // --- VARIÁVEIS GLOBAIS DE CONTEXTO ---
  let currentInstanceId = null;
  let currentXmlFilename = ''; 

  // Elementos do DOM
  const dashboardContainer = document.getElementById('dashboard-container');
  const viewerContainer = document.getElementById('viewer-container');
  const canvas = document.querySelector('#canvas');
  const listaDefinicoes = document.getElementById('lista-definicoes');
  const tableInstancias = document.getElementById('tabela-instancias');
  const btnVoltar = document.getElementById('btn-voltar');
  const tituloFluxoAtual = document.getElementById('titulo-fluxo-atual');
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  const viewer = new BpmnNavigatedViewer({ container: canvas });

  // 1. ROTEAMENTO INICIAL (Check de URL)
  const urlParams = new URLSearchParams(window.location.search);
  const idUrl = urlParams.get('id');
  const novoXml = urlParams.get('novo');

  if (idUrl) {
    // MODO EDIÇÃO: Carrega processo existente
    carregarInstancia(idUrl);
  } else if (novoXml) {
    // MODO NOVO: XML vem na URL
    currentInstanceId = null;
    currentXmlFilename = novoXml; // Define contexto imediatamente
    console.log('Novo Processo. XML:', currentXmlFilename);
    abrirDiagrama(novoXml, 'Novo Processo');
  } else {
    // MODO DASHBOARD
    currentInstanceId = null;
    carregarDashboard();
  }

  // 2. FUNÇÃO PARA CARREGAR PROCESSO EXISTENTE
  async function carregarInstancia(id) {
      try {
          const resp = await fetch('/backend/api_dashboard.php?acao=instancias'); 
          const dados = await resp.json();
          
          // Busca o processo na lista retornada pela API
          const proc = dados.find(p => p.id == id);
          
          if (proc) {
              currentInstanceId = id;
              
              if (proc.arquivo_xml) {
                  currentXmlFilename = proc.arquivo_xml; // <--- AQUI ESTÁ A CORREÇÃO
                  console.log('Processo Carregado. Contexto XML:', currentXmlFilename);
                  abrirDiagrama(proc.arquivo_xml, `Proc. #${id} - ${proc.nome_do_fluxo}`);
              } else {
                  alert('ERRO DE DADOS: Este processo não tem arquivo XML vinculado no banco.');
                  window.location.href = '/';
              }
          } else {
              alert('Processo não encontrado.');
              window.location.href = '/';
          }
      } catch (e) { 
          console.error(e); 
          alert('Erro de conexão ao carregar processo.'); 
      }
  }

  // 3. ABRIR DIAGRAMA VISUAL
  async function abrirDiagrama(xmlFilename, titulo) {
    dashboardContainer.classList.add('hidden');
    viewerContainer.classList.remove('hidden');
    tituloFluxoAtual.textContent = titulo;

    try {
      const response = await fetch('/public/' + xmlFilename);
      if (!response.ok) throw new Error(`Arquivo ${xmlFilename} não encontrado.`);
      
      const xml = await response.text();
      await viewer.importXML(xml);
      
      setTimeout(() => {
          const cv = viewer.get('canvas');
          cv.zoom('fit-viewport');
      }, 100);
    } catch (err) { 
        alert('Erro ao abrir XML: ' + err.message); 
    }
  }

  // 4. INTERAÇÃO DE CLIQUE (ROUTER)
  const eventBus = viewer.get('eventBus');
  eventBus.on('element.click', async (e) => {
    const element = e.element;
    const idTask = element.id; 
    const type = element.type;

    // Só reage a Tarefas e Eventos
    if (!type || (!type.toLowerCase().includes('task') && !type.toLowerCase().includes('event'))) return;

    // TRAVA DE SEGURANÇA
    if (!currentXmlFilename) {
        alert("Erro Crítico: O sistema não sabe qual XML está aberto. Dê F5.");
        return;
    }

    try {
      // Chama o Router enviando ID DA TAREFA + NOME DO XML
      const urlFetch = `/backend/router.php?task_id=${idTask}&process_key=${currentXmlFilename}`;
      console.log('Chamando rota:', urlFetch);

      const rotaResponse = await fetch(urlFetch);
      
      if (rotaResponse.status === 404) {
          console.warn(`Nenhuma rota configurada no banco para a tarefa "${idTask}" no fluxo "${currentXmlFilename}"`);
          return; 
      }
      
      const config = await rotaResponse.json();

      if (config.sucesso && config.url) {
        const separator = config.url.includes('?') ? '&' : '?';
        const idParaEnviar = currentInstanceId ? currentInstanceId : '';
        const finalUrl = `${config.url}${separator}instance_id=${idParaEnviar}`;
        openPhpModal(finalUrl);
      } else {
          if (config.erro) alert(config.erro);
      }
    } catch (err) { console.error('Erro no clique:', err); }
  });

  // 5. CARREGAR DASHBOARD
  async function carregarDashboard() {
    viewerContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');
    currentXmlFilename = '';

    try {
      // Botões de Novo Processo
      const respDef = await fetch('/backend/api_dashboard.php?acao=definicoes');
      const definicoes = await respDef.json();
      listaDefinicoes.innerHTML = '';
      
      definicoes.forEach(fluxo => {
        const card = document.createElement('div');
        card.className = 'card-fluxo';
        card.innerHTML = `<h3>${fluxo.nome_do_fluxo}</h3><p>+ Iniciar Novo</p>`;
        card.onclick = () => { window.location.href = `?novo=${fluxo.arquivo_xml}`; };
        listaDefinicoes.appendChild(card);
      });

      // Tabela de Instâncias
      const respInst = await fetch('/backend/api_dashboard.php?acao=instancias');
      const instancias = await respInst.json();
      
      if ($.fn.DataTable.isDataTable('#tabela-instancias')) { $('#tabela-instancias').DataTable().destroy(); }

      const tbody = tableInstancias.querySelector('tbody');
      tbody.innerHTML = '';
      
      instancias.forEach(inst => {
        const tr = document.createElement('tr');
        const idSenior = inst.id_processo_senior ? `<strong style="color:#0056b3">#${inst.id_processo_senior}</strong>` : '-';
        tr.innerHTML = `
            <td>${inst.id}</td>
            <td>${inst.nome_do_fluxo}</td>
            <td>${idSenior}</td>
            <td data-order="${inst.data_order}">${inst.data_formatada}</td>
            <td>${inst.estatus_atual}</td>
            <td><button class="btn-small">Abrir</button></td>
        `;
        tr.querySelector('button').onclick = () => { window.location.href = `?id=${inst.id}`; };
        tbody.appendChild(tr);
      });

      $('#tabela-instancias').DataTable({
          "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
          "pageLength": 10,
          "order": [[ 0, "desc" ]]
      });
    } catch (err) { console.error(err); }
  }

  // Modal e Utils
  async function openPhpModal(url) {
    modalBody.innerHTML = '<div style="text-align:center;padding:20px">Carregando...</div>';
    modalOverlay.classList.remove('hidden');
    try {
      const response = await fetch(url);
      const html = await response.text();
      modalBody.innerHTML = html;
      // Re-executa scripts dentro do HTML carregado
      modalBody.querySelectorAll('script').forEach(oldScript => {
        const newScript = document.createElement('script');
        newScript.textContent = oldScript.textContent;
        document.body.appendChild(newScript);
        document.body.removeChild(newScript);
      });
    } catch (err) { modalBody.innerHTML = 'Erro: ' + err.message; }
  }

  if (btnVoltar) btnVoltar.addEventListener('click', (e) => { e.preventDefault(); window.location.href = '/'; });
  if (modalClose) modalClose.addEventListener('click', () => { modalOverlay.classList.add('hidden'); modalBody.innerHTML = ''; });
});