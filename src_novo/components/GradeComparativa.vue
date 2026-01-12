<template>
  <div class="grade-container">
    <div class="header-grade">
      <h2>Grade Comparativa</h2>
      <p>Os itens marcados em verde representam o menor preço encontrado.</p>
    </div>

    <div class="conteudo-grade">
      <div v-if="loading" class="msg-loading">Carregando...</div>
      <div v-else-if="erro" class="msg-erro">{{ erro }}</div>
      
      <div v-else class="tabela-scroll">
        <table class="table-grade">
          <thead>
            <tr>
              <th class="col-fixa">Produto</th>
              <th class="col-melhor">Melhor Preço</th>
              <th v-for="f in dados.cabecalho" :key="f.id">{{ f.nome_curto }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="linha in dados.linhas" :key="linha.chave">
              <td class="col-fixa">
                <b>{{ linha.produto }}</b><br><small>{{ linha.chave }}</small>
              </td>
              <td class="col-melhor-val">{{ linha.melhor_fmt }}</td>
              <td v-for="f in dados.cabecalho" :key="f.id" :class="getClassCelula(linha.celulas[f.id].status)">
                {{ linha.celulas[f.id].valor_fmt }}
                <div v-if="linha.celulas[f.id].status === 'winner'" class="badge-venceu">★</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="footer-grade">
      <div class="box-total">Total: <strong>R$ {{ dados.total_fmt }}</strong></div>
      <div class="botoes">
         <button class="btn-voltar" @click="voltar">Voltar</button>
         <button class="btn-consolidar" @click="consolidar" :disabled="loading || salvando">
            {{ salvando ? 'Salvando...' : 'Gerar Documento' }}
         </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

// --- MUDANÇA 1: Props e Emits ---
const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

const loading = ref(true);
const salvando = ref(false);
const erro = ref(null);
const dados = ref({ cabecalho: [], linhas: [], total_fmt: '0,00' });

const API_URL = '/backend/modulos/GradeComparativa/GradeComparativaController.php';

onMounted(() => {
    // --- MUDANÇA 2: Usa props ---
    if(props.instanceId) carregarDados();
    else {
        erro.value = "ID não fornecido.";
        loading.value = false;
    }
});

async function carregarDados() {
    try {
        const req = await fetch(`${API_URL}?acao=carregar_grade&instance_id=${props.instanceId}`);
        const json = await req.json();
        if (json.erro) erro.value = json.erro;
        else dados.value = json;
    } catch (e) {
        erro.value = "Erro de conexão.";
    } finally {
        loading.value = false;
    }
}

async function consolidar() {
    if (!confirm('Consolidar vencedores?')) return;
    salvando.value = true;
    
    const payload = { acao: 'consolidar_vencedores', id_processo: props.instanceId };

    try {
        const req = await fetch(API_URL, { 
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const res = await req.json();

        if (res.sucesso) {
            alert('Sucesso! Valor: R$ ' + res.valor_gravado);
            emit('fechar'); // Fecha o modal ao terminar
        } else {
            alert('Erro: ' + (res.erro || res.msg));
        }
    } catch (e) { alert('Erro de conexão.'); } 
    finally { salvando.value = false; }
}

function voltar() {
    emit('fechar');
}

function getClassCelula(status) {
    if (status === 'winner') return 'cell-winner';
    if (status === 'tie') return 'cell-tie';
    return 'cell-normal';
}
</script>

<style scoped>
/* Use o CSS original do arquivo GradeComparativa.vue */
.grade-container { height: 100%; display: flex; flex-direction: column; background: #fff; }
.header-grade { padding: 15px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
.conteudo-grade { flex: 1; overflow: auto; padding: 10px; }
.footer-grade { padding: 15px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #fff; }
.table-grade { width: 100%; border-collapse: collapse; min-width: 800px; }
.table-grade th, .table-grade td { border: 1px solid #ccc; padding: 8px; text-align: center; }
.col-fixa { background: #fff; position: sticky; left: 0; }
.cell-winner { background: #d4edda; color: #155724; font-weight: bold; border: 2px solid #c3e6cb; }
.btn-consolidar { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.btn-voltar { background: #ccc; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px; }
</style>