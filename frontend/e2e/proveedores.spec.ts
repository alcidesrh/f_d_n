import process from 'node:process'
import { test, expect, type Page } from '@playwright/test'

/**
 * Forcon y Cybersource en la administración (ADR-047) contra el stack: los
 * enlaces de «Caja y facturación», la pantalla de cada proveedor, una URL
 * inválida que no se puede guardar y un cambio del Org ID que se deshace al
 * final. Necesita `config.proveedores` (la cuenta de `.env.e2e.local`). Con
 * `E2E_CAPTURAS=<carpeta>` guarda capturas.
 *   npm run test:e2e -- proveedores
 */
const USER = process.env.E2E_ADMIN_USER ?? 'admin'
const PASSWORD = process.env.E2E_ADMIN_PASSWORD
const CAPTURAS = process.env.E2E_CAPTURAS

async function captura(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${CAPTURAS}/${nombre}.png`, fullPage: true })
}

async function entrar(page: Page) {
  await page.goto('/login')
  await page.getByPlaceholder('Usuario').fill(USER)
  await page.getByPlaceholder('Contraseña').fill(PASSWORD!)
  await page.getByRole('button', { name: /Iniciar sesión|Aceptar/ }).click()
  // En dev, Vite compila en frío la primera vez: el login puede tardar.
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 30_000 })
}

test.describe('proveedores externos', () => {
  test.skip(!PASSWORD, 'Sin E2E_ADMIN_PASSWORD (make e2e-usuario)')

  test('enlaces en la administración y pantalla de Forcon', async ({ page }) => {
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(e.message))
    await entrar(page)

    await page.goto('/administracion')
    await expect(page.getByRole('link', { name: /Certificador FEL \(Forcon\)/ })).toBeVisible()
    await expect(page.getByRole('link', { name: /Pasarela de pago \(Cybersource\)/ })).toBeVisible()
    await captura(page, 'proveedores-administracion')

    await page.getByRole('link', { name: /Certificador FEL \(Forcon\)/ }).click()
    await expect(page).toHaveURL(/\/configuracion\/forcon$/)
    const url = page.getByPlaceholder('https://')
    await expect(url).toHaveValue(/^https:\/\//)
    await expect(page.getByText('Credenciales por empresa')).toBeVisible()
    await captura(page, 'proveedores-forcon')

    // Una URL que no es https no se puede guardar.
    const original = await url.inputValue()
    await url.fill('http://no-segura.test')
    await expect(page.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    await url.fill(original)
    await expect(page.getByRole('button', { name: 'Guardar' })).toBeDisabled()

    expect(errores).toEqual([])
  })

  test('Cybersource: cambiar el Org ID y deshacerlo', async ({ page }) => {
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(e.message))
    await entrar(page)

    await page.goto('/configuracion/cybersource')
    const org = page.getByPlaceholder('Vacío: sin huella del dispositivo')
    await expect(org).toBeVisible()
    const original = await org.inputValue()
    const guardar = page.getByRole('button', { name: 'Guardar' })

    await org.fill(`e2e-${Date.now()}`)
    await guardar.click()
    await expect(page.getByText(/Último cambio:/)).toBeVisible()
    await expect(guardar).toBeDisabled()
    await captura(page, 'proveedores-cybersource')

    await org.fill(original)
    await guardar.click()
    await expect(guardar).toBeDisabled()
    await page.reload()
    await expect(org).toHaveValue(original)

    expect(errores).toEqual([])
  })
})
