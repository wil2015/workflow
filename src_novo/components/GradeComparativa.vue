<template>
  <div class="grade-container">
    <div class="header-grade">
      <h2>Grade Comparativa</h2>
      <p>Os itens marcados em verde representam o menor preço encontrado.</p>
    </div>

    <div class="conteudo-grade">
      <div v-if="loading" class="msg-loading">Carregando e calculando vencedores...</div>
      <div v-else-if="erro" class="msg-erro">{{ erro }}</div>
      
      <div v-else class="tabela-scroll">
        <table class="table-grade">
          <thead>
            <tr>
              <th class="col-fixa">Item / Produto</th>
              <th class="col-melhor">Melhor Preço</th>
              <th v-for="f in dados.cabecalho" :key="f.id" :title="f.nome_completo">
                {{ f.nome_curto }}<br>
                <small>Cód: {{ f.id }}</small>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="linha in dados.linhas" :key="linha.chave">
              <td class="col-fixa">
                <span class="cod-item">{{ linha.chave }}</span><br>
                {{ linha.produto }}<br>
                <span class="qtd-badge">Qtd: {{ linha.qtd_fmt }}</span>
              </td>
              
              <td class="col-melhor-val">{{ linha.melhor_fmt }}</td>

              <td 
                v-for="f in dados.cabecalho" 
                :key="f.id"
                :class="getClassCelula(linha.celulas[f.id].status)"
              >
                {{ linha.celulas[f.id].valor_fmt }}
                <div v-if="linha.celulas[f.id].status === 'winner'" class="badge-venceu">★ VENCEU</div>
                <div v-if="linha.celulas[f.id].status === 'tie'" class="badge-empate">EMPATE</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="footer-grade">
      <div class="box-total">
        Total Estimado: <strong>R$ {{ dados.total_fmt }}</strong>
      </div>
      <div class="botoes">
         <button class="btn-voltar" @click="voltar">Voltar</button>
         <button class="btn-consolidar" @click="consolidar" :disabled="loading || salvando">
            {{ salvando ? 'Salvando...' : 'Gerar Documento / Consolidar' }}
         </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const instanceId = ref(null);
const loading = ref(true);
const salvando = ref(false);
const erro = ref(null);
const dados = ref({ cabecalho: [], linhas: [], total_fmt: '0,00' });

onMounted(() => {
    // Pega ID da URL
    const params = new URLSearchParams(window.location.search);
    instanceId.value = params.get('instance_id');
    
    if(instanceId.value) carregarDados();
    else erro.value = "ID do processo não fornecido.";
});

async function carregarDados() {
    try {
        const req = await fetch(`/backend/api_grade_comparativa.php?instance_id=${instanceId.value}`);
        const json = await req.json();
        
        if (json.erro) {
            erro.value = json.erro;
        } else {
            dados.value = json;
        }
    } catch (e) {
        erro.value = "Erro ao conectar com servidor.";
    } finally {
        loading.value = false;
    }
}

async function consolidar() {
    if (!confirm('Deseja consolidar os vencedores e gerar o documento?')) return;
    
    salvando.value = true;
    const fd = new FormData();
    fd.append('acao', 'consolidar_vencedores');
    fd.append('id_processo', instanceId.value);

    try {
        // Usa o seu arquivo existente consolidar_grade.php
        const req = await fetch('/backend/acoes/consolidar_grade.php', { method: 'POST', body: fd });
        const res = await req.json();

        if (res.sucesso) {
            alert('Sucesso! Valor Final: R$ ' + res.valor_gravado);
            window.location.href = '/'; // Volta pro dashboard ou outra tela
        } else {
            alert('Erro: ' + res.erro);
        }
    } catch (e) {
        alert('Erro de conexão.');
    } finally {
        salvando.value = false;
    }
}

function voltar() {
    window.history.back();
}

function getClassCelula(status) {
    if (status === 'winner') return 'cell-winner';
    if (status === 'tie') return 'cell-tie';
    if (status === 'loser') return 'cell-loser';
    return 'cell-empty';
}
</script>

<style scoped>
.grade-container { height: 100vh; display: flex; flex-direction: column; background: #fff; font-family: sans-serif; overflow: hidden; }
.header-grade { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.conteudo-grade { flex: 1; overflow: hidden; display: flex; flex-direction: column; position: relative; }
.tabela-scroll { flex: 1; overflow: auto; padding-bottom: 20px; }

/* Tabela */
.table-grade { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1000px; font-size: 13px; }
.table-grade th, .table-grade td { padding: 10px; border-bottom: 1px solid #ddd; border-right: 1px solid #eee; text-align: center; vertical-align: middle; }
.table-grade th { background: #f1f3f5; position: sticky; top: 0; z-index: 10; font-weight: 600; color: #444; border-top: 1px solid #ddd; }

/* Coluna Fixa (Produto) */
.col-fixa { 
    position: sticky; left: 0; z-index: 11; background: #fff; 
    text-align: left !important; width: 300px; min-width: 250px;
    border-right: 2px solid #ccc !important; 
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
}
.table-grade th.col-fixa { z-index: 12; background: #f8f9fa; }

/* Estilos de Células */
.cell-winner { background-color: #d4edda; color: #155724; font-weight: bold; border: 2px solid #c3e6cb !important; }
.cell-tie { background-color: #fff3cd; color: #856404; font-weight: bold; border: 2px solid #ffeeba !important; }
.cell-loser { color: #999; font-size: 12px; }
.cell-empty { background: #fafafa; color: #ddd; }

.col-melhor { width: 100px; background: #e9ecef !important; }
.col-melhor-val { background: #f8f9fa; font-weight: bold; }

/* Badges e Textos */
.cod-item { font-weight: bold; font-size: 11px; color: #666; }
.qtd-badge { background: #6c757d; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-top: 4px; display: inline-block; }
.badge-venceu { font-size: 9px; margin-top: 4px; color: #155724; }
.badge-empate { font-size: 9px; margin-top: 4px; color: #856404; }

/* Footer */
.footer-grade { padding: 15px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #fff; }
.box-total { font-size: 16px; border-left: 5px solid #28a745; padding-left: 10px; background: #f8f9fa; padding: 10px; border-radius: 4px; }
.btn-consolidar { background: #007bff; color: white; border: none; padding: 12px 25px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
.btn-consolidar:disabled { background: #ccc; cursor: not-allowed; }
.btn-voltar { background: transparent; border: 1px solid #ccc; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer; }

.msg-loading, .msg-erro { padding: 50px; text-align: center; color: #666; font-size: 16px; }
.msg-erro { color: red; }
</style>