import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { getEntity } from '@/core/entities/registry'

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
})
