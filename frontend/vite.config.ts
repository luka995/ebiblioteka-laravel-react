import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    allowedHosts: ['dashboard.ebiblioteka.rs', '.trycloudflare.com', 'localhost'],
  },
})
