<template>
  <div class="page-container">
    <div class="header-bar">
      <div class="title-area">
        <h2>Gerar Ordem de Compra</h2>
        <span class="subtitle">Processo #{{ instanceId }}</span>
      </div>
      <button class="btn-back" type="button" @click="voltar">Voltar ao Fluxo</button>
    </div>

    <div class="content">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Carregando autorizacoes...</p>
      </div>

      <div v-else-if="autorizacoes.length === 0" class="empty-state">
        <h3>Nenhuma autorizacao encontrada</h3>
        <p>Gere as autorizacoes de compra antes de solicitar a ordem de compra no Senior.</p>
      </div>

      <div v-else class="table-shell">
        <div v-if="mensagem" :class="['alert', sucessoUltimaOperacao ? 'alert-success' : 'alert-error']">
          <strong>{{ sucessoUltimaOperacao ? 'Sucesso' : 'Erro' }}:</strong> {{ mensagem }}
        </div>

        <table class="table-orders">
          <thead>
            <tr>
              <th>Autorizacao</th>
              <th>Fornecedor</th>
              <th>Ordem de compra</th>
              <th>Valor</th>
              <th>Acao</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="auth in autorizacoes" :key="`${auth.id_autorizacao}-${auth.id_fornecedor_senior}`">
              <td>#{{ auth.id_autorizacao }}</td>
              <td>
                <strong>{{ auth.nome_do_fornecedor }}</strong>
                <div class="muted">Senior: {{ auth.id_fornecedor_senior }}</div>
              </td>
              <td>
                <span v-if="auth.ordem_de_compra" class="badge-ok">{{ auth.ordem_de_compra }}</span>
                <span v-else class="badge-muted">Pendente</span>
              </td>
              <td>{{ formatMoeda(auth.valor_total_pedido) }}</td>
              <td>
                <button
                  type="button"
                  class="btn-primary"
                  :disabled="sending"
                  @click="gerarOrdemCompra(auth)"
                >
                  {{ textoBotao(auth) }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <section v-if="xmlEnvio || xmlRetorno" class="xml-panel">
          <details v-if="xmlEnvio" open>
            <summary>XML enviado</summary>
            <pre>{{ xmlEnvio }}</pre>
          </details>
          <details v-if="xmlRetorno" open>
            <summary>XML retornado</summary>
            <pre>{{ xmlRetorno }}</pre>
          </details>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const API_CONTROLLER = '/backend/modulos/OrdemDeCompra/OrdemDeCompraController.php';

const instanceId = ref(null);
const autorizacoes = ref([]);
const loading = ref(true);
const sending = ref(false);
const sendingId = ref('');
const mensagem = ref('');
const sucessoUltimaOperacao = ref(false);
const xmlEnvio = ref('');
const xmlRetorno = ref('');

onMounted(async () => {
  const params = new URLSearchParams(window.location.search);
  instanceId.value = params.get('instance_id');

  if (!instanceId.value) {
    loading.value = false;
    mensagem.value = 'ID do processo nao encontrado.';
    sucessoUltimaOperacao.value = false;
    return;
  }

  await carregarAutorizacoes();
});

async function carregarAutorizacoes() {
  loading.value = true;
  try {
    const res = await fetch(`${API_CONTROLLER}?acao=listar_autorizacoes&instance_id=${instanceId.value}`);
    const json = await res.json();
    if (json.erro) throw new Error(json.erro);

    autorizacoes.value = json.autorizacoes || [];
  } catch (e) {
    mensagem.value = e.message;
    sucessoUltimaOperacao.value = false;
  } finally {
    loading.value = false;
  }
}

async function gerarOrdemCompra(auth) {
  if (!confirm(`Solicitar ordem de compra para ${auth.nome_do_fornecedor}?`)) return;

  sending.value = true;
  sendingId.value = chave(auth);
  mensagem.value = '';
  xmlEnvio.value = '';
  xmlRetorno.value = '';

  try {
    const form = new FormData();
    form.append('instance_id', instanceId.value);
    form.append('id_autorizacao', auth.id_autorizacao);
    form.append('id_fornecedor_senior', auth.id_fornecedor_senior);

    const res = await fetch(`${API_CONTROLLER}?acao=gerar_ordem_compra`, {
      method: 'POST',
      body: form,
    });
    const json = await res.json();

    if (json.erro) throw new Error(json.erro);
    if (!json.sucesso) {
      mensagem.value = json.mensagem || 'Erro ao gerar ordem de compra.';
      sucessoUltimaOperacao.value = false;
      xmlEnvio.value = json.xml_envio || '';
      xmlRetorno.value = json.xml_retorno || '';
      return;
    }

    xmlEnvio.value = '';
    xmlRetorno.value = '';
    mensagem.value = `${json.mensagem} Numero: ${json.num_ocp}`;
    sucessoUltimaOperacao.value = true;
    await carregarAutorizacoes();
  } catch (e) {
    mensagem.value = e.message;
    sucessoUltimaOperacao.value = false;
  } finally {
    sending.value = false;
    sendingId.value = '';
  }
}

function chave(auth) {
  return `${auth.id_autorizacao}-${auth.id_fornecedor_senior}`;
}

function textoBotao(auth) {
  if (sendingId.value === chave(auth)) return 'Solicitando...';
  return auth.ordem_de_compra ? 'Solicitar novamente' : 'Solicitar OC';
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
  padding: 12px 20px 16px;
  box-sizing: border-box;
  font-family: 'Segoe UI', sans-serif;
  color: #263238;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.header-bar {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid #edf0f2;
  padding-bottom: 10px;
  margin-bottom: 12px;
  gap: 16px;
}

.title-area h2 {
  margin: 0;
  color: #213547;
}

.subtitle,
.muted {
  color: #607d8b;
  font-size: 0.9rem;
}

.content {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
}

.table-shell {
  min-width: 0;
}

.table-orders {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #dfe6ec;
}

.table-orders th {
  background: #f6f8fa;
  text-align: left;
  padding: 12px;
  border-bottom: 2px solid #dfe6ec;
  color: #34495e;
}

.table-orders td {
  padding: 12px;
  border-bottom: 1px solid #e8edf1;
  vertical-align: middle;
}

.btn-primary,
.btn-back {
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 700;
}

.btn-primary {
  background-color: #1f7a4d;
  color: #fff;
  padding: 9px 14px;
  min-width: 120px;
}

.btn-primary:disabled {
  opacity: 0.62;
  cursor: not-allowed;
}

.btn-back {
  background: #fff;
  border: 1px solid #cbd5dd;
  color: #34495e;
  padding: 8px 16px;
}

.badge-ok,
.badge-muted {
  display: inline-block;
  padding: 5px 10px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 700;
}

.badge-ok {
  background-color: #d8f1e4;
  color: #145c38;
}

.badge-muted {
  background-color: #e9eef2;
  color: #50606b;
}

.alert {
  padding: 12px 14px;
  border-radius: 6px;
  margin-bottom: 12px;
  border: 1px solid transparent;
}

.alert-success {
  background: #d8f1e4;
  color: #145c38;
  border-color: #b8dfca;
}

.alert-error {
  background: #fde2e1;
  color: #8a1f17;
  border-color: #f5bab7;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 48px;
  background: #f8fafb;
  border: 1px solid #e1e8ed;
  border-radius: 8px;
}

.spinner {
  border: 4px solid #edf2f7;
  border-top: 4px solid #2176ae;
  border-radius: 50%;
  width: 38px;
  height: 38px;
  animation: spin 1s linear infinite;
  margin: 0 auto 14px;
}

.xml-panel {
  margin-top: 14px;
  display: grid;
  gap: 10px;
}

.xml-panel details {
  border: 1px solid #d7e0e7;
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
}

.xml-panel summary {
  cursor: pointer;
  padding: 10px 12px;
  font-weight: 700;
  background: #f6f8fa;
}

.xml-panel pre {
  margin: 0;
  padding: 12px;
  overflow: auto;
  max-height: 320px;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.84rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 760px) {
  .page-container {
    padding: 8px;
  }

  .header-bar {
    align-items: stretch;
    flex-direction: column;
  }

  .table-orders {
    min-width: 760px;
  }
}
</style>
