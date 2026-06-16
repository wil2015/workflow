<template>
  <div class="parametros-wrapper">
    <div class="header-actions">
      <div class="titulo-box">
        <h2>Parametros</h2>
        <p v-if="instanceId">Processo Workflow: <strong>#{{ instanceId }}</strong></p>
      </div>
    </div>

    <div class="form-container">
      <div v-if="loading" class="loading">Carregando...</div>

      <form v-else class="parametros-form" @submit.prevent="salvar">
        <div class="form-grid">
          <label class="field">
            <span>Prev. Cotacao</span>
            <input type="date" v-model="form.data_cotacao" />
          </label>

          <label class="field">
            <span>Prev. Entrega</span>
            <input type="date" v-model="form.data_recebimento" />
          </label>
        </div>

        <label class="field">
          <span>Endereco da entrega</span>
          <textarea
            v-model="form.endereco_da_entrega"
            rows="5"
            placeholder="Informe o endereco de entrega"
          ></textarea>
        </label>
      </form>
    </div>

    <div class="footer-actions">
      <button class="btn-fechar" type="button" @click="fechar">Voltar</button>
      <button class="btn-salvar" type="button" @click="salvar" :disabled="salvando || loading">
        {{ salvando ? 'Salvando...' : 'Salvar Parametros' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';

const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

const instanceId = ref(props.instanceId);
const loading = ref(true);
const salvando = ref(false);

const form = reactive({
  data_cotacao: '',
  data_recebimento: '',
  endereco_da_entrega: ''
});

onMounted(() => {
  carregar();
});

async function carregar() {
  if (!instanceId.value) {
    loading.value = false;
    return;
  }

  try {
    const url = `/backend/modulos/Parametros/ParametrosController.php?acao=carregar&id_processo=${instanceId.value}`;
    const res = await fetch(url);
    const json = await res.json();

    if (!res.ok || json.erro) {
      throw new Error(json.erro || 'Erro ao carregar parametros.');
    }

    form.data_cotacao = json.parametros?.data_cotacao || '';
    form.data_recebimento = json.parametros?.data_recebimento || '';
    form.endereco_da_entrega = json.parametros?.endereco_da_entrega || '';
  } catch (e) {
    alert('Erro: ' + e.message);
  } finally {
    loading.value = false;
  }
}

async function salvar() {
  if (!instanceId.value) return;

  salvando.value = true;
  try {
    const formData = new FormData();
    formData.append('acao', 'salvar');
    formData.append('id_processo', instanceId.value);
    formData.append('data_cotacao', form.data_cotacao || '');
    formData.append('data_recebimento', form.data_recebimento || '');
    formData.append('endereco_da_entrega', form.endereco_da_entrega || '');

    const res = await fetch('/backend/modulos/Parametros/ParametrosController.php', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (!res.ok || !json.sucesso) {
      throw new Error(json.erro || 'Erro ao salvar parametros.');
    }

    alert(json.msg || 'Parametros atualizados com sucesso!');
  } catch (e) {
    alert('Erro: ' + e.message);
  } finally {
    salvando.value = false;
  }
}

function fechar() {
  emit('fechar');
}
</script>

<style scoped>
.parametros-wrapper { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-actions { padding: 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.titulo-box h2 { margin: 0; color: #333; font-size: 20px; font-weight: 700; }
.titulo-box p { margin: 4px 0 0; color: #555; font-size: 13px; }
.form-container { flex: 1; padding: 24px; overflow-y: auto; }
.parametros-form { max-width: 820px; display: flex; flex-direction: column; gap: 18px; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(180px, 260px)); gap: 18px; }
.field { display: flex; flex-direction: column; gap: 6px; color: #333; font-size: 13px; font-weight: 700; }
.field input,
.field textarea {
  border: 1px solid #cfd4dc;
  border-radius: 4px;
  padding: 9px 10px;
  font: inherit;
  font-weight: 400;
  color: #222;
  background: #fff;
}
.field textarea { resize: vertical; min-height: 120px; line-height: 1.4; }
.field input:focus,
.field textarea:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.12); }
.loading { color: #555; font-size: 14px; }
.footer-actions { padding: 15px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; background: #f8f9fa; }
.btn-fechar { background: #6c757d; color: white; border: none; padding: 10px 18px; border-radius: 4px; cursor: pointer; font-weight: 700; }
.btn-salvar { background: #10b981; color: white; border: none; padding: 10px 18px; border-radius: 4px; cursor: pointer; font-weight: 700; }
.btn-salvar:hover:not(:disabled) { background: #059669; }
.btn-salvar:disabled { opacity: 0.65; cursor: not-allowed; }

@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
