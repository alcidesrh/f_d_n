import { test, expect } from '@playwright/test'

/**
 * Navegación por capacidad (ADR-049) contra el stack levantado: al entrar
 * quien configura la aplicación, el shell publica las rutas con lo que exige
 * cada pantalla, el backend no conoce ninguna capacidad mal escrita y manda
 * los menús y la administración ya podados.
 *
 * Necesita una cuenta con `config.editar` (la de pruebas de `.env.e2e.local`,
 * `make e2e-usuario`):
 *   npm run test:e2e -- navegacion
 */
const USER = process.env.E2E_ADMIN_USER ?? 'admin'
const PASSWORD = process.env.E2E_ADMIN_PASSWORD

test.skip(!PASSWORD, 'Falta E2E_ADMIN_PASSWORD (make e2e-usuario)')

test('al entrar se sincronizan las rutas y menús y administración llegan del backend', async ({ page }) => {
  const sincronizacion = page.waitForResponse((r) => r.url().endsWith('/api/vue-routes/sync') && r.request().method() === 'POST', { timeout: 30_000 })
  const menus = page.waitForResponse((r) => r.url().endsWith('/api/me/menus'), { timeout: 30_000 })

  await page.goto('/login')
  await page.getByPlaceholder('Usuario').fill(USER)
  await page.getByPlaceholder('Contraseña').fill(PASSWORD!)
  await page.getByRole('button', { name: /Iniciar sesión|Aceptar/ }).click()
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 30_000 })

  const sync = await sincronizacion
  expect(sync.ok()).toBe(true)
  const respuesta = (await sync.json()) as { total: number; capacidadesDesconocidas: string[] }
  expect(respuesta.capacidadesDesconocidas, 'capacidades de meta.capacidad que no están en el catálogo').toEqual([])
  expect(respuesta.total).toBeGreaterThan(0)

  // Los menús se piden después de sincronizar: ya con lo que exige cada ruta.
  const areas = (await (await menus).json()) as Record<string, { items: unknown[] }[]>
  expect(Object.values(areas).flat().every((m) => m.items.length > 0), 'un menú sin ítems no se manda').toBe(true)

  await page.goto('/administracion')
  await expect(page.locator('.caja').first()).toBeVisible({ timeout: 15_000 })
})
