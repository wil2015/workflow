import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [vue()],
    server: {
      host: true,
      port: 5173,
      strictPort: true, 
      hmr: {
        clientPort: Number(env.VITE_CLIENT_PORT) || 5173 
      },
      proxy: {
        // Proxy para o backend Mezzio (todas as rotas /backend/api/*)
        '/backend/api': {
          target: env.VITE_API_URL || 'http://backend_new:80',
          changeOrigin: true,
        },
        // Proxy legado (mantido para compatibilidade)
        '/backend': {
          target: env.VITE_API_URL || 'http://backend_legacy:80',
          changeOrigin: true,
        }
      }
    }
  };
});
