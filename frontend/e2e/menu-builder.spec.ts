import { test, expect, type Locator, type Page } from '@playwright/test'

/**
 * Constructor de menús (ADR-018) de punta a punta contra el stack levantado:
 * ítems → menú → árbol con drag & drop (GSAP Draggable) → área → shell.
 *
 * Crea datos con prefijo `E2E` y los borra al final. Necesita un usuario con
 * `ROLE_ADMIN`/`ROLE_SUPER_ADMIN`:
 *   E2E_ADMIN_USER=admin E2E_ADMIN_PASSWORD='…' npm run test:e2e -- menu-builder
 */
const USER = process.env.E2E_ADMIN_USER ?? 'admin'
const PASSWORD = process.env.E2E_ADMIN_PASSWORD
const ROLE = process.env.E2E_ADMIN_ROLE ?? 'ROLE_SUPER_ADMIN'

/** Geometría de `MenuOutline.vue`. */
const ROW_H = 52
const INDENT = 32
const PADDING = 8

const ITEMS = [
  { nombre: 'E2E Inicio', route: 'dashboard', icon: 'home' },
  { nombre: 'E2E Config', route: 'entity-config', icon: 'settings' },
  { nombre: 'E2E Migración', route: 'migracion', icon: 'database' },
]
const MENU = 'E2E Menú'

interface SavedNode {
  label: string
  children: SavedNode[]
}
const shape = (nodes: SavedNode[]): unknown[] =>
  nodes.map((node) => (node.children.length ? { [node.label]: shape(node.children) } : node.label))

async function box(locator: Locator) {
  const rect = await locator.boundingBox()
  if (!rect) throw new Error('elemento sin caja')
  return rect
}

/** Arrastra `handle` moviendo el puntero en pasos (Draggable necesita pointermove). */
async function dragBy(page: Page, handle: Locator, dx: number, dy: number) {
  await handle.scrollIntoViewIfNeeded()
  const start = await box(handle)
  const x = start.x + start.width / 2
  const y = start.y + start.height / 2
  await page.mouse.move(x, y)
  await page.mouse.down()
  await page.mouse.move(x + 4, y + 4, { steps: 3 })
  await page.mouse.move(x + dx, y + dy, { steps: 20 })
  await page.mouse.up()
}

/** Suelta un ítem de la paleta en la fila `index` con sangría `depth` del esquema. */
async function dropFromPalette(page: Page, nombre: string, index: number, depth: number) {
  const chip = page.locator('.palette-item', { hasText: nombre })
  const handle = chip.locator('[data-drag-handle]')
  await page.locator('.mo').scrollIntoViewIfNeeded()
  const [chipBox, handleBox, root] = await Promise.all([
    box(chip),
    box(handle),
    box(page.locator('.mo')),
  ])
  // El chip se mueve con el puntero: su esquina superior izquierda debe caer en el destino.
  const targetX = root.x + PADDING + depth * INDENT + 2
  const targetY = root.y + PADDING + index * ROW_H + 2
  const grabX = handleBox.x + handleBox.width / 2 - chipBox.x
  const grabY = handleBox.y + handleBox.height / 2 - chipBox.y
  await dragBy(
    page,
    handle,
    targetX + grabX - (chipBox.x + grabX),
    targetY + grabY - (chipBox.y + grabY),
  )
  await expect(page.locator('.mo-row', { hasText: nombre })).toBeVisible()
}

const outlineRow = (page: Page, nombre: string) =>
  page.locator('.mo-row', { hasText: nombre }).locator('[data-drag-handle]')

async function saveTree(page: Page): Promise<SavedNode[]> {
  const response = page.waitForResponse(
    (r) => r.url().includes('/tree') && r.request().method() === 'PUT',
  )
  await page.getByRole('button', { name: 'Guardar árbol' }).click()
  const saved = await response
  expect(saved.status()).toBe(200)
  return (await saved.json()) as SavedNode[]
}

test.describe('constructor de menús', () => {
  // eslint-disable-next-line playwright/no-skipped-test -- sin credenciales no hay contra quién correr
  test.skip(!PASSWORD, 'definir E2E_ADMIN_PASSWORD')
  test.setTimeout(120_000)

  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.getByPlaceholder('Usuario').fill(USER)
    await page.getByPlaceholder('Contraseña').fill(PASSWORD!)
    await page.getByRole('button', { name: 'Aceptar' }).click()
    await expect(page).not.toHaveURL(/\/login$/)
  })

  test('ítems, árbol con drag & drop, área y navegación del shell', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))

    // Restos de una corrida anterior interrumpida.
    await cleanup(page)
    await page.goto('/configuracion/menus')
    await expect(page.getByRole('heading', { name: 'Ítems navegables' })).toBeVisible()

    try {
      // 1. Ítems navegables.
      for (const item of ITEMS) {
        await page.getByRole('button').getByText('Nuevo ítem', { exact: true }).click()
        const dialog = page.getByRole('dialog')
        await dialog.getByLabel('Texto').fill(item.nombre)
        await dialog.locator('label', { hasText: 'Ruta' }).locator('.p-select').click()
        await page.getByRole('option', { name: new RegExp(`^${item.route} —`) }).click()
        await dialog.getByPlaceholder('Buscar por nombre, tag o categoría…').fill(item.icon)
        await dialog.locator(`[data-icon="${item.icon}"]`).click()
        await dialog.getByRole('button', { name: 'Guardar' }).click()
        await expect(dialog).toBeHidden()
        await expect(page.locator('.palette-item', { hasText: item.nombre })).toBeVisible()
      }

      // 2. Menú con el rol del usuario.
      await page.getByRole('button', { name: 'Nuevo menú' }).click()
      await page.getByLabel('Nombre').fill(MENU)
      await page.locator('.p-multiselect').click()
      await page.locator('.p-multiselect-overlay').getByRole('searchbox').fill(ROLE)
      await page.getByRole('option', { name: ROLE, exact: true }).click()
      await page.keyboard.press('Escape')
      await page.getByRole('button', { name: 'Crear' }).click()
      await expect(page.locator('.mo')).toBeVisible()

      // 3. Desde la paleta: Inicio, Config y Migración como hija de Config.
      await dropFromPalette(page, 'E2E Inicio', 0, 0)
      await dropFromPalette(page, 'E2E Config', 1, 0)
      await dropFromPalette(page, 'E2E Migración', 2, 1)
      await page.screenshot({ path: '/tmp/e2e-menu-1-paleta.png', fullPage: true })
      expect(shape(await saveTree(page))).toEqual([
        'E2E Inicio',
        { 'E2E Config': ['E2E Migración'] },
      ])

      // 4. Reordenar: el bloque Config (con su hija) sube por encima de Inicio.
      await dragBy(page, outlineRow(page, 'E2E Config'), 0, -ROW_H)
      // 5. Cambiar de nivel: Migración pasa a raíz (arrastre a la izquierda).
      await dragBy(page, outlineRow(page, 'E2E Migración'), -INDENT, 0)
      // 6. Anidar Inicio bajo Migración (arrastre a la derecha).
      await dragBy(page, outlineRow(page, 'E2E Inicio'), INDENT, 0)
      await page.screenshot({ path: '/tmp/e2e-menu-2-reordenado.png', fullPage: true })
      // Config, Migración (raíz), Inicio (hijo de Migración)
      expect(shape(await saveTree(page))).toEqual([
        'E2E Config',
        { 'E2E Migración': ['E2E Inicio'] },
      ])

      // 7. Soltar fuera quita el bloque; se descarta sin guardar recargando el menú.
      await dragBy(page, outlineRow(page, 'E2E Config'), 0, -400)
      await expect(page.locator('.mo-row', { hasText: 'E2E Config' })).toHaveCount(0)
      await expect(page.locator('.palette-item', { hasText: 'E2E Config' })).toBeVisible()
      await expect(page.getByText('Árbol sin guardar')).toBeVisible()
      await dropFromPalette(page, 'E2E Config', 0, 0)

      // 8. Colocar el menú en la barra lateral izquierda.
      await page.getByRole('tab', { name: 'Áreas de la UI' }).click()
      const area = page.locator('.card', { hasText: 'Barra lateral izquierda' })
      await area.locator('.p-select').click()
      await page.getByRole('option', { name: MENU }).click()
      const layout = page.waitForResponse(
        (r) => r.url().includes('/menu-layout') && r.request().method() === 'PUT',
      )
      await page.getByRole('button', { name: 'Guardar distribución' }).click()
      expect((await layout).status()).toBe(200)

      // 9. El shell muestra el menú en la barra izquierda y navega.
      const sidebar = page.locator('aside.sidebar.left')
      await expect(sidebar.getByText('E2E Config')).toBeVisible()
      await expect(sidebar.getByText('E2E Migración')).toBeVisible()
      await sidebar
        .locator('.menu-item', { hasText: 'E2E Migración' })
        .locator('.nav-toggle')
        .first()
        .click()
      await expect(sidebar.getByText('E2E Inicio')).toBeVisible()
      await page.screenshot({ path: '/tmp/e2e-menu-3-shell.png', fullPage: true })
      await sidebar.getByRole('link', { name: 'E2E Migración' }).click()
      await expect(page).toHaveURL(/\/migracion$/)

      // 10. Submenú: se pliega con transición (desaparece al terminar).
      await sidebar
        .locator('.menu-item', { hasText: 'E2E Migración' })
        .locator('.nav-toggle')
        .first()
        .click()
      await expect(sidebar.getByText('E2E Inicio')).toBeHidden()

      // 11. Modo mini: al pasar el mouse el enlace se despliega al ancho abierto con su
      // texto en la misma posición que en modo abierto, se mantiene y revierte al salir.
      const link = sidebar.locator('.menu-link', { hasText: 'E2E Config' })
      const text = link.locator('.menu-text')
      const width = async () => Math.round((await box(link)).width)
      const openX = Math.round((await box(text)).x)
      await sidebar.locator('.toggle-sidebar').click()
      await expect(text).toHaveCSS('opacity', '0')
      await link.hover()
      await expect.poll(width).toBe(250)
      await expect(text).toHaveCSS('opacity', '1')
      expect(Math.round((await box(text)).x)).toBe(openX)
      await page.mouse.move(640, 400)
      await expect.poll(width).toBe(70)
      await expect(text).toHaveCSS('opacity', '0')
      await sidebar.locator('.toggle-sidebar').click()

      expect(errors).toEqual([])
    } finally {
      await cleanup(page)
    }
  })
})

/** Borra por GraphQL lo que creó el test (el menú arrastra sus colocaciones y su árbol). */
async function cleanup(page: Page) {
  await page.evaluate(
    async ({ menu, items }) => {
      const token = JSON.parse(localStorage.getItem('session') ?? '{}').token
      const gql = (query: string, variables = {}) =>
        fetch('/graphql', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
          body: JSON.stringify({ query, variables }),
        }).then((r) => r.json())
      const data = (await gql('{ menus { id nombre } menuItems { id nombre } }')).data
      for (const m of data.menus.filter((x: { nombre: string }) => x.nombre === menu))
        await gql('mutation($i: deleteMenuInput!) { deleteMenu(input: $i) { menu { id } } }', {
          i: { id: m.id },
        })
      for (const it of data.menuItems.filter((x: { nombre: string }) => items.includes(x.nombre)))
        await gql(
          'mutation($i: deleteMenuItemInput!) { deleteMenuItem(input: $i) { menuItem { id } } }',
          { i: { id: it.id } },
        )
    },
    { menu: MENU, items: ITEMS.map((item) => item.nombre) },
  )
}
