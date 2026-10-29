import process from 'node:process'
import { test, expect, type Page } from '@playwright/test'

/**
 * Mensajes predeterminados (ADR-045) de punta a punta contra el stack:
 * crear uno con horario desde la pantalla, probarlo, desactivarlo y
 * eliminarlo. Necesita `chat.predeterminados`: la cuenta de pruebas de
 * `.env.e2e.local` (`make e2e-usuario`). Crea datos con prefijo `E2E` y los
 * borra al final. Con `E2E_CAPTURAS=<carpeta>` guarda capturas.
 *   npm run test:e2e -- mensajes-predeterminados
 */
const USER = process.env.E2E_ADMIN_USER ?? 'admin'
const PASSWORD = process.env.E2E_ADMIN_PASSWORD
const CAPTURAS = process.env.E2E_CAPTURAS
const NOMBRE = `E2E Buenos días ${Date.now()}`

async function captura(page: Page, nombre: string) {
  if (!CAPTURAS) return
  // Que terminen la animación del diálogo y los avisos.
  await page.waitForTimeout(500)
  await page.screenshot({ path: `${CAPTURAS}/${nombre}.png`, fullPage: false })
}

async function entrar(page: Page) {
  await page.goto('/login')
  await page.getByPlaceholder('Usuario').fill(USER)
  await page.getByPlaceholder('Contraseña').fill(PASSWORD!)
  await page.getByRole('button', { name: /Iniciar sesión|Aceptar/ }).click()
  await expect(page).not.toHaveURL(/\/login$/)
}

test.describe('mensajes predeterminados', () => {
  test.skip(!PASSWORD, 'Sin E2E_ADMIN_PASSWORD (make e2e-usuario)')

  test('crear, probar, desactivar y eliminar un mensaje con horario', async ({ page }) => {
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(e.message))
    await entrar(page)

    await page.goto('/configuracion/mensajes-predeterminados')
    await expect(page.getByRole('button', { name: 'Nuevo mensaje' })).toBeEnabled()
    await page.getByRole('button', { name: 'Nuevo mensaje' }).click()

    const dialogo = page.getByRole('dialog', { name: 'Nuevo mensaje predeterminado' })
    await expect(dialogo).toBeVisible()

    // Sin nada: dice lo que falta y no guarda.
    await dialogo.getByRole('button', { name: 'Crear' }).click()
    await expect(dialogo.getByText('Póngale un nombre.')).toBeVisible()
    await expect(dialogo.getByText('Elija a quién va dirigido.')).toBeVisible()

    // 1) Nombre.
    await dialogo.getByPlaceholder('p. ej. Salidas Guatemala – Flores').fill(NOMBRE)
    // 2) Audiencia: el mismo usuario de pruebas.
    await dialogo.locator('label', { hasText: 'Usuarios' }).locator('.p-multiselect').click()
    await page.locator('.p-multiselect-overlay').getByRole('searchbox').fill(USER)
    await page.locator('.p-multiselect-overlay').getByRole('option', { name: new RegExp(`^${USER}:`) }).first().click()
    await page.keyboard.press('Escape')
    // 3) Contenido: sin predeterminado, a una hora, de lunes a viernes.
    await dialogo.locator('input[type="time"]').fill('07:30')
    for (const d of ['Lun', 'Mar', 'Mié', 'Jue', 'Vie']) await dialogo.getByRole('button', { name: d, exact: true }).click()
    await dialogo.getByPlaceholder('Lo que quiere decir').fill('Buenos días, equipo. Revisen las salidas de hoy.')
    await captura(page, 'dialogo')

    await dialogo.getByRole('button', { name: 'Crear' }).click()
    await expect(dialogo).toBeHidden()

    const tarjeta = page.locator('.tarjeta', { hasText: NOMBRE })
    await expect(tarjeta).toBeVisible()
    await expect(tarjeta).toContainText('Lun a vie a las 07:30')
    await expect(tarjeta).toContainText('1 usuario · 1 destinatario')
    await captura(page, 'lista')

    // Probar: le llega solo a quien prueba.
    await tarjeta.getByRole('button', { name: 'Probar' }).click()
    await expect(page.getByText('Le llegó a «Avisos del sistema» en el chat.')).toBeVisible()

    // Desactivar.
    await tarjeta.getByRole('switch').click()
    await expect(tarjeta).toHaveClass(/tarjeta--inactiva/)

    // Editar: el formulario trae lo guardado.
    await tarjeta.getByRole('button', { name: 'Editar' }).click()
    const edicion = page.getByRole('dialog', { name: 'Editar mensaje predeterminado' })
    await expect(edicion.getByPlaceholder('p. ej. Salidas Guatemala – Flores')).toHaveValue(NOMBRE)
    await expect(edicion.locator('input[type="time"]')).toHaveValue('07:30')
    await edicion.getByRole('button', { name: 'Cancelar' }).click()

    // En el teléfono, la lista y el formulario caben sin desplazar a lo ancho.
    await page.setViewportSize({ width: 375, height: 812 })
    await expect(tarjeta).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await captura(page, 'movil-lista')
    await tarjeta.getByRole('button', { name: 'Editar' }).click()
    await expect(edicion).toBeVisible()
    await captura(page, 'movil-dialogo')
    await edicion.getByRole('button', { name: 'Cancelar' }).click()
    await page.setViewportSize({ width: 1280, height: 800 })

    // Eliminar.
    await tarjeta.getByRole('button', { name: 'Eliminar' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Eliminar' }).click()
    await expect(tarjeta).toHaveCount(0)

    expect(errores).toEqual([])
  })
})
