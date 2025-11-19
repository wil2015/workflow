// import BpmnModeler from 'bpmn-js/lib/Modeler';
import BpmnNavigatedViewer from 'bpmn-js/lib/NavigatedViewer';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {

  // --- SELETORES ---
  const canvas = document.querySelector('#canvas');
  const createDiagramLink = document.querySelector('#js-create-diagram');
  const downloadLink = document.querySelector('#js-download-diagram');
  const downloadSvgLink = document.querySelector('#js-download-svg');
  const uploadLink = document.querySelector('#js-upload-diagram');
  const saveToServerLink = document.querySelector('#js-save-to-server');
  const fileInput = document.querySelector('#file-input');
  
  // Seletores do MODAL
  const modalOverlay = document.getElementById('modal-overlay');
  const modalBody = document.getElementById('modal-body');
  const modalClose = document.getElementById('modal-close');

  // --- INICIALIZAÇÃO ---
  //const modeler = new BpmnModeler({
  //  container: canvas
  //});

  const modeler = new BpmnNavigatedViewer({
    container: canvas
  });

  // --- FUNÇÕES ---

  // 1. Abre o Modal com conteúdo do PHP
  async function openPhpModal(url) {
    modalBody.innerHTML = 'Carregando dados do servidor...';
    modalOverlay.classList.remove('hidden'); // Mostra o modal

    try {
      // O Vite vai redirecionar isso para o Apache graças ao proxy
      const response = await fetch(url);
      const html = await response.text();
      modalBody.innerHTML = html;
      
      // Se houver scripts dentro do HTML retornado pelo PHP, 
      // precisamos executá-los manualmente (segurança do navegador)
      executeScripts(modalBody);

    } catch (err) {
      modalBody.innerHTML = '<p style="color:red">Erro ao carregar tela: ' + err.message + '</p>';
    }
  }

  // Helper para rodar scripts que vêm no HTML do PHP
  function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
      const newScript = document.createElement('script');
      newScript.textContent = oldScript.textContent;
      document.body.appendChild(newScript);
      document.body.removeChild(newScript); // Limpa após executar
    });
  }

  // 2. Carrega o XML de Compras (e fecha o loading inicial)
  async function loadComprasDiagram() {
    try {
      // Busca o arquivo na pasta /public
      const response = await fetch('/compras.xml'); 
      
      if (!response.ok) throw new Error('Não foi possível encontrar compras.xml');

      const xml = await response.text();
      
      await modeler.importXML(xml);
      modeler.get('canvas').zoom('fit-viewport');
      
      // SUCESSO: Esconde o modal de "Carregando..."
      modalOverlay.classList.add('hidden'); 
      console.log('Processo de Compras carregado!');

    } catch (err) {
      console.error('Erro ao carregar compras.xml', err);
      modalBody.innerHTML = `<p style="color:red">Erro fatal: ${err.message}</p>`;
      // Mantém o modal aberto mostrando o erro
    }
  }

  // 3. Funções de Download/Upload (Padrão)
  function setEncoded(link, name, data) {
    if (!link) return;
    const encodedData = encodeURIComponent(data);
    if (data) {
      link.classList.add('active');
      link.setAttribute('href', 'data:application/bpmn20-xml;charset=UTF-8,' + encodedData);
      link.setAttribute('download', name);
    } else {
      link.classList.remove('active');
    }
  }

  const exportArtifacts = debounce(async function() {
    try {
      const { svg } = await modeler.saveSVG();
      setEncoded(downloadSvgLink, 'diagrama.svg', svg);
    } catch (err) { console.error(err); }

    try {
      const { xml } = await modeler.saveXML({ format: true });
      setEncoded(downloadLink, 'diagrama.bpmn', xml);
    } catch (err) { console.error(err); }
  }, 500);

  // --- EVENT LISTENERS ---

  // Fechar Modal
  modalClose.addEventListener('click', () => {
    modalOverlay.classList.add('hidden');
  });

  // Clique nas Tarefas do Diagrama
  modeler.on('element.click', (e) => {
    const element = e.element;
    const id = element.id; 

    console.log('Clicou:', id);

    // Mapeamento ID -> Arquivo PHP
    if (id === 'Activity_SelecionarSolicitacao') {
      openPhpModal('/backend/views/selecionar_solicitacoes.php');
    } 
    else if (id === 'Activity_SelecionarFornecedores') {
      openPhpModal('/backend/views/selecionar_fornecedores.php');
    }
    // Adicione outros conforme necessário
  });

  // Botões da Barra
  createDiagramLink.addEventListener('click', (e) => {
    e.preventDefault();
    modeler.createDiagram();
  });

  uploadLink.addEventListener('click', (e) => {
    e.preventDefault();
    fileInput.click();
  });

  fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (evt) => modeler.importXML(evt.target.result);
      reader.readAsText(file);
    }
  });

  modeler.on('commandStack.changed', exportArtifacts);

  // Salvar no Servidor
  if (saveToServerLink) {
    saveToServerLink.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        const { xml } = await modeler.saveXML({ format: true });
        const response = await fetch('/backend/salvar_fluxo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/xml' },
          body: xml
        });
        const txt = await response.text();
        alert(txt);
      } catch (err) {
        alert('Erro ao salvar: ' + err.message);
      }
    });
  }

  // --- HELPER ---
  function debounce(fn, timeout) {
    let timer;
    return function(...args) {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), timeout);
    };
  }

  // --- START ---
  // Tenta carregar o fluxo de compras ao iniciar
  loadComprasDiagram();

});