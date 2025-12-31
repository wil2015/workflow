import { createApp } from 'vue';
import SolicitacoesList from './components/SolicitacoesList.vue';

// Procura a div #app-solicitacoes e monta o componente lá
const el = document.getElementById('app-solicitacoes');
if (el) {
    createApp(SolicitacoesList).mount('#app-solicitacoes');
}