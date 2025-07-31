import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  root: './src',
  base: '/', // important for dev mode inside WordPress

  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    cors: true, // allow loading in WordPress

    // HMR fix for iframe/proxy environments
    hmr: {
      protocol: 'ws',
      host: 'localhost',
      port: 5173,
    },
  },

  build: {
    outDir: '../app/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.jsx'),
    },
  },
})
