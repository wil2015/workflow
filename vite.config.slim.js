import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Vite config para uso com backend Slim 4
// Uso: npx vite --config vite.config.slim.js
export default defineConfig({
  plugins: [vue()],
  root: '.',
  publicDir: 'public',
  build: {
    outDir: 'dist'
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      // Proxy para o backend Slim 4 (mesmas rotas /backend/api/*)
      '/backend/api': {
        target: 'http://back_slim:80',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/backend\/api/, '/api')
      },
      // Arquivos BPMN estaticos
      '/bpmn': {
        target: 'http://back_slim:80',
        changeOrigin: true,
      }
    }
  }
});
