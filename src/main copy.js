// --- 1. Importações ---
import BpmnModeler from 'bpmn-js/lib/Modeler';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn.css';
import './style.css';

// VAMOS "EMBRULHAR" TUDO AQUI DENTRO
document.addEventListener('DOMContentLoaded', () => {

  // --- 2. Seletores do HTML ---
  // Agora temos certeza que o HTML existe
  const canvas = document.querySelector('#canvas');
  const createDiagramLink = document.querySelector('#js-create-diagram');
  const downloadLink = document.querySelector('#js-download-diagram');
  const downloadSvgLink = document.querySelector('#js-download-svg');
  const uploadLink = document.querySelector('#js-upload-diagram');
  const fileInput = document.querySelector('#file-input');

  // --- 3. Inicialização do Editor ---
  const modeler = new BpmnModeler({
    container: canvas
  });

  // --- 4. Funções Principais ---

  /**
   * Abre um diagrama (XML) no editor (usado pelo Upload).
   */
  async function openDiagram(xml) {
    try {
      await modeler.importXML(xml);
      modeler.get('canvas').zoom('fit-viewport');
    } catch (err) {
      console.error('Erro ao importar XML', err);
    }
  }

  /**
   * Cria um novo diagrama em branco (usando createDiagram).
   */
  async function createNewDiagram() {
    try {
      await modeler.createDiagram(); 
      modeler.get('canvas').zoom('fit-viewport'); 
      console.log('Novo diagrama criado!');
    } catch (err) {
      console.error('Erro ao criar diagrama', err);
    }
  }

  /**
   * Atualiza o link de download (<a>) com os dados.
   */
  function setEncoded(link, name, data) {
    // Adiciona uma verificação para garantir que o link existe
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

  /**
   * Gera o XML e o SVG e atualiza os links de download.
   */
  const exportArtifacts = debounce(async function() {
    console.log('Atualizando links de download...');
    
    try {
      const { svg } = await modeler.saveSVG();
      setEncoded(downloadSvgLink, 'diagrama.svg', svg);
    } catch (err) {
      console.error('Erro ao salvar SVG', err);
    }

    try {
      const { xml } = await modeler.saveXML({ format: true });
      setEncoded(downloadLink, 'diagrama.bpmn', xml);
    } catch (err) {
      console.error('Erro ao salvar XML', err);
    }
  }, 500);


  // --- 5. "Ouvintes" de Eventos ---

  // Ouve o clique no botão "Novo Diagrama"
  createDiagramLink.addEventListener('click', (e) => {
    e.preventDefault();
    createNewDiagram();
  });

  // Ouve o clique no botão "Upload"
  uploadLink.addEventListener('click', (e) => {
    e.preventDefault();
    fileInput.click(); 
  });

  // Ouve quando o usuário seleciona um arquivo no input
  fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];

    if (file) {
      const reader = new FileReader();
      reader.onload = (event) => {
        const xml = event.target.result;
        openDiagram(xml); 
      };
      reader.readAsText(file);
      fileInput.value = null;
    }
  });

  // Ouve qualquer mudança no diagrama
  modeler.on('commandStack.changed', exportArtifacts);

  // Impede o clique em links de download vazios
  document.querySelectorAll('.buttons a[download]').forEach(link => {
    link.addEventListener('click', (e) => {
      if (!link.classList.contains('active')) {
        e.preventDefault();
      }
    });
  });

  // --- 6. Função Helper (Debounce) ---
  function debounce(fn, timeout) {
    let timer;
    return function(...args) {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(() => {
        fn.apply(this, args);
      }, timeout);
    };
  }

  // --- 7. Início ---
  // Carrega o diagrama em branco ao abrir a página
  createNewDiagram();

}); // <-- FECHA O "EMBRULHO" DO DOMContentLoaded