import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {
  let currentInstanceId = null;
  let currentXmlFilename = ''; 
  let currentFluxoId = null; // Variável global do ID

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

  // 1. LÓGICA DE INICIALIZAÇÃO (CORRIGIDA)
  const urlParams = new URLSearchParams(window.location.search);
  const idUrl = urlParams.get('id');
  const novoXml = urlParams.get('novo');
  const novoId = urlParams.get('fluxo_id'); // <--- Lê o parâmetro correto

  console.log('Start Params:', { idUrl, novoXml, novoId }); // Debug

  if (idUrl) {
    // Processo Existente
    carregarInstancia(idUrl);
  } else if (novoXml && novoId) {
    // Novo Processo
    currentInstanceId = null;
    currentXmlFilename = novoXml;
    currentFluxoId = novoId;
    abrirDiagrama(novoXml, 'Novo Processo');
  } else {
    // Dashboard
    currentInstanceId = null;
    carregarDashboard();
  }

  // 2. CARREGAR INSTÂNCIA EXISTENTE
  async function carregarInstancia(id) {
      try {
          const resp = await fetch('/backend/api_dashboard.php?acao=instancias'); 
          const dados = await resp.json();
          const proc = dados.find(p => p.id == id);
          
          if (proc) {
              currentInstanceId = id;
              currentXmlFilename = proc.arquivo_xml;
              currentFluxoId = proc.fluxo_id; // Pega o valor padronizado da API
              
              if (currentXmlFilename && currentFluxoId) {
                  abrirDiagrama(currentXmlFilename, `Proc. #${id} - ${proc.nome_do_fluxo}`);
              } else {
                  alert('Dados inconsistentes no banco para este processo.');
                  window.location.href = '/';
              }
          } else {
              alert('Processo não encontrado'); 
              window.location.href = '/';
          }
      } catch (e) { 
          console.error(e); 
          alert('Erro ao carregar instância.');
      }
  }

  // 3. ABRIR DIAGRAMA
  async function abrirDiagrama(xmlFilename, titulo) {
    dashboardContainer.classList.add('hidden');
    viewerContainer.classList.remove('hidden');
    tituloFluxoAtual.textContent = titulo;

    try {
      const response = await fetch('/public/' + xmlFilename);
      if (!response.ok) throw new Error('XML não encontrado: ' + xmlFilename);
      const xml = await response.text();
      await viewer.importXML(xml);
      setTimeout(() => viewer.get('canvas').zoom('fit-viewport'), 100);
    } catch (err) { alert('Erro XML: ' + err.message); }
  }

  // 4. ROUTER (CLIQUE)
  const eventBus = viewer.get('eventBus');
  eventBus.on('element.click', async (e) => {
    const element = e.element;
    const idTask = element.id; 
    const type = element.type;

    if (!type || (!type.toLowerCase().includes('task') && !type.toLowerCase().includes('event'))) return;

    if (!currentFluxoId) {
        alert("Erro: ID do fluxo perdido. Atualize a página.");
        return;
    }

    try {
      // Envia fluxo_id corretamente para o PHP
      const urlFetch = `/backend/router.php?task_id=${idTask}&fluxo_id=${currentFluxoId}`;
      console.log('Router Fetch:', urlFetch);

      const rotaResponse = await fetch(urlFetch);
      if (rotaResponse.status === 404) {
          console.warn('Rota não encontrada (404)');
          return;
      }
      
      const config = await rotaResponse.json();

      if (config.sucesso && config.url) {
        const separator = config.url.includes('?') ? '&' : '?';
        const idParaEnviar = currentInstanceId ? currentInstanceId : '';
       // CORREÇÃO: Adicionamos &fluxo_id=... na URL do modal
        const finalUrl = `${config.url}${separator}instance_id=${idParaEnviar}&fluxo_id=${currentFluxoId}`;
        openPhpModal(finalUrl);
      } else {
          if (config.erro) alert(config.erro);
      }
    } catch (err) { console.error('Erro Router:', err); }
  });

  // 5. CARREGAR DASHBOARD
  async function carregarDashboard() {
    viewerContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');
    currentXmlFilename = '';
    currentFluxoId = null;

    try {
      // Botões "Iniciar Novo"
      const respDef = await fetch('/backend/api_dashboard.php?acao=definicoes');
      const definicoes = await respDef.json();
      listaDefinicoes.innerHTML = '';
      
      definicoes.forEach(fluxo => {
        const card = document.createElement('div');
        card.className = 'card-fluxo';
        card.innerHTML = `<h3>${fluxo.nome_do_fluxo}</h3><p>+ Iniciar Novo</p>`;
        // CORREÇÃO: Gera a URL com 'fluxo_id' para ser lido no passo 1
        card.onclick = () => { 
            window.location.href = `?novo=${fluxo.arquivo_xml}&fluxo_id=${fluxo.fluxo_id}`; 
        };
        listaDefinicoes.appendChild(card);
      });

      // Tabela de Processos
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

  // Modal Utils
  async function openPhpModal(url) {
    modalBody.innerHTML = '<div style="text-align:center;padding:20px">Carregando...</div>';
    modalOverlay.classList.remove('hidden');
    try {
      const response = await fetch(url);
      const html = await response.text();
      modalBody.innerHTML = html;
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