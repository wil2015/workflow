import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {

  let currentInstanceId = null;

  // Seletores
  const dashboardContainer = document.getElementById('dashboard-container');
  const viewerContainer = document.getElementById('viewer-container');
  const canvas = document.querySelector('#canvas');
  
  const listaDefinicoes = document.getElementById('lista-definicoes');
  // Nota: Agora selecionamos a TABLE inteira, não só o tbody, para o DataTables funcionar bem
  const tableElement = document.getElementById('tabela-instancias'); 
  
  const btnVoltar = document.getElementById('btn-voltar');
  const tituloFluxoAtual = document.getElementById('titulo-fluxo-atual');
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  const viewer = new BpmnNavigatedViewer({ container: canvas });

  // Roteamento URL
  const urlParams = new URLSearchParams(window.location.search);
  const idUrl = urlParams.get('id');
  const novoXml = urlParams.get('novo');

  if (idUrl) {
    currentInstanceId = idUrl;
    abrirDiagrama('compras.xml', `Processo #${idUrl}`);
  } else if (novoXml) {
    currentInstanceId = null;
    abrirDiagrama(novoXml, 'Novo Processo', null);
  } else {
    currentInstanceId = null;
    carregarDashboard();
  }

  // --- 1. DASHBOARD COM DATATABLES ---
  async function carregarDashboard() {
    viewerContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');
    currentInstanceId = null; 

    try {
      // 1. Definições (Cards)
      const respDef = await fetch('/backend/api_dashboard.php?acao=definicoes');
      const definicoes = await respDef.json();
      listaDefinicoes.innerHTML = '';
      
      definicoes.forEach(fluxo => {
        const card = document.createElement('div');
        card.className = 'card-fluxo';
        card.innerHTML = `<h3>${fluxo.nome_do_fluxo}</h3><p>Iniciar novo</p>`;
        card.onclick = () => { window.location.href = `?novo=${fluxo.arquivo_xml}`; };
        listaDefinicoes.appendChild(card);
      });

      // 2. Histórico (Tabela com DataTables)
      const respInst = await fetch('/backend/api_dashboard.php?acao=instancias');
      const instancias = await respInst.json();
      
      // Se já existe DataTable, destrói para recriar (evita erro de reinit)
      if ($.fn.DataTable.isDataTable('#tabela-instancias')) {
          $('#tabela-instancias').DataTable().destroy();
      }

      const tbody = tableElement.querySelector('tbody');
      tbody.innerHTML = '';
      
      instancias.forEach(inst => {
        const tr = document.createElement('tr');
        
        // Coluna ID Senior com destaque
        const idSenior = inst.id_processo_senior ? `<span style="font-weight:bold; color:#0056b3">#${inst.id_processo_senior}</span>` : '<span style="color:#999">-</span>';
        
        tr.innerHTML = `
            <td>${inst.id}</td>
            <td>${inst.nome_do_fluxo}</td>
            <td>${idSenior}</td> <td data-order="${inst.data_order}">${inst.data_formatada}</td>
            <td>${inst.estatus_atual}</td>
            <td><button class="btn-small">Abrir</button></td>
        `;
        
        tr.querySelector('button').onclick = () => { window.location.href = `?id=${inst.id}`; };
        tbody.appendChild(tr);
      });

      // Inicializa DataTables
      $('#tabela-instancias').DataTable({
          "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
          "pageLength": 10,
          "order": [[ 3, "desc" ]], // Ordena pela Data (Coluna 3 - índice começa em 0)
          "columnDefs": [
              { "width": "50px", "targets": 0 },  // ID Workflow
              { "width": "100px", "targets": 2 }, // ID Senior
              { "width": "80px", "targets": 5 }   // Botão
          ]
      });

    } catch (err) { console.error(err); }
  }

  // --- 2. RESTO DO CÓDIGO (Visualizador, Modal, etc) ---
  // (Mantenha o restante das funções abrirDiagrama, eventBus.on, openPhpModal iguais ao que já funcionava)
  
  async function abrirDiagrama(xmlFilename, titulo) {
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
      }, 100);
    } catch (err) { alert('Erro: ' + err.message); window.location.href = '/'; }
  }

  const eventBus = viewer.get('eventBus');
  eventBus.on('element.click', async (e) => {
    const element = e.element;
    const id = element.id; 
    const type = element.type;

    if (!type || (!type.toLowerCase().includes('task') && !type.toLowerCase().includes('event'))) return;

    try {
      const rotaResponse = await fetch(`/backend/router.php?task_id=${id}`);
      if (rotaResponse.status === 404) return;
      const config = await rotaResponse.json();

      if (config.sucesso && config.url) {
        const separator = config.url.includes('?') ? '&' : '?';
        const idParaEnviar = currentInstanceId ? currentInstanceId : '';
        const finalUrl = `${config.url}${separator}instance_id=${idParaEnviar}`;
        openPhpModal(finalUrl);
      }
    } catch (err) { console.error(err); }
  });

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
  if (modalClose) modalClose.addEventListener('click', () => { 
      modalOverlay.classList.add('hidden'); 
      modalBody.innerHTML = '';
  });

});