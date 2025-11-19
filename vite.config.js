import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    host: true, // Permite acesso externo ao container
    proxy: {
      // O segredo está aqui:
      '/backend': {
        // NÃO USE 'localhost'
        // NÃO USE a porta '8080'
        // USE o nome do serviço no docker-compose ('backend') e a porta interna ('80')
        target: 'http://backend:80', 
        changeOrigin: true,
        secure: false,
      },
      // Se tiver a rota antiga
      '/salvar_fluxo.php': {
        target: 'http://backend:80',
        changeOrigin: true,
        secure: false,
      }
    }
  }
});