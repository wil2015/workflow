import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [vue()],
    server: {
      host: true,
      port: 5173, // Internamente é sempre 5173
      strictPort: true, 
      hmr: {
        // O navegador conecta na porta que definimos no docker-compose
        clientPort: Number(env.VITE_CLIENT_PORT) || 5173 
      },
      proxy: {
        '/backend': {
          // Cada frontend fala com seu respectivo backend
          target: env.VITE_API_URL || 'http://backend_legacy:80',
          changeOrigin: true,
        }
      }
    }
  };
});