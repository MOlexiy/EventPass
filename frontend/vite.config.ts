import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// The dev server proxies API calls to Laravel, so the SPA and the API share
// one origin: Sanctum session cookies and CSRF just work, no CORS needed.
// In Docker the target is the "api" service.
const proxy = {
  target: process.env.API_PROXY_TARGET ?? 'http://localhost:8000',
  changeOrigin: false,
}

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': proxy,
      '/sanctum': proxy,
      '/auth/google': proxy,
    },
  },
})
