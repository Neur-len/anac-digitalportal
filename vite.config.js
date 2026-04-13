import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'

export default defineConfig({
  plugins: [
    symfonyPlugin({ stimulus: false }),
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    origin: 'http://localhost:5173', 
    watch: {
      usePolling: true,
    },
  },
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.js',
      },
    },
  },
})
