import { test, expect, type Page } from '@playwright/test'

/**
 * Ninguna pantalla desborda en horizontal (docs/frontend/responsive.md): lo
 * que no cabe se reacomoda o scrollea dentro de su propio contenedor. Una
 * sesión por ancho y las pantallas, sin recargar, con el router de la app.
 *
 * Necesita la cuenta de pruebas de `.env.e2e.local` (`make e2e-usuario`):
 *   npm run test:e2e -- responsive
 */
const USER = process.env.E2E_ADMIN_USER ?? 'admin'
const PASSWORD = process.env.E2E_ADMIN_PASSWORD

test.skip(!PASSWORD, 'Falta E2E_ADMIN_PASSWORD (make e2e-usuario)')

const ANCHOS = [
  { nombre: 'teléfono angosto', width: 320, height: 640 },
  { nombre: 'teléfono', width: 390, height: 844 },
  { nombre: 'tableta', width: 768, height: 1024 },
  { nombre: 'escritorio', width: 1280, height: 800 },
]

const PANTALLAS = [
  '/',
  '/administracion',
  '/mi-cuenta',
  '/venta',
  '/boletos',
  '/salidas',
  '/salidas/calendario',
  '/salidas/programar',
  '/salidas/chequeo',
  '/encomiendas',
  '/encomiendas/registrar',
  '/encomiendas/salida',
  '/encomiendas/entregar',
  '/encomiendas/pendientes-envio',
  '/rutas',
  '/tarifas/mapa',
  '/seguimiento',
  '/agencias/depositos',
  '/agencias/saldos',
  '/reporte/cuadre-venta-boletos',
  '/reporte/encomienda-cuadre',
  '/venta-en-linea',
  '/autorizaciones',
  '/ayuda',
  '/lista/bus',
  '/lista/usuario',
  '/form/bus',
  '/configuracion/roles',
  '/configuracion/accesos',
  '/configuracion/menus',
  '/configuracion/forcon',
  '/configuracion/correo',
  '/configuracion/cybersource',
  '/configuracion/bitacora-diaria',
  '/auditoria/bitacora-diaria',
]

async function entrar(page: Page) {
  await page.goto('/login')
  await page.getByPlaceholder('Usuario').fill(USER)
  await page.getByPlaceholder('Contraseña').fill(PASSWORD!)
  await page.getByRole('button', { name: /Iniciar sesión|Aceptar/ }).click()
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 30_000 })
  await page.locator('.main').waitFor()
}

/** Navega con el router de la app (sin esperar su promesa: la resuelve la pantalla) y deja que asiente. */
async function abrir(page: Page, ruta: string) {
  await page.evaluate((destino) => {
    const app = document.querySelector<HTMLElement & { __vue_app__: { config: { globalProperties: { $router: { push(r: string): unknown } } } } }>('#app')!
    void app.__vue_app__.config.globalProperties.$router.push(destino)
  }, ruta)
  // En dev, Vite compila en frío cada pantalla la primera vez.
  await expect.poll(() => new URL(page.url()).pathname, { timeout: 30_000 }).toBe(ruta)
  await page.waitForTimeout(800)
}

/** Lo que se sale de la página: cuánto y, para el mensaje, quién. */
function desborde(page: Page) {
  return page.evaluate(() => {
    const main = document.querySelector('.main') as HTMLElement
    const borde = main.getBoundingClientRect().right
    const culpables = [...main.querySelectorAll<HTMLElement>('*')]
      .filter((el) => el.getBoundingClientRect().right > borde + 1 && el.getBoundingClientRect().width > 0)
      .filter((el) => {
        for (let p = el.parentElement; p && p !== main; p = p.parentElement) {
          if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(p).overflowX)) return false
        }
        return true
      })
      .slice(0, 3)
      .map((el) => `${el.tagName.toLowerCase()}.${String(el.className).split(/\s+/).slice(0, 2).join('.')}`)
    return { px: Math.max(document.documentElement.scrollWidth - innerWidth, main.scrollWidth - main.clientWidth), culpables }
  })
}

/** Las pantallas que se salen, con cuánto y quién. */
async function pantallasQueDesbordan(page: Page) {
  const fallas: string[] = []
  for (const ruta of PANTALLAS) {
    await abrir(page, ruta)
    const { px, culpables } = await desborde(page)
    if (px > 0) fallas.push(`${ruta}: +${px}px (${culpables.join(', ')})`)
  }
  return fallas
}

for (const ancho of ANCHOS) {
  test(`sin desborde horizontal en ${ancho.nombre} (${ancho.width}px)`, async ({ page }) => {
    test.setTimeout(240_000)
    await page.setViewportSize({ width: ancho.width, height: ancho.height })
    await entrar(page)
    expect(await pantallasQueDesbordan(page)).toEqual([])
  })
}
