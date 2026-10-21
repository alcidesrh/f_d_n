import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'
import { createApp, nextTick } from 'vue'
import {
  alcanceSesion,
  almacenPorUsuario,
  entrarComo,
  salirDeStores,
} from '@/core/auth/alcanceSesion'
import { defineEntityStore } from '@/core/entities/entityStore'
import { entityStores, getEntity } from '@/core/entities/registry'

const { restMock, schemaMock } = vi.hoisted(() => ({
  restMock: { getEntityConfiguration: vi.fn<(entityClass: string) => Promise<unknown>>() },
  schemaMock: { require: vi.fn<(name: string) => unknown>() },
}))

vi.mock('@/core/metadata/entityConfiguration', () => ({
  fetchEntityConfiguration: restMock.getEntityConfiguration,
}))
vi.mock('@/core/entities/schema', () => ({ useSchemaStore: () => schemaMock }))

describe('getEntity', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    restMock.getEntityConfiguration.mockResolvedValue(null)
    schemaMock.require.mockImplementation((name: string) => {
      if (name === 'Nope') throw new Error('No existe la entidad: Nope')
      return { name, scalarFields: ['id', 'numero', 'total'], orderFields: [], orderInput: null }
    })
  })

  it('crea el store de la entidad por demanda', () => {
    const store = getEntity('Boleto')
    expect(store.name).toBe('Boleto')
    expect(store.$id).toBe('entity:Boleto')
  })

  it('devuelve la misma instancia para el mismo nombre', () => {
    const a = getEntity('Boleto')
    const b = getEntity('Boleto')
    expect(a).toBe(b)
  })

  it('lanza si la entidad no existe en el schema', () => {
    expect(() => getEntity('Nope')).toThrow('No existe la entidad: Nope')
  })

  it('carga columnas desde el fallback del schema si no hay configuración REST', async () => {
    const store = getEntity('Bus')
    await vi.waitFor(() => expect(store.columns.length).toBeGreaterThan(0))
    expect(store.columns.map((c) => c.field)).toEqual(['numero', 'total'])
  })

  it('usa la configuración REST cuando existe', async () => {
    restMock.getEntityConfiguration.mockResolvedValue({
      collectionFieldConfig: [{ field: 'numero', label: 'Número', position: 1, visible: true }],
    })
    const store = getEntity('Cliente')
    await vi.waitFor(() => expect(store.columns.length).toBe(1))
    expect(store.columns[0]?.field).toBe('numero')
    expect(restMock.getEntityConfiguration).toHaveBeenCalledWith('Cliente')
  })

  describe('al descartar las stores del usuario', () => {
    const clave = (usuario: string) => `usuario/${usuario}/entity:Bus`
    const guardado = (usuario: string) => JSON.parse(localStorage.getItem(clave(usuario)) ?? 'null')

    beforeEach(() => {
      localStorage.clear()
      // Como `app/pinia.ts`; Pinia aplica los plugins al instalarse en una app.
      const pinia = createPinia()
      pinia.use(alcanceSesion)
      pinia.use(createPersistedState({ storage: almacenPorUsuario }))
      createApp({}).use(pinia)
      setActivePinia(pinia)
      entrarComo('ana')
    })

    it('otro usuario recibe una instancia viva con lo suyo y lo que cambia se guarda en su clave', async () => {
      const deAna = getEntity('Bus')
      deAna.filters = { numero: 'A-1' }
      await nextTick()
      localStorage.setItem(clave('beto'), JSON.stringify({ filters: { numero: 'B-1' } }))

      entrarComo('beto')
      const deBeto = getEntity('Bus')
      expect(deBeto).not.toBe(deAna)
      expect(defineEntityStore('Bus')()).toBe(deBeto)
      expect(deBeto.filters).toEqual({ numero: 'B-1' })

      deBeto.filters = { numero: 'B-2' }
      await nextTick()
      expect(guardado('beto').filters).toEqual({ numero: 'B-2' })
      expect(guardado('ana').filters).toEqual({ numero: 'A-1' })
    })

    it('el mismo usuario, al volver a entrar, sigue guardando lo que cambia', async () => {
      const antes = getEntity('Bus')
      salirDeStores()
      // `entity-config` refresca la instancia del registro: no debe quedar una descartada.
      expect(entityStores.has('Bus')).toBe(false)

      entrarComo('ana')
      const despues = getEntity('Bus')
      expect(despues).not.toBe(antes)
      despues.filters = { numero: 'A-2' }
      await nextTick()
      expect(guardado('ana').filters).toEqual({ numero: 'A-2' })
    })
  })
})
