import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {

  // --- VARIÁVEL GLOBAL SIMPLES ---
  // Aqui guardamos o ID. Se for novo, fica null.
  let currentInstanceId = null;

  // Seletores
  const dashboardContainer = document.getElementById('dashboard-container');
  const viewerContainer = document.getElementById('viewer-container');
  const canvas = document.querySelector('#canvas');
  const listaDefinicoes = document.getElementById('lista-definicoes');
  const tabelaInstancias = document.getElementById('tabela-instancias').querySelector('tbody');
  const btnVoltar = document.getElementById('btn-voltar');
  const tituloFluxoAtual = document.getElementById('titulo-fluxo-atual');
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  const viewer = new BpmnNavigatedViewer({ container: canvas });

  // --- 1. DASHBOARD ---
  async function carregarDashboard() {
    viewerContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');
    currentInstanceId = null; // Reseta o ID sempre que voltar ao início

    try {
      // Carrega Definições (Novo)
      const respDef = await fetch('/backend/api_dashboard.php?acao=definicoes');
      const definicoes = await respDef.json();
      listaDefinicoes.innerHTML = '';
      
      definicoes.forEach(fluxo => {
        const card = document.createElement('div');
        card.className = 'card-fluxo';
        card.innerHTML = `<h3>${fluxo.nome_do_fluxo}</h3><p>Iniciar novo</p>`;
        // Clicou em Novo -> ID é null
        card.onclick = () => abrirDiagrama(fluxo.arquivo_xml, fluxo.nome_do_fluxo, null);
        listaDefinicoes.appendChild(card);
      });

      // Carrega Histórico (Existente)
      const respInst = await fetch('/backend/api_dashboard.php?acao=instancias');
      const instancias = await respInst.json();
      tabelaInstancias.innerHTML = '';
      
      instancias.forEach(inst => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>#${inst.id}</td><td>${inst.nome_do_fluxo}</td><td>${inst.data_formatada}</td><td>${inst.estatus_atual}</td><td><button class="btn-small">Abrir</button></td>`;
        // Clicou no Histórico -> Passa o ID da instância
        tr.querySelector('button').onclick = () => abrirDiagrama(inst.arquivo_xml, `Proc. #${inst.id}`, inst.id);
        tabelaInstancias.appendChild(tr);
      });
    } catch (err) { console.error(err); }
  }

  // --- 2. ABRIR DIAGRAMA ---
  async function abrirDiagrama(xmlFilename, titulo, idInstancia) {
    // 1. Guarda o ID na variável global
    currentInstanceId = idInstancia; 
    console.log("ID Definido como:", currentInstanceId);

    // 2. Mostra a tela
    dashboardContainer.classList.add('hidden');
    viewerContainer.classList.remove('hidden');
    tituloFluxoAtual.textContent = titulo;

    try {
      const response = await fetch('/public/' + xmlFilename);
      const xml = await response.text();
      await viewer.importXML(xml);
      
      setTimeout(() => {
          const canvas = viewer.get('canvas');
          canvas.zoom('fit-viewport');
          const viewbox = canvas.viewbox();
          canvas.zoom(1, { x: viewbox.x + viewbox.width / 2, y: viewbox.y + viewbox.height / 2 });
      }, 100);
    } catch (err) { alert('Erro: ' + err.message); voltarAoDashboard(); }
  }

  function voltarAoDashboard() {
    viewer.clear();
    carregarDashboard();
  }

  // --- 3. CLIQUE NA TAREFA ---
  const eventBus = viewer.get('eventBus');

  eventBus.on('element.click', async (e) => {
    const element = e.element;
    const id = element.id; 
    
    if (!element.type || !element.type.toLowerCase().includes('task')) return;

    try {
      // Pergunta ao router qual arquivo PHP abrir
      const rotaResponse = await fetch(`/backend/router.php?task_id=${id}`);
      if (rotaResponse.status === 404) return;
      const config = await rotaResponse.json();

      if (config.sucesso && config.url) {
        const separator = config.url.includes('?') ? '&' : '?';
        
        // AQUI: Pegamos a variável global e montamos a URL com instance_id
        const idParaEnviar = currentInstanceId ? currentInstanceId : '';
        const finalUrl = `${config.url}${separator}instance_id=${idParaEnviar}`;
        
        openPhpModal(finalUrl);
      }
    } catch (err) { console.error(err); }
  });

  // --- 4. MODAL ---
  async function openPhpModal(url) {
    modalBody.innerHTML = 'Carregando...';
    modalOverlay.classList.remove('hidden');
    try {
      const response = await fetch(url);
      const html = await response.text();
      modalBody.innerHTML = html;
      
      // Executa scripts do PHP
      modalBody.querySelectorAll('script').forEach(oldScript => {
        const newScript = document.createElement('script');
        newScript.textContent = oldScript.textContent;
        document.body.appendChild(newScript);
        document.body.removeChild(newScript);
      });
    } catch (err) { modalBody.innerHTML = 'Erro: ' + err.message; }
  }

  if (btnVoltar) btnVoltar.addEventListener('click', (e) => { e.preventDefault(); voltarAoDashboard(); });
  if (modalClose) modalClose.addEventListener('click', () => { 
      modalOverlay.classList.add('hidden'); 
      modalBody.innerHTML = '';
  });

  // Inicia
  carregarDashboard();
});