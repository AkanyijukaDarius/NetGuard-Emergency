import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  
  const hostIp = env.VITE_API_BASE_URL 
    ? env.VITE_API_BASE_URL.replace(/https?:\/\//, '').split(':')[0] 
    : '127.0.0.1';

  return {
    plugins: [
      laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true,
      }),
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
          },
        },
      }),
      tailwindcss(),
    ],

    server: {
      host: '0.0.0.0', 
      port: 5173,
      strictPort: true,
      cors: true,

      hmr: {
        host: hostIp,
        port: 5173,
        protocol: 'ws',
      },

      origin: `http://${hostIp}:5173`,
    },
  };
});