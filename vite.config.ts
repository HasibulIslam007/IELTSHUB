import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({base:'/build/',plugins:[react(),tailwindcss()],build:{outDir:'backend/public/build',emptyOutDir:true,manifest:'manifest.json',rollupOptions:{input:'frontend/src/main.tsx'}},server:{host:'127.0.0.1',port:5173,strictPort:true,cors:{origin:'http://127.0.0.1:8000'}}});
