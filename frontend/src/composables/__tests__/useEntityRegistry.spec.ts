import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { getEntity, useEntityRegistry } from '@/composables/useEntityRegistry'

const { restMock, schemaRepoMock } = vi.hoisted(() => ({
  restMock: { getEntityConfiguration: vi.fn<(entityClass: string) => Promise<unknown>>() },
  schemaRepoMock: { getEntity: vi.fn<(name: string) => unknown>() },
}))

vi.mock('@/lib/apollo/rest', () => ({ rest: restMock }))
vi.mock('@/stores/schemaRepository', () => ({ useSchemaRepositoryStore: () => schemaRepoMock }))

describe('useEntityRegistry', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    restMock.getEntityConfiguration.mockResolvedValue(null)
    schemaRepoMock.getEntity.mockReturnValue({
      name: 'Boleto',
      scalarFields: ['id', 'numero', 'total'],
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

  it('expone getEntity vía el composable', () => {
    expect(useEntityRegistry().getEntity).toBe(getEntity)
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
