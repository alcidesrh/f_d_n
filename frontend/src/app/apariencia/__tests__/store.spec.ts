import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSessionStore } from '@/core/auth/session'
import { useAparienciaStore } from '../store'

const guardarApariencia = vi.hoisted(() => vi.fn<(apariencia: unknown) => Promise<unknown>>(async () => ({})))
const usePreset = vi.hoisted(() => vi.fn<(preset: unknown) => void>())
vi.mock('@/core/cuenta/api', () => ({ guardarApariencia }))
vi.mock('@primeuix/themes', () => ({ usePreset }))
vi.mock('../preset', () => ({ presetCompleto: vi.fn<() => Promise<object>>(async () => ({})) }))

const estilo = () => document.documentElement.style

describe('useAparienciaStore', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    localStorage.clear()
    setActivePinia(createPinia())
    useSessionStore().user = 'ana'
    guardarApariencia.mockClear()
    usePreset.mockClear()
  })
  afterEach(() => {
    vi.useRealTimers()
    document.documentElement.removeAttribute('style')
  })

  it('un cambio se ve al instante como parámetro CSS y sale a la cuenta al rato, uno solo', async () => {
    const apariencia = useAparienciaStore()
    apariencia.cambiar('esquinas', 0)
    apariencia.cambiar('texto', 17)
    expect(estilo().getPropertyValue('--apariencia-esquinas')).toBe('0')
    expect(estilo().getPropertyValue('--apariencia-texto')).toBe('17px')
    expect(apariencia.pendiente).toBe(true)
    await vi.runAllTimersAsync()
    expect(guardarApariencia).toHaveBeenCalledTimes(1)
    expect(guardarApariencia).toHaveBeenCalledWith(expect.objectContaining({ esquinas: 0, texto: 17 }))
    expect(apariencia.pendiente).toBe(false)
  })

  it('volver al de fábrica quita el parámetro de <html>; un parámetro no regenera el preset', async () => {
    const apariencia = useAparienciaStore()
    await apariencia.aplicar()
    usePreset.mockClear()
    apariencia.cambiar('densidad', 0.75)
    apariencia.cambiar('densidad', 1)
    await vi.runAllTimersAsync()
    expect(estilo().getPropertyValue('--apariencia-densidad')).toBe('')
    expect(usePreset).not.toHaveBeenCalled()
    apariencia.cambiar('estilo', 'aura')
    await vi.runAllTimersAsync()
    expect(usePreset).toHaveBeenCalledTimes(1)
  })

  it('ignora valores que no son opciones', () => {
    const apariencia = useAparienciaStore()
    apariencia.cambiar('densidad', 3)
    expect(apariencia.ajustes.densidad).toBe(1)
    expect(apariencia.pendiente).toBe(false)
  })

  it('la de la cuenta gana, salvo que haya un cambio local sin confirmar: entonces se manda este', async () => {
    const apariencia = useAparienciaStore()
    apariencia.recibirDeLaCuenta({ primario: 'red', sombras: 0, inventado: 'x' })
    expect(apariencia.ajustes.primario).toBe('red')
    expect(apariencia.ajustes.sombras).toBe(0)
    expect(guardarApariencia).not.toHaveBeenCalled()

    apariencia.cambiar('primario', 'teal')
    apariencia.recibirDeLaCuenta({ primario: 'red' })
    await vi.runAllTimersAsync()
    expect(apariencia.ajustes.primario).toBe('teal')
    expect(guardarApariencia).toHaveBeenCalledWith(expect.objectContaining({ primario: 'teal' }))
  })

  it('suplantando, lo cambiado queda en este navegador', async () => {
    useSessionStore().original = { user: 'admin', token: 't' }
    const apariencia = useAparienciaStore()
    apariencia.cambiar('sombras', 0)
    await vi.runAllTimersAsync()
    expect(apariencia.ajustes.sombras).toBe(0)
    expect(apariencia.pendiente).toBe(false)
    expect(guardarApariencia).not.toHaveBeenCalled()
  })

  it('si la cuenta no responde, sigue pendiente', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    guardarApariencia.mockRejectedValueOnce(new Error('sin red'))
    const apariencia = useAparienciaStore()
    apariencia.cambiar('movimiento', 0)
    await vi.runAllTimersAsync()
    expect(apariencia.pendiente).toBe(true)
  })

  it('restablecer vuelve a la de fábrica pero conserva el modo', () => {
    const apariencia = useAparienciaStore()
    apariencia.cambiar('modo', 'dark')
    apariencia.cambiar('esquinas', 2)
    expect(apariencia.esDeFabrica).toBe(false)
    apariencia.restablecer()
    expect(apariencia.ajustes.modo).toBe('dark')
    expect(apariencia.ajustes.esquinas).toBe(1)
    expect(apariencia.esDeFabrica).toBe(true)
  })
})
