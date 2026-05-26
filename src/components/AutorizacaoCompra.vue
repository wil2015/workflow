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

      <div v-else-if="documentos.length > 0" class="docs-list">
        
        <div v-if="temArquivoQuebrado" class="alert-warning">
            ⚠️ <strong>Atenção:</strong> Arquivos físicos não encontrados.
        </div>
        <div v-else class="alert-success">
            ✅ <strong>Sucesso!</strong> Documentos disponíveis.
        </div>

        <table class="table-docs">
          <thead>
            <tr>
              <th>Data</th>
              <th>Arquivo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documentos" :key="doc.id">
              <td>{{ formatData(doc.criado_em) }}</td>
              <td>
                <span class="file-name">📄 {{ doc.nome_arquivo }}</span>
              </td>
              <td>
                <a v-if="doc.existe_fisicamente" :href="montarLink(doc.caminho_arquivo)" target="_blank" class="btn-view">
                   👁️ Visualizar PDF
                </a>
                <span v-else class="badge-error">❌ Perdido</span>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="podeGerar" class="regenerate-area" :class="{ 'highlight-warning': temArquivoQuebrado }">
            <div class="regenerate-info">
                <p>Atualizar documentos?</p>
                <small>Substituirá as versões atuais.</small>
            </div>
            <button @click="gerarDocumentos" class="btn-warning-action">
              🔄 Gerar Novamente
            </button>
        </div>

        <div class="actions-footer">
            <button @click="enviarEConcluir" class="btn-finish" :disabled="sending || temArquivoQuebrado">
                {{ sending ? 'Enviando...' : 'Enviar E-mails e Concluir' }}
            </button>
        </div>
      </div>

      <div v-else-if="podeGerar" class="empty-state">
        <div class="icon-big">🖨️</div>
        <h3>Nenhum documento gerado</h3>
        <p>A grade de preços está pronta. Clique abaixo para gerar os PDFs.</p>
        <button @click="gerarDocumentos" class="btn-primary">Gerar Autorizações</button>
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
import { ref, onMounted, computed } from 'vue';

const BASE_APACHE = 'http://localhost:8081/'; 
const API_CONTROLLER = '/backend/modulos/AutorizacaoCompra/AutorizacaoCompraController.php';

const instanceId = ref(null);
const documentos = ref([]);
const podeGerar = ref(false); // Estado novo para controle de bloqueio
const loading = ref(true);
const sending = ref(false);
const statusMsg = ref('Carregando...');

const temArquivoQuebrado = computed(() => {
    if (!documentos.value) return false;
    return documentos.value.some(d => d.existe_fisicamente === false);
});

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    instanceId.value = params.get('instance_id');
    if (instanceId.value) {
        await carregarLista();
    } else {
        loading.value = false;
        alert("Erro: ID não encontrado.");
    }
});

async function carregarLista() {
    loading.value = true;
    try {
        const url = `${API_CONTROLLER}?acao=listar_documentos&instance_id=${instanceId.value}`;
        const res = await fetch(url);
        const json = await res.json();
        
        if (json.erro) throw new Error(json.erro);
        
        // AGORA O BACKEND RETORNA UM OBJETO COM 'documentos' E 'tem_cotacao'
        // Se for estrutura antiga (array direto), faz fallback
        if (Array.isArray(json)) {
            documentos.value = json;
            podeGerar.value = true; // Assume true se backend for velho
        } else {
            documentos.value = json.documentos || [];
            podeGerar.value = json.tem_cotacao; // Boolean vindo do PHP
        }
        
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function gerarDocumentos() {
    if(!podeGerar.value) return; // Segurança extra no front
    if(!confirm("Gerar documentos oficiais?")) return;
    
    loading.value = true;
    statusMsg.value = "Gerando PDFs...";
    
    try {
        const form = new FormData();
        form.append('instance_id', instanceId.value);
        form.append('id_usuario', 1);

        const res = await fetch(`${API_CONTROLLER}?acao=gerar_autorizacoes`, { method: 'POST', body: form });
        const json = await res.json();
        
        if (json.erro) throw new Error(json.erro);
        
        alert("Sucesso!");
        await carregarLista();
        
    } catch (e) {
        alert("Erro: " + e.message);
    } finally {
        loading.value = false;
    }
}

async function enviarEConcluir() {
    if(!confirm("Enviar e-mails?")) return;
    sending.value = true;
    try {
        const form = new FormData();
        form.append('instance_id', instanceId.value);
        form.append('id_usuario', 1);
        const res = await fetch(`${API_CONTROLLER}?acao=enviar_emails`, { method: 'POST', body: form });
        const json = await res.json();
        if (json.erro) throw new Error(json.erro);
        alert("Enviado!");
        window.history.back();
    } catch (e) {
        alert("Erro: " + e.message);
    } finally {
        sending.value = false;
    }
}


function montarLink(caminhoRelativo) {
    if (!caminhoRelativo) return '#';
    
    // 1. Remove barra inicial se houver (ex: "/storage" vira "storage")
    let path = caminhoRelativo.replace(/^\//, '');
    
    // 2. CORREÇÃO: Se o caminho começar com "public/", removemos.
    // O objetivo é que o link final fique limpo: "http://.../storage/docs/..."
    if (path.startsWith('public/')) {
        path = path.replace('public/', '');
    }

    // 3. Verifica se é link absoluto ou relativo
    if (path.startsWith('http')) return path;
    
    // Retorna a URL base + o caminho limpo (ex: storage/docs/...)
    return BASE_APACHE + path;
}
function formatData(dt) { return dt ? new Date(dt).toLocaleString('pt-BR') : '-'; }
function voltar() { window.history.back(); }
</script>

<style scoped>
/* Estilos Gerais */
.page-container { max-width: 900px; margin: 0 auto; padding: 20px; font-family: 'Segoe UI', sans-serif; color: #333; }
.header-bar { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 25px; }
.title-area h2 { margin: 0; color: #2c3e50; }
.subtitle { color: #7f8c8d; font-size: 0.9rem; }

/* Botões */
.btn-primary { background-color: #27ae60; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: 600; }
.btn-back { background: white; border: 1px solid #ced4da; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
.btn-view { background-color: #3498db; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
.btn-warning-action { background-color: #e67e22; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-finish { background-color: #2c3e50; color: white; width: 100%; padding: 14px; border: none; border-radius: 6px; font-size: 1.1rem; cursor: pointer; font-weight: bold; margin-top: 10px; }
.btn-finish:disabled { background-color: #95a5a6; cursor: not-allowed; }

/* Novo Botão de Voltar Destacado para Erros */
.btn-back-highlight { background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; font-weight: 600; }
.btn-back-highlight:hover { background-color: #5a6268; }

/* Tabelas e Alertas */
.table-docs { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.table-docs th { background: #f8f9fa; text-align: left; padding: 12px; border-bottom: 2px solid #dee2e6; }
.table-docs td { padding: 12px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
.alert-success { background-color: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #badbcc; }
.alert-warning { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #ffeeba; }
.badge-error { background-color: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; }

/* Áreas de Estado */
.loading-state, .empty-state { text-align: center; padding: 60px; background-color: #f9f9f9; border-radius: 8px; }
.icon-big { font-size: 4rem; opacity: 0.5; margin-bottom: 15px; }

/* ESTADO DE ERRO (BLOQUEIO) */
.error-state {
    text-align: center;
    padding: 50px;
    background-color: #fff5f5; /* Fundo avermelhado leve */
    border: 2px solid #ffcccc;
    border-radius: 8px;
    color: #a71d2a;
}
.icon-error { font-size: 4rem; margin-bottom: 15px; }
.error-state h3 { margin-bottom: 10px; color: #721c24; }
.error-hint { font-size: 0.9rem; color: #666; margin-top: 5px; }

.regenerate-area { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; background-color: #fafafa; padding: 15px; border-radius: 6px; border: 1px solid #eee; }
.spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>