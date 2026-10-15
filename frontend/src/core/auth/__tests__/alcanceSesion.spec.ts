import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, defineStore, setActivePinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { createApp, nextTick, onScopeDispose, ref } from 'vue'
import { alcanceSesion, almacenPorUsuario, claveDeUsuario, entrarComo, salirDeStores } from '../alcanceSesion'

let desuscritas = 0
let recargas = 0

/** Pinia aplica los plugins al instalarse en una app. */
function nuevaPinia() {
  const pinia = createPinia()
  pinia.use(alcanceSesion)
  pinia.use(createPersistedState({ storage: almacenPorUsuario }))
  createApp({}).use(pinia)
  setActivePinia(pinia)
}

const useCarrito = defineStore('carrito', () => {
  const asientos = ref<number[]>([])
  onScopeDispose(() => desuscritas++)
  return { asientos }
})
const useSidebar = defineStore('sidebar', { persist: true, state: () => ({ modo: 'abierto' }) })
const useTema = defineStore('tema', {
  persist: true,
  permanente: { alRecargar: () => void recargas++ },
  state: () => ({ modo: 'claro' }),
})
const useEsquema = defineStore('esquema', { global: true, persist: true, state: () => ({ version: 1 }) })

describe('alcanceSesion', () => {
  beforeEach(() => {
    localStorage.clear()
    desuscritas = 0
    recargas = 0
    nuevaPinia()
    entrarComo('ana')
  })

  it('otro usuario no ve la venta a medias del anterior', () => {
    useCarrito().asientos = [3, 4]
    entrarComo('beto')
    expect(useCarrito().asientos).toEqual([])
    expect(desuscritas).toBe(1)
  })

  it('el mismo usuario (sesión vencida) conserva su trabajo', () => {
    useCarrito().asientos = [3, 4]
    entrarComo('ana')
    expect(useCarrito().asientos).toEqual([3, 4])
  })

  it('el cierre de sesión descarta el trabajo en memoria', () => {
    useCarrito().asientos = [3, 4]
    salirDeStores()
    expect(useCarrito().asientos).toEqual([])
  })

  it('cada usuario reencuentra sus preferencias', async () => {
    useSidebar().modo = 'cerrado'
    await nextTick()

    entrarComo('beto')
    expect(useSidebar().modo).toBe('abierto')

    entrarComo('ana')
    expect(useSidebar().modo).toBe('cerrado')
  })

  it('las preferencias sobreviven a la recarga de la página', async () => {
    useSidebar().modo = 'cerrado'
    await nextTick()
    nuevaPinia()
    expect(useSidebar().modo).toBe('cerrado')
  })

  it('recarga en el sitio las permanentes con lo del nuevo usuario', async () => {
    const tema = useTema()
    tema.modo = 'oscuro'
    await nextTick()

    entrarComo('beto')
    expect(useTema()).toBe(tema)
    expect(tema.modo).toBe('claro')
    expect(recargas).toBe(1)

    entrarComo('ana')
    expect(tema.modo).toBe('oscuro')
  })

  it('las globales son de todos y no llevan usuario', async () => {
    useEsquema().version = 2
    await nextTick()
    entrarComo('beto')
    expect(useEsquema().version).toBe(2)
    expect(localStorage.getItem('esquema')).toContain('2')
  })

  it('claveDeUsuario separa lo guardado a mano', () => {
    expect(claveDeUsuario('boletos.columnas')).toBe('usuario/ana/boletos.columnas')
    entrarComo('beto')
    expect(claveDeUsuario('boletos.columnas')).toBe('usuario/beto/boletos.columnas')
  })
})
