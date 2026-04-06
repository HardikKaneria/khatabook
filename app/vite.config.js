import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import tailwindcss from "tailwindcss";


export default defineConfig({
  plugins: [react()],
  root: './src',
  base: '/',

  css: {
    postcss: {
      plugins: [tailwindcss()],
    },
  },

  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    cors: true,
    hmr: {
      protocol: 'ws',
      host: 'localhost',
      port: 5173,
    },
  },

  build: {
    outDir: '../dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.jsx'),
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined;
          }

          if (id.includes('recharts')) {
            return 'charts';
          }

          if (id.includes('/@ant-design/icons/')) {
            return 'antd-icons';
          }

          if (id.includes('/dayjs/')) {
            return 'dayjs';
          }

          if (id.includes('/rc-') || id.includes('/@rc-component/')) {
            return 'antd-rc';
          }

          if (
            id.includes('/antd/es/layout') ||
            id.includes('/antd/es/grid') ||
            id.includes('/antd/es/menu') ||
            id.includes('/antd/es/select') ||
            id.includes('/antd/es/typography') ||
            id.includes('/antd/es/button')
          ) {
            return 'antd-shell';
          }

          if (
            id.includes('/antd/es/alert') ||
            id.includes('/antd/es/anchor') ||
            id.includes('/antd/es/card') ||
            id.includes('/antd/es/config-provider') ||
            id.includes('/antd/es/form') ||
            id.includes('/antd/es/input') ||
            id.includes('/antd/es/input-number') ||
            id.includes('/antd/es/switch') ||
            id.includes('/antd/es/upload') ||
            id.includes('/antd/es/space')
          ) {
            return 'antd-forms';
          }

          if (
            id.includes('/antd/') ||
            id.includes('/@ant-design/')
          ) {
            return 'antd';
          }

          if (id.includes('/react/') || id.includes('/react-dom/')) {
            return 'react-vendor';
          }

          return 'vendor';
        },
      },
    },
  },
});
