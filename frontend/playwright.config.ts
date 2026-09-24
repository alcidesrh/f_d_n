import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

/**
 * E2E contra el stack levantado (`make dev`): frontend y API detrás de Caddy.
 * Otra URL: `E2E_BASE_URL=https://... npm run test:e2e`.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost',
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
