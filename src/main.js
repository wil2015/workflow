// Usamos o NavigatedViewer (Visualizador) em vez do Modeler (Editor)
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {

  // --- SELETORES ---
  const canvas = document.querySelector('#canvas');
  
  // Seletores do MODAL
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  // --- INICIALIZAÇÃO ---
  // Verifica se o canvas existe antes de iniciar
  if (!canvas) {
    console.error('Erro: Elemento #canvas não encontrado no HTML.');
    return;
  }

  const viewer = new BpmnNavigatedViewer({
    container: canvas
  });

  // --- FUNÇÕES ---

  // 1. Abre o Modal com conteúdo do PHP
  async function openPhpModal(url) {
    if (!modalBody || !modalOverlay) return;

    modalBody.innerHTML = 'Carregando dados do servidor...';
    modalOverlay.classList.remove('hidden');

    try {
      const response = await fetch(url);
      const html = await response.text();
      modalBody.innerHTML = html;
      executeScripts(modalBody);
    } catch (err) {
      modalBody.innerHTML = '<p style="color:red">Erro ao carregar tela: ' + err.message + '</p>';
    }
  }

  // Helper para rodar scripts do HTML retornado
  function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
      const newScript = document.createElement('script');
      newScript.textContent = oldScript.textContent;
      document.body.appendChild(newScript);
      document.body.removeChild(newScript);
    });
  }

  // 2. Carrega o XML de Compras Automaticamente
  async function loadComprasDiagram() {
    try {
      const response = await fetch('/compras.xml'); 
      
      if (!response.ok) throw new Error('Não foi possível encontrar compras.xml');

      const xml = await response.text();
      
      await viewer.importXML(xml);
      viewer.get('canvas').zoom('fit-viewport');
      
      console.log('Processo carregado!');

    } catch (err) {
      console.error('Erro ao carregar XML', err);
      alert('Erro: ' + err.message);
    }
  }

  // --- EVENT LISTENERS ---

  // Fechar Modal (Verificamos se o botão existe antes de adicionar o evento)
  if (modalClose) {
    modalClose.addEventListener('click', () => {
      modalOverlay.classList.add('hidden');
    });
  }

  // Clique nas Tarefas
  const eventBus = viewer.get('eventBus');

  eventBus.on('element.click', (e) => {
    const element = e.element;
    const id = element.id; 

    console.log('Clicou:', id);

    if (id === 'Activity_SelecionarSolicitacao') {
      openPhpModal('/backend/views/selecionar_solicitacoes.php');
    } 
    else if (id === 'Activity_SelecionarFornecedores') {
      openPhpModal('/backend/views/selecionar_fornecedores.php');
    }
    else if (id === 'Activity_ClassificarValores') {
      openPhpModal('/backend/views/lancar_valores.php');
    }
  });

  // --- START ---
  loadComprasDiagram();

});