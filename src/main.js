import { createApp } from 'vue'
import App from './App.vue'

// Importe seu CSS global se tiver (opcional)
// import './style.css' 

// Cria a aplicação Vue usando o componente App.vue como raiz
const app = createApp(App)

// Monta o Vue na div <div id="app"> do HTML
app.mount('#app')