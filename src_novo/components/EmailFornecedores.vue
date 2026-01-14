<template>
  <div class="email-wrapper">
    <div class="header-email">
      <h2>Enviar Para Fornecedores</h2>
      <p>Marque os e-mails desejados. Você pode adicionar múltiplos e-mails para o mesmo fornecedor.</p>
    </div>

    <div class="conteudo-lista">
      <div v-if="loading" class="msg-loading">
        <div class="spinner"></div>
        Sincronizando contatos com ERP Senior...
      </div>
      
      <div v-else-if="listaFornecedores.length === 0" class="msg-vazio">
        Nenhum fornecedor vinculado a este processo.
      </div>

      <div v-else class="lista-scroll">
        <div v-for="forn in listaFornecedores" :key="forn.id_participante" class="card-fornecedor">
          <div class="card-header">
            <strong>{{ forn.nome }}</strong>
            <span class="cod-senior">Cód: {{ forn.id_fornecedor_senior }}</span>
          </div>
          
          <div class="card-emails">
            <label 
                v-for="(emailObj, idx) in forn.emails" 
                :key="idx" 
                class="item-email"
                :class="{ 'selecionado': emailObj.checked }"
            >
              <input type="checkbox" v-model="emailObj.checked">
              <span class="email-texto">{{ emailObj.email }}</span>
            </label>

            <div class="add-email-row">
                <input 
                    type="text" 
                    v-model="forn.novoEmailTemp" 
                    placeholder="Adicionar outro e-mail..." 
                    class="input-novo"
                    @keyup.enter="adicionarEmail(forn)"
                >
                <button 
                    class="btn-add" 
                    @click="adicionarEmail(forn)" 
                    :disabled="salvandoLocal === forn.id_participante"
                    title="Adicionar"
                >
                    {{ salvandoLocal === forn.id_participante ? '...' : '+' }}
                </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="footer-email">
      <div class="info-selecao">
          {{ totalSelecionados }} e-mails selecionados para envio.
      </div>
      <div class="botoes">
          <button class="btn-voltar" @click="$emit('fechar')">Cancelar</button>
          <button class="btn-salvar" @click="salvar" :disabled="loading || salvando">
            {{ salvando ? 'Salvando...' : 'Confirmar Envio' }}
          </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const props = defineProps({ instanceId: String });
const emit = defineEmits(['fechar']);

const loading = ref(true);
const salvando = ref(false);
const salvandoLocal = ref(null);
const listaFornecedores = ref([]);

const API_URL = '/backend/modulos/EmailFornecedores/EmailFornecedoresController.php';

// Computed para mostrar quantos estão marcados no rodapé
const totalSelecionados = computed(() => {
    let qtd = 0;
    listaFornecedores.value.forEach(f => {
        f.emails.forEach(e => { if(e.checked) qtd++; });
    });
    return qtd;
});

onMounted(() => {
    carregarEmails();
});

async function carregarEmails() {
    try {
        const req = await fetch(`${API_URL}?acao=listar&instance_id=${props.instanceId}`);
        const res = await req.json();
        
        if (res.erro) throw new Error(res.erro);
        
        // Inicializa o campo temporário para cada fornecedor
        listaFornecedores.value = res.map(f => ({ ...f, novoEmailTemp: '' }));
        
    } catch (e) {
        alert("Erro ao carregar: " + e.message);
    } finally {
        loading.value = false;
    }
}

async function adicionarEmail(forn) {
    if (!forn.novoEmailTemp || !forn.novoEmailTemp.includes('@')) {
        alert("Digite um e-mail válido.");
        return;
    }

    // Verifica se já existe visualmente para evitar duplicação na tela
    const existe = forn.emails.find(e => e.email.toLowerCase() === forn.novoEmailTemp.toLowerCase());
    if (existe) {
        existe.checked = true; // Se já existe, só marca
        forn.novoEmailTemp = '';
        return;
    }

    salvandoLocal.value = forn.id_participante;

    try {
        const payload = {
            acao: 'add_email',
            id_fornecedor_senior: forn.id_fornecedor_senior,
            email: forn.novoEmailTemp
        };

        const req = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const res = await req.json();

        if (res.sucesso) {
            // Adiciona na lista visualmente e marca como checado
            forn.emails.push({ 
                email: forn.novoEmailTemp.toLowerCase(), 
                checked: true 
            });
            forn.novoEmailTemp = ''; // Limpa o campo para poder digitar outro
        } else {
            throw new Error(res.erro || "Erro ao adicionar.");
        }
    } catch (e) {
        alert(e.message);
    } finally {
        salvandoLocal.value = null;
    }
}

async function salvar() {
    const selecionados = [];
    
    listaFornecedores.value.forEach(forn => {
        forn.emails.forEach(emailObj => {
            if (emailObj.checked) {
                selecionados.push({
                    id_participante: forn.id_participante,
                    id_fornecedor_senior: forn.id_fornecedor_senior,
                    email: emailObj.email
                });
            }
        });
    });

    if (selecionados.length === 0) {
        if(!confirm("Nenhum e-mail selecionado. Deseja continuar sem enviar nada?")) return;
    }

    salvando.value = true;

    try {
        const req = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'salvar',
                instance_id: props.instanceId,
                emails_selecionados: selecionados
            })
        });
        
        const res = await req.json();
        
        if (res.sucesso) {
            alert(res.msg);
            emit('fechar');
        } else {
            throw new Error(res.erro);
        }
    } catch (e) {
        alert("Erro: " + e.message);
    } finally {
        salvando.value = false;
    }
}
</script>

<style scoped>
.email-wrapper { height: 100%; display: flex; flex-direction: column; background: #fff; font-family: 'Segoe UI', sans-serif; }
.header-email { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
.header-email h2 { margin: 0; font-size: 20px; color: #333; }
.header-email p { margin: 5px 0 0 0; color: #666; font-size: 13px; }

.conteudo-lista { flex: 1; overflow-y: auto; padding: 20px; background: #f0f2f5; }

.card-fornecedor { background: #fff; padding: 15px; margin-bottom: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #007bff; }
.card-header { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
.cod-senior { font-size: 12px; color: #666; background: #eee; padding: 2px 6px; border-radius: 4px; }

.item-email { display: flex; align-items: center; padding: 8px; cursor: pointer; transition: 0.2s; border-radius: 4px; margin-bottom: 2px; }
.item-email:hover { background: #f8f9fa; }
.item-email.selecionado { background: #e8f0fe; color: #1967d2; font-weight: 500; }
.item-email input { transform: scale(1.3); margin-right: 10px; }
.email-texto { font-size: 14px; }

.add-email-row { display: flex; gap: 5px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee; }
.input-novo { flex: 1; padding: 6px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; }
.input-novo:focus { border-color: #80bdff; outline: none; }

.btn-add { background: #28a745; color: white; border: none; width: 32px; border-radius: 4px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.btn-add:hover:not(:disabled) { background: #218838; }
.btn-add:disabled { opacity: 0.6; cursor: wait; }

.footer-email { padding: 15px 20px; background: #fff; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.info-selecao { color: #666; font-size: 14px; font-weight: 600; }
.botoes { display: flex; gap: 10px; }
.btn-salvar { background: #007bff; color: white; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-salvar:hover:not(:disabled) { background: #0056b3; }
.btn-voltar { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }

.msg-loading, .msg-vazio { text-align: center; padding: 40px; color: #666; font-size: 15px; }
.spinner { border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 0 auto 10px auto; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>