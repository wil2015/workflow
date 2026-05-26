<template>
  <div class="grade-container">
    <div class="header-grade">
      <h2>Grade Comparativa</h2>
      <p>
        Os itens marcados em verde representam o menor preço encontrado <b>entre as cotações que atendem</b>.
        Cotações recusadas (Não atende) não concorrem ao menor preço.
      </p>
    </div>

    <div class="conteudo-grade">
      <div v-if="loading" class="msg-loading">
        <div class="spinner"></div>
        Carregando dados e calculando vencedores...
      </div>

      <div v-else class="tabela-wrapper">
        <table class="table-grade">
          <thead>
            <tr>
              <th class="col-prod-header">Item / Produto</th>
              <th class="col-melhor-header">Melhor Preço</th>
              <th v-for="f in dados.cabecalho" :key="f.id" class="col-forn">
                <div class="forn-nome" :title="f.nome_completo">{{ f.nome_curto }}</div>
                <div class="forn-cod">Cód: {{ f.id }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="linha in dados.linhas" :key="linha.chave">
              <td class="col-prod">
                <div class="prod-cod">{{ linha.chave }}</div>
                <div class="prod-nome">{{ linha.produto }}</div>
                <div class="prod-qtd">Qtd: {{ linha.qtd_fmt }}</div>
              </td>

              <td class="col-melhor-val">{{ linha.melhor_fmt }}</td>

              <td
                v-for="f in dados.cabecalho"
                :key="f.id"
                :class="getClassCelula(linha.celulas?.[f.id]?.status)"
              >
                <template v-if="linha.celulas && linha.celulas[f.id]">
                  <div
                    class="valor-celula"
                    :class="{ 'valor-riscado': linha.celulas[f.id].status === 'rejected' }"
                  >
                    {{ linha.celulas[f.id].valor_fmt }}
                  </div>

                  <div v-if="linha.celulas[f.id].status === 'winner'" class="badge-venceu">★ VENCEU</div>
                  <div v-if="linha.celulas[f.id].status === 'tie'" class="badge-empate">EMPATE</div>
                  <div v-if="linha.celulas[f.id].status === 'rejected'" class="badge-recusada">RECUSADA</div>

                  <div v-if="mostrarControles(linha.celulas[f.id])" class="celula-controles">
                    <label class="flag-atende">
                      <input
                        type="checkbox"
                        v-model="linha.celulas[f.id].atende"
                        @change="onToggleAtende(linha, linha.celulas[f.id])"
                      />
                      Atende
                    </label>

                    <input
                      v-if="!linha.celulas[f.id].atende"
                      class="input-just"
                      :class="{ 'input-just-erro': !linha.celulas[f.id].justificativa_da_recusa?.trim() }"
                      type="text"
                      maxlength="50"
                      placeholder="Justificativa (até 50)"
                      v-model="linha.celulas[f.id].justificativa_da_recusa"
                      @input="onEditJust(linha, linha.celulas[f.id])"
                    />
                  </div>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="footer-grade">
      <div class="botoes">
        <button class="btn-voltar" @click="voltar">Voltar</button>
      </div>
      <div class="box-total">
        <span class="label-total">Total Estimado:</span>
        <span class="valor-total">R$ {{ dados.total_fmt }}</span>
      </div>
      <div class="botoes">
        <button class="btn-consolidar" @click="consolidar" :disabled="loading || salvando">
          {{ salvando ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

const loading = ref(true);
const salvando = ref(false);
const dados = ref({ cabecalho: [], linhas: [], total_fmt: '0,00', total_raw: 0 });

const API_URL = '/backend/modulos/GradeComparativa/GradeComparativaController.php';

const nf = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function fmt(n) {
  const v = Number(n);
  if (!Number.isFinite(v)) return '0,00';
  return nf.format(v);
}

onMounted(() => {
  if (props.instanceId) carregarDados();
  else {
    alert('ID não fornecido.');
    emit('fechar');
  }
});

async function carregarDados() {
  try {
    const req = await fetch(`${API_URL}?acao=carregar_grade&instance_id=${props.instanceId}`);

    let json;
    try {
      json = await req.json();
    } catch (_e) {
      throw new Error('Falha ao processar resposta do servidor (JSON inválido).');
    }

    if (json.erro) throw new Error(json.erro);

    dados.value = json;
    normalizarDados();
    recalcularTudo();
  } catch (e) {
    console.error(e);
    alert('ERRO AO CARREGAR GRADE:\n' + (e?.message || String(e)));
    emit('fechar');
  } finally {
    loading.value = false;
  }
}

function normalizarDados() {
  for (const linha of (dados.value.linhas || [])) {
    if (linha.qtd_raw === undefined || linha.qtd_raw === null) {
      // fallback: converte qtd_fmt (pt-BR) para number
      const s = String(linha.qtd_fmt || linha.qtd || '1').replace(/\./g, '').replace(',', '.');
      const n = Number(s);
      linha.qtd_raw = Number.isFinite(n) && n > 0 ? n : 1;
    }

    const celulas = linha.celulas || {};
    for (const fid of Object.keys(celulas)) {
      const c = celulas[fid];
      if (!c) continue;
      c.atende = !!c.atende;
      if (c.justificativa_da_recusa === null || c.justificativa_da_recusa === undefined) {
        c.justificativa_da_recusa = '';
      }
      // garante formatos iniciais
      const v = Number(c.valor || 0);
      c.valor_fmt = v > 0 ? fmt(v) : '-';
    }
  }

  if (dados.value.total_raw === undefined || dados.value.total_raw === null) {
    dados.value.total_raw = 0;
  }
  if (!dados.value.total_fmt) {
    dados.value.total_fmt = fmt(dados.value.total_raw);
  }
}

function mostrarControles(c) {
  if (!c) return false;
  const valor = Number(c.valor || 0);
  return !!c.oferta_id && valor > 0;
}

function onToggleAtende(linha, celula) {
  if (celula.atende) {
    celula.justificativa_da_recusa = '';
  }
  recalcularLinha(linha);
  recalcularTotal();
}

function onEditJust(_linha, celula) {
  if (!celula) return;
  if (celula.justificativa_da_recusa && celula.justificativa_da_recusa.length > 50) {
    celula.justificativa_da_recusa = celula.justificativa_da_recusa.slice(0, 50);
  }
}

function recalcularLinha(linha) {
  const celulas = linha.celulas || {};

  // menor entre cotações válidas e que atendem
  const validos = [];
  for (const fid of Object.keys(celulas)) {
    const c = celulas[fid];
    if (!c) continue;
    const valor = Number(c.valor || 0);
    if (valor > 0 && c.atende) validos.push(valor);
  }

  const menor = validos.length ? Math.min(...validos) : null;
  const empates = menor === null ? 0 : validos.filter(v => Math.abs(v - menor) < 0.001).length;

  // atualiza status e formatos
  for (const fid of Object.keys(celulas)) {
    const c = celulas[fid];
    if (!c) continue;
    const valor = Number(c.valor || 0);

    if (valor <= 0) c.status = 'empty';
    else if (!c.atende) c.status = 'rejected';
    else if (menor !== null && Math.abs(valor - menor) < 0.001) c.status = empates > 1 ? 'tie' : 'winner';
    else c.status = 'loser';

    c.valor_fmt = valor > 0 ? fmt(valor) : '-';
  }

  linha.melhor_raw = menor;
  linha.melhor_fmt = menor !== null ? fmt(menor) : '-';
}

function recalcularTotal() {
  let total = 0;
  for (const linha of (dados.value.linhas || [])) {
    const qtd = Number(linha.qtd_raw || 0);
    const menor = linha.melhor_raw;
    if (Number.isFinite(qtd) && qtd > 0 && menor !== null && menor !== undefined) {
      total += Number(menor) * qtd;
    }
  }
  dados.value.total_raw = total;
  dados.value.total_fmt = fmt(total);
}

function recalcularTudo() {
  for (const linha of (dados.value.linhas || [])) {
    recalcularLinha(linha);
  }
  recalcularTotal();
}

function validarJustificativas() {
  const pendencias = [];
  for (const linha of (dados.value.linhas || [])) {
    const celulas = linha.celulas || {};
    for (const fid of Object.keys(celulas)) {
      const c = celulas[fid];
      if (!mostrarControles(c)) continue;
      if (!c.atende) {
        const j = (c.justificativa_da_recusa || '').trim();
        if (!j) pendencias.push(linha.chave);
      }
    }
  }
  return pendencias;
}

async function consolidar() {
  const pend = validarJustificativas();
  if (pend.length) {
    alert('Existem cotações marcadas como NAO atende sem justificativa.\nItens: ' + pend.slice(0, 20).join(', '));
    return;
  }

  if (!confirm('Deseja salvar os vencedores e consolidar a grade?')) return;

  salvando.value = true;

  const ofertas = [];
  for (const linha of (dados.value.linhas || [])) {
    const celulas = linha.celulas || {};
    for (const fid of Object.keys(celulas)) {
      const c = celulas[fid];
      if (!c || !c.oferta_id) continue;
      ofertas.push({
        oferta_id: c.oferta_id,
        atende: !!c.atende,
        justificativa_da_recusa: (c.justificativa_da_recusa || '').trim(),
      });
    }
  }

  const payload = { acao: 'consolidar_vencedores', id_processo: props.instanceId, ofertas };

  try {
    const req = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    let res;
    try {
      res = await req.json();
    } catch (_e) {
      throw new Error('Erro fatal no servidor.');
    }

    if (res.sucesso) {
      const itensGravados = res.itens_gravados !== undefined ? `\nItens gravados na grade_de_custos: ${res.itens_gravados}` : '';
      alert('Valores salvos!\nValor Final: R$ ' + res.valor_gravado + itensGravados);
      emit('fechar');
    } else {
      alert('Erro ao salvar: ' + (res.erro || res.msg || 'erro desconhecido'));
    }
  } catch (e) {
    alert('Erro de conexão: ' + (e?.message || String(e)));
  } finally {
    salvando.value = false;
  }
}

function voltar() {
  emit('fechar');
}

function getClassCelula(status) {
  if (status === 'winner') return 'cell-winner';
  if (status === 'tie') return 'cell-tie';
  if (status === 'loser') return 'cell-loser';
  if (status === 'rejected') return 'cell-rejected';
  return 'cell-empty';
}
</script>

<style scoped>
.grade-container { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-grade { padding: 15px 20px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
.header-grade h2 { margin: 0; font-size: 20px; color: #333; }
.header-grade p { margin: 5px 0 0 0; font-size: 13px; color: #666; }

.conteudo-grade { flex: 1; overflow: hidden; position: relative; display: flex; flex-direction: column; }
.tabela-wrapper { flex: 1; overflow: auto; padding: 10px; }
.table-grade { border-collapse: separate; border-spacing: 0; min-width: 1000px; font-size: 12px; }
.table-grade th, .table-grade td { padding: 8px; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; text-align: center; vertical-align: middle; }
.table-grade thead th { position: sticky; top: 0; background-color: #f1f3f5; color: #333; z-index: 10; border-top: 1px solid #ddd; box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1); }

.col-prod, .col-prod-header { position: sticky; left: 0; background: #fff; z-index: 20; width: 300px; min-width: 250px; text-align: left !important; border-right: 2px solid #ccc !important; }
.col-prod-header { z-index: 30 !important; background: #f8f9fa !important; }

.prod-cod { font-weight: bold; color: #555; font-size: 11px; margin-bottom: 2px; }
.prod-nome { font-size: 12px; color: #000; line-height: 1.3; margin-bottom: 4px; display: block; }
.prod-qtd { background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; display: inline-block; }

.forn-nome { font-weight: bold; font-size: 11px; color: #333; margin-bottom: 2px; }
.forn-cod { font-weight: normal; color: #666; font-size: 10px; }

.col-melhor-val { background: #e9ecef; font-weight: bold; width: 90px; }
.col-melhor-header { width: 90px; background: #e2e6ea !important; }

.cell-winner { background-color: #d4edda; color: #155724; border: 2px solid #c3e6cb !important; position: relative; }
.cell-tie { background-color: #fff3cd; color: #856404; border: 2px solid #ffeeba !important; }
.cell-loser { color: #999; }
.cell-empty { background-color: #f9f9f9; color: #ccc; }
.cell-rejected { background-color: #f8d7da; color: #721c24; border: 2px solid #f5c6cb !important; }

.valor-celula { font-size: 13px; }
.valor-riscado { text-decoration: line-through; }

.badge-venceu { font-size: 10px; font-weight: bold; color: #155724; margin-top: 4px; display: block; }
.badge-empate { font-size: 10px; font-weight: bold; color: #856404; margin-top: 4px; display: block; }
.badge-recusada { font-size: 10px; font-weight: bold; color: #721c24; margin-top: 4px; display: block; }

.celula-controles { margin-top: 6px; display: flex; flex-direction: column; gap: 6px; align-items: stretch; }
.flag-atende { display: flex; gap: 6px; align-items: center; justify-content: center; font-size: 11px; }
.input-just { font-size: 11px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
.input-just-erro { border-color: #dc3545; }

.footer-grade { padding: 15px 20px; border-top: 1px solid #ddd; background: #fff; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 40; }
.box-total { background: #e9ecef; padding: 10px 20px; border-radius: 4px; border-left: 5px solid #28a745; font-size: 16px; }
.label-total { font-weight: normal; margin-right: 5px; color: #555; }
.valor-total { font-weight: bold; color: #000; }

.btn-consolidar { background: #007bff; color: white; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.2s; }
.btn-consolidar:hover:not(:disabled) { background: #0056b3; }
.btn-consolidar:disabled { opacity: 0.7; cursor: wait; }

.btn-voltar { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.btn-voltar:hover { background: #5a6268; }

.msg-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #666; }
.spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin-bottom: 10px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
