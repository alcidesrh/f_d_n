import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

/**
 * E2E contra el stack levantado (`make dev`): frontend y API detrás de Caddy.
 * Otra URL: `E2E_BASE_URL=https://... npm run test:e2e`.
 *
 * URL y cuenta de pruebas salen de `.env.e2e.local` en la raíz del repo
 * (`make e2e-usuario`); lo que ya venga en el entorno tiene prioridad.
 */
try {
  process.loadEnvFile(new URL('../.env.e2e.local', import.meta.url))
} catch {
  // Sin el archivo: solo las variables del entorno.
}

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://localhost',
    // Caddy firma `localhost` con su CA local: el navegador de prueba no la conoce.
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
