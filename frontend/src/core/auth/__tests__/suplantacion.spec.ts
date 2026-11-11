import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { createApp } from 'vue'
import type { Router } from 'vue-router'

const llamadas: Array<{ path: string; method?: string; body?: unknown }> = []
let respuesta: unknown = null
let falla: Error | null = null

vi.mock('@/core/http', () => ({
  request: async (path: string, options: { method?: string; body?: unknown } = {}) => {
    llamadas.push({ path, method: options.method, body: options.body })
    if (falla) throw falla
    return respuesta
  },
}))

const { alcanceSesion, almacenPorUsuario } = await import('../alcanceSesion')
const { cambiarRecarga, useSessionStore } = await import('../session')
const { cambiarRecargaAlVencer, handleUnauthorized, usarRouter } = await import('../unauthorized')

usarRouter({
  currentRoute: { value: { name: 'inicio', matched: [], fullPath: '/' } },
  options: { history: { location: '/' } },
  push: async () => undefined,
} as unknown as Router)

const recargas: string[] = []

describe('suplantación en la sesión', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
    llamadas.length = 0
    recargas.length = 0
    falla = null
    const pinia = createPinia()
    pinia.use(alcanceSesion)
    pinia.use(createPersistedState({ storage: almacenPorUsuario }))
    createApp({}).use(pinia)
    setActivePinia(pinia)
    cambiarRecarga((d) => recargas.push(d))
    cambiarRecargaAlVencer((d) => recargas.push(`vencida:${d}`))
    const s = useSessionStore()
    s.user = 'admin'
    s.token = 'fdn_admin'
  })

  it('entra como el usuario guardando la sesión propia y recarga la app', async () => {
    respuesta = { username: 'cajero', token: 'fdn_suplantado' }
    const s = useSessionStore()
    await s.suplantar(7, 'Revisar su caja')

    expect(llamadas.at(-1)).toEqual({
      path: '/usuarios/7/suplantar',
      method: 'POST',
      body: { motivo: 'Revisar su caja' },
    })
    expect(s.user).toBe('cajero')
    expect(s.token).toBe('fdn_suplantado')
    expect(s.original).toEqual({ user: 'admin', token: 'fdn_admin' })
    expect(s.suplantando).toBe(true)
    expect(recargas).toEqual(['/'])
  })

  it('si el backend no deja, la sesión no cambia', async () => {
    falla = new Error('No se puede entrar como otro administrador del sistema.')
    const s = useSessionStore()
    await expect(s.suplantar(1)).rejects.toThrow('administrador del sistema')
    expect(s.user).toBe('admin')
    expect(s.original).toBeNull()
    expect(recargas).toEqual([])
  })

  it('vuelve a la cuenta propia revocando el token de suplantación', async () => {
    respuesta = { username: 'cajero', token: 'fdn_suplantado' }
    const s = useSessionStore()
    await s.suplantar(7, 'Revisar su caja')
    respuesta = { salida: true }
    await s.terminarSuplantacion()

    expect(llamadas.at(-1)).toMatchObject({ path: '/suplantacion/salir', method: 'POST' })
    expect(s.user).toBe('admin')
    expect(s.token).toBe('fdn_admin')
    expect(s.original).toBeNull()
    expect(recargas).toEqual(['/', '/'])
  })

  it('un 401 suplantando vuelve a la cuenta propia en vez de ir al login', async () => {
    respuesta = { username: 'cajero', token: 'fdn_suplantado' }
    const s = useSessionStore()
    await s.suplantar(7, 'Revisar su caja')
    handleUnauthorized()

    expect(s.user).toBe('admin')
    expect(s.token).toBe('fdn_admin')
    expect(recargas.at(-1)).toBe('vencida:/')
  })

  it('cerrar sesión suplantando cierra la suplantación y la sesión propia', async () => {
    respuesta = { username: 'cajero', token: 'fdn_suplantado' }
    const s = useSessionStore()
    await s.suplantar(7, 'Revisar su caja')
    await s.logout()

    expect(llamadas.map((l) => l.path).slice(-2)).toEqual(['/suplantacion/salir', '/logout'])
    expect(s.user).toBeNull()
    expect(s.original).toBeNull()
  })
})
