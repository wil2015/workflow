<template>
  <div class="page-container">
    <div class="header-bar">
      <div class="title-area">
        <h2>Autorização de Compra</h2>
        <span class="subtitle">Documentos Oficiais do Processo #{{ instanceId }}</span>
      </div>
      <div class="actions">
        <button class="btn-back" @click="voltar">Voltar ao Fluxo</button>
      </div>
    </div>

    <div class="content">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>{{ statusMsg }}</p>
      </div>

      <!-- Tela de edição completa: o HTML inteiro do Twig entra no Tiptap. -->
      <div v-else-if="modoEdicao" class="editor-area">
        <div class="editor-topbar">
          <div>
            <strong>Revise a autorização antes de emitir.</strong>
            <div class="editor-hint">
              O documento veio do banco/Twig. Edite o texto necessário e depois emita o PDF.
            </div>
          </div>

          <div class="editor-topbar-actions">
            <button @click="cancelarEdicao" class="btn-back" :disabled="sending">
              Cancelar
            </button>

            <button @click="salvarRascunho" class="btn-secondary" :disabled="sending">
              💾 Salvar rascunho
            </button>

            <button @click="emitirPdfEditado" class="btn-primary" :disabled="sending">
              {{ sending ? 'Emitindo PDF...' : 'Emitir PDF' }}
            </button>
          </div>
        </div>

        <div class="editor-body">
          <div v-if="documentosEdicao.length > 1" class="field-row">
            <label>Autorização:</label>
            <select v-model.number="indiceDocumentoAtual" @change="trocarDocumentoEdicao">
              <option
                v-for="(doc, index) in documentosEdicao"
                :key="doc.id_autorizacao"
                :value="index"
              >
                {{ doc.titulo }}
              </option>
            </select>
          </div>

          <div class="editor-shell">
            <div class="tiptap-menubar" v-if="editor">
              <button type="button" :class="{ active: editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()">B</button>
              <button type="button" :class="{ active: editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
              <button type="button" :class="{ active: editor.isActive('underline') }" @click="editor.chain().focus().toggleUnderline().run()"><u>U</u></button>

              <span class="sep"></span>

              <button type="button" :class="{ active: editor.isActive('paragraph') }" @click="editor.chain().focus().setParagraph().run()">Normal</button>
              <button type="button" :class="{ active: editor.isActive('heading', { level: 1 }) }" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</button>
              <button type="button" :class="{ active: editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
              <button type="button" :class="{ active: editor.isActive('heading', { level: 3 }) }" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>

              <span class="sep"></span>

              <button type="button" :class="{ active: editor.isActive({ textAlign: 'left' }) }" @click="editor.chain().focus().setTextAlign('left').run()">⯇</button>
              <button type="button" :class="{ active: editor.isActive({ textAlign: 'center' }) }" @click="editor.chain().focus().setTextAlign('center').run()">☰</button>
              <button type="button" :class="{ active: editor.isActive({ textAlign: 'right' }) }" @click="editor.chain().focus().setTextAlign('right').run()">⯈</button>
              <button type="button" :class="{ active: editor.isActive({ textAlign: 'justify' }) }" @click="editor.chain().focus().setTextAlign('justify').run()">☷</button>

              <span class="sep"></span>

              <button type="button" :class="{ active: editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()">• Lista</button>
              <button type="button" :class="{ active: editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()">1. Lista</button>
              <button type="button" :class="{ active: editor.isActive('blockquote') }" @click="editor.chain().focus().toggleBlockquote().run()">Aspas</button>
              <button type="button" :class="{ active: editor.isActive('link') }" @click="definirLink">Link</button>

              <span class="sep"></span>

              <button type="button" @click="editor.chain().focus().undo().run()">↶</button>
              <button type="button" @click="editor.chain().focus().redo().run()">↷</button>

              <span class="sep"></span>

              <button type="button" @click="inserirTabela">Inserir tabela</button>
              <button type="button" @click="editor.chain().focus().addColumnAfter().run()">+ Col.</button>
              <button type="button" @click="editor.chain().focus().deleteColumn().run()">- Col.</button>
              <button type="button" @click="editor.chain().focus().addRowAfter().run()">+ Linha</button>
              <button type="button" @click="editor.chain().focus().deleteRow().run()">- Linha</button>
              <button type="button" @click="editor.chain().focus().deleteTable().run()">Apagar tabela</button>
              <button type="button" @click="editor.chain().focus().fixTables().run()">Corrigir tabela</button>
            </div>

            <div class="editor-scroll">
              <div class="editor-page">
                <editor-content :editor="editor" class="tiptap-editor-content" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="autorizacoes.length > 0" class="docs-list">
        <div v-if="temArquivoQuebrado" class="alert-warning">
          ⚠️ <strong>Atenção:</strong> Alguns PDFs atuais não foram encontrados fisicamente.
        </div>
        <div v-else class="alert-success">
          ✅ <strong>Sucesso!</strong> Dados disponíveis para revisão.
        </div>

        <section class="auth-section">
          <div class="section-title-row">
            <div>
              <h3>Autorizações do processo</h3>
              <small>Use esta lista para revisar a autorização correta. Ela vem da tabela atual de autorizações, não do nome do PDF.</small>
            </div>
          </div>

          <table v-if="autorizacoes.length > 0" class="table-docs">
            <thead>
              <tr>
                <th>Autorização</th>
                <th>Fornecedor</th>
                <th>Valor</th>
                <th>PDF atual</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="auth in autorizacoes" :key="auth.id_autorizacao">
                <td>#{{ auth.id_autorizacao }}</td>
                <td>
                  <strong>{{ auth.fornecedor_nome }}</strong>
                  <div v-if="auth.fornecedor_cnpj" class="muted-small">{{ auth.fornecedor_cnpj }}</div>
                </td>
                <td>{{ formatMoeda(auth.valor_total_pedido) }}</td>
                <td>
                  <a
                    v-if="auth.documento && auth.documento.existe_fisicamente"
                    :href="montarLink(auth.documento.caminho_arquivo)"
                    target="_blank"
                    class="btn-view"
                  >
                    👁️ Visualizar PDF
                  </a>
                  <span v-else class="badge-muted">Sem PDF emitido</span>
                </td>
                <td>
                  <button
                    type="button"
                    class="btn-edit-doc"
                    :disabled="sending"
                    @click="prepararEdicao(auth)"
                  >
                    ✏️ Revisar / Emitir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-else class="regenerate-area highlight-warning">
          <div class="regenerate-info">
            <p>Nenhuma autorização atual encontrada no snapshot.</p>
              <small>Prepare as autorizações novamente para revisar e emitir.</small>
          </div>
          </div>
        </section>

        <div class="actions-footer">
          <button @click="enviarEConcluir" class="btn-finish" :disabled="sending || temArquivoQuebrado">
            {{ sending ? 'Enviando...' : 'Enviar E-mails e Concluir' }}
          </button>
        </div>
      </div>

      <div v-else-if="podeGerar" class="empty-state">
        <div class="icon-big">📝</div>
        <h3>Nenhum documento emitido</h3>
        <p>A grade de preços está pronta. Clique abaixo para revisar o documento antes de gerar o PDF.</p>
        <button @click="prepararEdicao" class="btn-primary">Preparar Autorizações</button>
      </div>

      <div v-else class="error-state">
        <div class="icon-error">🚫</div>
        <h3>Não existe cotação de fornecedores</h3>
        <p>Não é possível gerar documentos pois este processo não possui itens vencedores na Grade de Preços.</p>
        <p class="error-hint">Verifique se a etapa de Cotação foi realizada e consolidada no fluxo anterior.</p>

        <button @click="voltar" class="btn-back-highlight">Voltar para verificar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import { Node, mergeAttributes } from '@tiptap/core';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { TableKit } from '@tiptap/extension-table';

const BASE_APACHE = 'http://localhost:8081/';
const API_CONTROLLER = '/backend/modulos/AutorizacaoCompra/AutorizacaoCompraController.php';

const ImagemDocumento = Node.create({
  name: 'imagemDocumento',
  inline: true,
  group: 'inline',
  atom: true,

  addAttributes() {
    return {
      src: { default: null },
      alt: { default: null },
      title: { default: null },
      class: { default: null },
    };
  },

  parseHTML() {
    return [{ tag: 'img[src]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['img', mergeAttributes(HTMLAttributes)];
  },
});

const instanceId = ref(null);
const autorizacoes = ref([]);
const documentosEdicao = ref([]);
const documentoAtual = ref(null);
const indiceDocumentoAtual = ref(0);
const htmlDocumento = ref('');

const podeGerar = ref(false);
const loading = ref(true);
const sending = ref(false);
const modoEdicao = ref(false);
const statusMsg = ref('Carregando...');

const editor = useEditor({
  content: '',
  extensions: [
    StarterKit,
    ImagemDocumento,
    Underline,
    Link.configure({
      openOnClick: false,
      autolink: true,
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    TableKit.configure({
      table: {
        resizable: true,
      },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'prosemirror-documento',
    },
  },
  onUpdate: ({ editor }) => {
    htmlDocumento.value = editor.getHTML();
    if (documentoAtual.value) {
      documentoAtual.value.html = htmlDocumento.value;
    }
  },
});

const temArquivoQuebrado = computed(() => {
  return autorizacoes.value.some(auth => auth.documento && auth.documento.existe_fisicamente === false);
});

onMounted(async () => {
  const params = new URLSearchParams(window.location.search);
  instanceId.value = params.get('instance_id');
  if (instanceId.value) {
    await carregarLista();
  } else {
    loading.value = false;
    alert('Erro: ID não encontrado.');
  }
});

async function carregarLista() {
  loading.value = true;
  try {
    const url = `${API_CONTROLLER}?acao=listar_documentos&instance_id=${instanceId.value}`;
    const res = await fetch(url);
    const json = await res.json();

    if (json.erro) throw new Error(json.erro);

    if (Array.isArray(json)) {
      autorizacoes.value = [];
      podeGerar.value = false;
    } else {
      autorizacoes.value = json.autorizacoes || [];
      podeGerar.value = json.tem_cotacao ?? autorizacoes.value.length > 0;
    }
  } catch (e) {
    console.error(e);
    alert('Erro ao carregar documentos: ' + e.message);
  } finally {
    loading.value = false;
  }
}

function extrairIdAutorizacao(doc) {
  if (!doc) return '';

  if (doc.id_autorizacao) {
    return String(doc.id_autorizacao);
  }

  if (doc.id) {
    return String(doc.id);
  }

  const fonte = `${doc.nome_arquivo || ''} ${doc.caminho_arquivo || ''}`;
  const match = fonte.match(/auth_([A-Za-z0-9_]+)/i);

  return match ? match[1] : '';
}

async function prepararEdicao(docLista = null) {
  if (!podeGerar.value) return;

  const idAutorizacaoAlvo = extrairIdAutorizacao(docLista);

  loading.value = true;
  statusMsg.value = idAutorizacaoAlvo
    ? `Preparando autorização #${idAutorizacaoAlvo} para revisão...`
    : 'Preparando documento para revisão...';

  try {
    const form = new FormData();
    form.append('instance_id', instanceId.value);
    form.append('id_usuario', 1);

    if (idAutorizacaoAlvo) {
      form.append('id_autorizacao', idAutorizacaoAlvo);
    }

    if (docLista?.id_fornecedor_senior) {
      form.append('id_fornecedor_senior', docLista.id_fornecedor_senior);
    }

    const res = await fetch(`${API_CONTROLLER}?acao=preparar_autorizacoes`, {
      method: 'POST',
      body: form
    });
    const json = await res.json();

    if (json.erro) throw new Error(json.erro);

    documentosEdicao.value = json.documentos || [];
    if (!documentosEdicao.value.length) {
      throw new Error('Nenhum documento retornado para revisão.');
    }

    let indiceInicial = 0;
    if (idAutorizacaoAlvo) {
      const encontrado = documentosEdicao.value.findIndex(doc => String(doc.id_autorizacao) === idAutorizacaoAlvo);
      if (encontrado === -1) {
        throw new Error(`Autorização #${idAutorizacaoAlvo} não foi retornada pelo backend.`);
      }
      indiceInicial = encontrado;
    }

    indiceDocumentoAtual.value = indiceInicial;
    modoEdicao.value = true;
    await nextTick();
    abrirDocumentoEdicao(documentosEdicao.value[indiceInicial]);
  } catch (e) {
    alert('Erro: ' + e.message);
  } finally {
    loading.value = false;
  }
}

function chaveRascunho(doc = documentoAtual.value) {
  const idAutorizacao = doc?.id_autorizacao || 'sem_autorizacao';
  return `autorizacao_compra_tiptap_${instanceId.value}_${idAutorizacao}`;
}

function abrirDocumentoEdicao(doc) {
  documentoAtual.value = doc;

  const rascunhoSalvo = localStorage.getItem(chaveRascunho(doc));
  const htmlInicial = garantirLogoCabecalho(rascunhoSalvo || doc.html || '');

  htmlDocumento.value = htmlInicial;
  editor.value?.commands.setContent(htmlInicial, false);
  editor.value?.commands.fixTables();
}

function obterHtmlEditor() {
  const html = editor.value?.getHTML() || htmlDocumento.value || '';
  htmlDocumento.value = html;
  if (documentoAtual.value) {
    documentoAtual.value.html = html;
  }
  return html;
}

function garantirLogoCabecalho(html) {
  const conteudo = html || '';
  if (conteudo.includes('logo-fundunesp')) return conteudo;

  const logo = '<p class="cabecalho"><img class="logo-fundunesp" alt="Fundunesp" src="/logo.png"></p>';
  const matchTitulo = conteudo.match(/<h[1-3][^>]*(class="[^"]*titulo[^"]*"|class='[^']*titulo[^']*')[^>]*>/i);

  if (!matchTitulo) return `${logo}${conteudo}`;

  return conteudo.replace(matchTitulo[0], `${logo}${matchTitulo[0]}`);
}

function salvarRascunho() {
  if (!documentoAtual.value) return;

  const html = obterHtmlEditor();
  localStorage.setItem(chaveRascunho(), html);
  alert('Rascunho salvo neste navegador.');
}

function trocarDocumentoEdicao() {
  if (documentoAtual.value) {
    obterHtmlEditor();
  }
  abrirDocumentoEdicao(documentosEdicao.value[indiceDocumentoAtual.value]);
}

function cancelarEdicao() {
  if (!confirm('Cancelar revisão? As alterações não emitidas serão perdidas.')) return;
  modoEdicao.value = false;
  documentoAtual.value = null;
  htmlDocumento.value = '';
  editor.value?.commands.clearContent(false);
}

function inserirTabela() {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 4, withHeaderRow: true }).run();
}

function definirLink() {
  if (!editor.value) return;

  const hrefAtual = editor.value.getAttributes('link').href || '';
  const url = window.prompt('Informe a URL do link:', hrefAtual);

  if (url === null) return;

  if (url.trim() === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    return;
  }

  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
}

async function emitirPdfEditado() {
  if (!documentoAtual.value) return;

  const html = obterHtmlEditor();
  if (!html.trim()) {
    alert('Documento vazio.');
    return;
  }

  if (!confirm('Emitir PDF com o documento atual?')) return;

  sending.value = true;

  try {
    const form = new FormData();
    form.append('instance_id', instanceId.value);
    form.append('id_usuario', 1);
    form.append('id_autorizacao', documentoAtual.value.id_autorizacao);
    if (documentoAtual.value.id_fornecedor_senior) {
      form.append('id_fornecedor_senior', documentoAtual.value.id_fornecedor_senior);
    }
    form.append('html_documento', html);

    const res = await fetch(`${API_CONTROLLER}?acao=emitir_autorizacao_editada`, {
      method: 'POST',
      body: form
    });
    const json = await res.json();

    if (json.erro) throw new Error(json.erro);

    localStorage.removeItem(chaveRascunho());
    alert('PDF emitido com sucesso.');
    modoEdicao.value = false;
    editor.value?.commands.clearContent(false);
    await carregarLista();
  } catch (e) {
    alert('Erro: ' + e.message);
  } finally {
    sending.value = false;
  }
}

async function enviarEConcluir() {
  if (!confirm('Enviar e-mails?')) return;
  sending.value = true;
  try {
    const form = new FormData();
    form.append('instance_id', instanceId.value);
    form.append('id_usuario', 1);
    const res = await fetch(`${API_CONTROLLER}?acao=enviar_emails`, { method: 'POST', body: form });
    const json = await res.json();
    if (json.erro) throw new Error(json.erro);
    alert('Enviado!');
    window.history.back();
  } catch (e) {
    alert('Erro: ' + e.message);
  } finally {
    sending.value = false;
  }
}

function montarLink(caminhoRelativo) {
  if (!caminhoRelativo) return '#';

  let path = caminhoRelativo.replace(/^\//, '');

  if (path.startsWith('public/')) {
    path = path.replace('public/', '');
  }

  if (path.startsWith('http')) return path;

  return BASE_APACHE + path;
}

function formatMoeda(valor) {
  const numero = Number(valor || 0);
  return numero.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function voltar() {
  window.history.back();
}
</script>

<style scoped>
.page-container {
  max-width: 1120px;
  height: 100vh;
  max-height: 100vh;
  margin: 0 auto;
  padding: 10px 20px 14px;
  box-sizing: border-box;
  font-family: 'Segoe UI', sans-serif;
  color: #333;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.header-bar {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid #f0f0f0;
  padding-bottom: 10px;
  margin-bottom: 10px;
}

.title-area h2 {
  margin: 0;
  color: #2c3e50;
}

.subtitle {
  color: #7f8c8d;
  font-size: 0.9rem;
}

.content {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
}

.btn-primary {
  background-color: #27ae60;
  color: white;
  padding: 12px 25px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 600;
}

.btn-secondary {
  background-color: #0d6efd;
  color: white;
  padding: 10px 18px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 600;
}

.btn-secondary:disabled,
.btn-primary:disabled,
.btn-back:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-back {
  background: white;
  border: 1px solid #ced4da;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-view {
  background-color: #3498db;
  color: white;
  padding: 6px 12px;
  text-decoration: none;
  border-radius: 4px;
  font-size: 0.9rem;
}

.btn-warning-action {
  background-color: #e67e22;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}

.row-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-edit-doc {
  background-color: #e67e22;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
}

.btn-edit-doc:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-finish {
  background-color: #2c3e50;
  color: white;
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 6px;
  font-size: 1.1rem;
  cursor: pointer;
  font-weight: bold;
  margin-top: 10px;
}

.btn-finish:disabled {
  background-color: #95a5a6;
  cursor: not-allowed;
}

.btn-back-highlight {
  background-color: #6c757d;
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  margin-top: 15px;
  font-weight: 600;
}

.table-docs {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  background: white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table-docs th {
  background: #f8f9fa;
  text-align: left;
  padding: 12px;
  border-bottom: 2px solid #dee2e6;
}

.table-docs td {
  padding: 12px;
  border-bottom: 1px solid #dee2e6;
  vertical-align: middle;
}

.alert-success {
  background-color: #d1e7dd;
  color: #0f5132;
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 20px;
  border: 1px solid #badbcc;
}

.alert-warning {
  background-color: #fff3cd;
  color: #856404;
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 20px;
  border: 1px solid #ffeeba;
}

.badge-error {
  background-color: #f8d7da;
  color: #721c24;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: bold;
}

.badge-muted {
  display: inline-block;
  background-color: #e9ecef;
  color: #495057;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 0.82rem;
  font-weight: 600;
}

.badge-ok {
  display: inline-block;
  background-color: #d1e7dd;
  color: #0f5132;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 0.82rem;
  font-weight: 700;
}

.auth-section {
  margin-top: 18px;
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.section-title-row h3 {
  margin: 0 0 4px;
  color: #2c3e50;
}

.muted-small {
  color: #6c757d;
  font-size: 0.84rem;
  margin-top: 3px;
}

.docs-list {
  height: 100%;
  min-height: 0;
  overflow: auto;
  padding-bottom: 18px;
}

.editor-area {
  height: 100%;
  min-height: 0;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.editor-topbar {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 10px 14px;
  background: #ffffff;
  border-bottom: 1px solid #dee2e6;
}

.editor-hint {
  margin-top: 3px;
  font-size: 0.85rem;
  color: #6c757d;
}

.editor-topbar-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  white-space: nowrap;
}

.editor-body {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f5f6f8;
  padding: 12px;
}

.field-row {
  flex: 0 0 auto;
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
}

.field-row label {
  font-weight: 600;
}

.field-row select {
  padding: 8px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  min-width: 320px;
}

.editor-shell {
  flex: 1 1 auto;
  min-height: 0;
  border: 1px solid #cbd5e1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #e9ecef;
}

.tiptap-menubar {
  flex: 0 0 auto;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 8px;
  background: #f8f9fa;
  border-bottom: 1px solid #cbd5e1;
}

.tiptap-menubar button {
  min-height: 30px;
  padding: 5px 9px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.86rem;
}

.tiptap-menubar button.active {
  background: #0d6efd;
  color: #ffffff;
  border-color: #0d6efd;
}

.tiptap-menubar .sep {
  width: 1px;
  background: #cbd5e1;
  margin: 3px 5px;
}

.editor-scroll {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  padding: 18px;
}

.editor-page {
  width: 900px;
  max-width: 100%;
  min-height: 100%;
  margin: 0 auto;
  background: #ffffff;
  padding: 34px 44px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.10);
  box-sizing: border-box;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 60px;
  background-color: #f9f9f9;
  border-radius: 8px;
}

.icon-big {
  font-size: 4rem;
  opacity: 0.5;
  margin-bottom: 15px;
}

.error-state {
  text-align: center;
  padding: 50px;
  background-color: #fff5f5;
  border: 2px solid #ffcccc;
  border-radius: 8px;
  color: #a71d2a;
}

.icon-error {
  font-size: 4rem;
  margin-bottom: 15px;
}

.error-state h3 {
  margin-bottom: 10px;
  color: #721c24;
}

.error-hint {
  font-size: 0.9rem;
  color: #666;
  margin-top: 5px;
}

.regenerate-area {
  margin-top: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #fafafa;
  padding: 15px;
  border-radius: 6px;
  border: 1px solid #eee;
}

.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 15px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Documento editável Tiptap */
:deep(.ProseMirror) {
  outline: none;
  min-height: 520px;
  font-family: sans-serif;
  font-size: 14px;
  line-height: 1.45;
  color: #222;
}

:deep(.ProseMirror h1),
:deep(.ProseMirror h2),
:deep(.ProseMirror h3) {
  line-height: 1.2;
}

:deep(.ProseMirror h2.titulo),
:deep(.ProseMirror .titulo) {
  color: #004085;
  border-bottom: 2px solid #004085;
  padding-bottom: 6px;
  margin-bottom: 20px;
}

:deep(.ProseMirror .cabecalho) {
  margin-bottom: 28px;
  text-align: center;
}

:deep(.ProseMirror .logo-fundunesp) {
  display: block;
  margin: 0 auto 24px;
  width: 160px;
  max-width: 45%;
  height: auto;
}

:deep(.ProseMirror table) {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
  table-layout: fixed;
}

:deep(.ProseMirror td),
:deep(.ProseMirror th) {
  border: 1px solid #ccc;
  padding: 6px;
  vertical-align: top;
  min-width: 1em;
  position: relative;
}

:deep(.ProseMirror th) {
  background-color: #f8f9fa;
  font-weight: 700;
}

:deep(.ProseMirror .selectedCell:after) {
  z-index: 2;
  position: absolute;
  content: '';
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: rgba(13, 110, 253, 0.12);
  pointer-events: none;
}

:deep(.ProseMirror p) {
  margin: 0 0 0.75rem;
}

:deep(.ProseMirror ul),
:deep(.ProseMirror ol) {
  padding-left: 1.4rem;
}

:deep(.ProseMirror a) {
  color: #0d6efd;
  text-decoration: underline;
}

:deep(.ProseMirror blockquote) {
  border-left: 3px solid #cbd5e1;
  padding-left: 12px;
  color: #555;
}

:deep(.ProseMirror .text-right),
:deep(.ProseMirror [style*="text-align: right"]) {
  text-align: right;
}

@media (max-width: 760px) {
  .page-container {
    padding: 8px;
  }

  .editor-topbar {
    align-items: stretch;
    flex-direction: column;
  }

  .editor-topbar-actions {
    justify-content: flex-end;
    flex-wrap: wrap;
    white-space: normal;
  }

  .field-row {
    align-items: stretch;
    flex-direction: column;
  }

  .field-row select {
    min-width: 0;
    width: 100%;
  }

  .editor-scroll {
    padding: 8px;
  }

  .editor-page {
    padding: 24px 18px;
  }
}
</style>
