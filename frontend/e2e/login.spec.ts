import { test, expect } from '@playwright/test'

test.describe('login', () => {
  test('sin sesión, cualquier ruta lleva al login', async ({ page }) => {
    await page.goto('/lista/bus')
    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByPlaceholder('Usuario')).toBeVisible()
  })

  test('valida los campos obligatorios', async ({ page }) => {
    await page.goto('/login')
    await page.getByRole('button', { name: 'Aceptar' }).click()
    await expect(page.locator('.formkit-message').first()).toBeVisible()
  })

  test('rechaza credenciales inválidas', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))
    await page.goto('/login')
    await page.getByPlaceholder('Usuario').fill('admin')
    await page.getByPlaceholder('Contraseña').fill('clave-incorrecta')
    await page.getByRole('button', { name: 'Aceptar' }).click()
    await expect(page.getByText('Usuario o contraseña incorrecto.')).toBeVisible()
    expect(errors).toEqual([])
  })
})
