// 1. IMPORTAÇÕES
// Usamos NavigatedViewer para apenas visualizar (sem paleta de edição)
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {

  // --- SELETORES DO DOM ---
  // Containers Principais
  const dashboardContainer = document.getElementById('dashboard-container');
  const viewerContainer = document.getElementById('viewer-container');
  const canvas = document.querySelector('#canvas');
  
  // Elementos do Dashboard
  const listaDefinicoes = document.getElementById('lista-definicoes');
  const tabelaInstancias = document.getElementById('tabela-instancias').querySelector('tbody');
  
  // Elementos de Navegação
  const btnVoltar = document.getElementById('btn-voltar');
  const tituloFluxoAtual = document.getElementById('titulo-fluxo-atual');
  
  // Elementos do Modal
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  // --- INICIALIZAÇÃO DO BPMN ---
  const viewer = new BpmnNavigatedViewer({
    container: canvas
  });

  // ============================================================
  //  LÓGICA DO DASHBOARD (TELA INICIAL)
  // ============================================================

  async function carregarDashboard() {
    console.log('Carregando Dashboard...');
    
    // Garante que o Dashboard está visível e o Viewer oculto
    viewerContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');

    try {
      // 1. Busca Definições (Tipos de Fluxo: Compras, Férias, etc.)
      const respDef = await fetch('/backend/api_dashboard.php?acao=definicoes');
      if (!respDef.ok) throw new Error('Erro ao carregar definições');
      const definicoes = await respDef.json();

      listaDefinicoes.innerHTML = '';
      if (definicoes.length === 0) {
        listaDefinicoes.innerHTML = '<p>Nenhum fluxo definido.</p>';
      }

      definicoes.forEach(fluxo => {
        const card = document.createElement('div');
        card.className = 'card-fluxo';
        card.innerHTML = `<h3>${fluxo.nome_do_fluxo}</h3><p>Clique para iniciar</p>`;
        // Ao clicar, abre o diagrama correspondente
        card.onclick = () => abrirDiagrama(fluxo.arquivo_xml, fluxo.nome_do_fluxo);
        listaDefinicoes.appendChild(card);
      });

      // 2. Busca Instâncias (Histórico: O que está rodando)
      const respInst = await fetch('/backend/api_dashboard.php?acao=instancias');
      if (!respInst.ok) throw new Error('Erro ao carregar histórico');
      const instancias = await respInst.json();

      tabelaInstancias.innerHTML = '';
      if (instancias.length === 0) {
        tabelaInstancias.innerHTML = '<tr><td colspan="5">Nenhum processo recente.</td></tr>';
      }

      instancias.forEach(inst => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>#${inst.id}</td>
          <td>${inst.nome_do_fluxo}</td>
          <td>${inst.data_formatada || inst.data_inicio}</td>
          <td>${inst.estatus_atual}</td>
          <td><button class="btn-small">Visualizar</button></td>
        `;
        // Ao clicar, abre o diagrama dessa instância
        tr.querySelector('button').onclick = () => abrirDiagrama(inst.arquivo_xml, `Processo #${inst.id} - ${inst.nome_do_fluxo}`);
        tabelaInstancias.appendChild(tr);
      });

    } catch (err) {
      console.error(err);
      listaDefinicoes.innerHTML = `<p style="color:red">Erro: ${err.message}</p>`;
    }
  }

  // ============================================================
  //  LÓGICA DO VISUALIZADOR (TELA DO DIAGRAMA)
  // ============================================================

  async function abrirDiagrama(xmlFilename, titulo) {
    // Troca de tela
    dashboardContainer.classList.add('hidden');
    viewerContainer.classList.remove('hidden');
    tituloFluxoAtual.textContent = titulo;

    try {
      // Busca o XML na pasta public
      // Nota: xmlFilename deve vir do banco como 'compras.xml'
      const response = await fetch('/public/' + xmlFilename);
      
      if (!response.ok) throw new Error(`Arquivo ${xmlFilename} não encontrado.`);
      
      const xml = await response.text();
      
      // Importa para o visualizador
      await viewer.importXML(xml);
      viewer.get('canvas').zoom(1);;

    } catch (err) {
      console.error('Erro ao abrir diagrama:', err);
      alert('Erro ao carregar o fluxo: ' + err.message);
      voltarAoDashboard();
    }
  }

  function voltarAoDashboard() {
    viewer.clear(); // Limpa a memória do diagrama
    carregarDashboard(); // Recarrega os dados
  }

  // ============================================================
  //  LÓGICA DE INTERAÇÃO (CLIQUES NAS TAREFAS)
  // ============================================================

  const eventBus = viewer.get('eventBus');

  eventBus.on('element.click', async (e) => {
    const element = e.element;
    const id = element.id; 
    const type = element.type; 

    // Ignora cliques em coisas que não são Tarefas (ex: StartEvent, Setas)
    if (!type || !type.toLowerCase().includes('task')) {
      return;
    }

    console.log('Consultando rota para tarefa:', id);

    try {
      // Consulta o Router PHP para saber qual tela abrir
      const rotaResponse = await fetch(`/backend/router.php?task_id=${id}`);
      
      if (rotaResponse.status === 404) {
        console.log('Nenhuma tela configurada para esta tarefa (404).');
        return; 
      }

      const config = await rotaResponse.json();

      if (config.sucesso && config.url) {
        openPhpModal(config.url);
      } else {
        console.warn('Configuração de rota inválida:', config);
      }

    } catch (err) {
      console.error('Erro no router:', err);
      alert('Erro ao comunicar com o servidor.');
    }
  });

  // ============================================================
  //  LÓGICA DO MODAL (JANELAS PHP)
  // ============================================================

  async function openPhpModal(url) {
    modalBody.innerHTML = '<div style="text-align:center; padding:20px;">Carregando...</div>';
    modalOverlay.classList.remove('hidden');

    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);
      
      const html = await response.text();
      modalBody.innerHTML = html;
      
      // IMPORTANTE: Executa scripts <script> que vierem no HTML do PHP
      executeScripts(modalBody);

    } catch (err) {
      modalBody.innerHTML = `<p style="color:red; padding:20px;">Erro ao carregar tela: ${err.message}</p>`;
    }
  }

  // Helper para fazer os scripts dentro do modal funcionarem
  function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
      const newScript = document.createElement('script');
      // Copia atributos (src, type, etc)
      Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
      newScript.textContent = oldScript.textContent;
      
      document.body.appendChild(newScript);
      document.body.removeChild(newScript); // Limpa após executar
    });
  }

  // ============================================================
  //  EVENT LISTENERS GERAIS
  // ============================================================

  if (btnVoltar) {
    btnVoltar.addEventListener('click', (e) => {
      e.preventDefault();
      voltarAoDashboard();
    });
  }

  if (modalClose) {
    modalClose.addEventListener('click', () => {
      modalOverlay.classList.add('hidden');
      modalBody.innerHTML = ''; // Limpa o conteúdo ao fechar
    });
  }

  // --- START ---
  // Inicia a aplicação carregando o Dashboard
  carregarDashboard();

});