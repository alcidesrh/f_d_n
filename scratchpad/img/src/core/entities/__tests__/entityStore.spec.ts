import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { defineEntityStore, olvidarDefinicion } from '@/core/entities/entityStore'
import { manifiestoActual, type Manifiesto } from '@/core/metadata/manifiesto'

const { fetchConfig, schemaMock } = vi.hoisted(() => ({
  fetchConfig: vi.fn<(entityClass: string) => Promise<unknown>>(),
  schemaMock: {
    entities: {} as Record<string, unknown>,
    huellas: {} as Record<string, string>,
    require(name: string) {
      const entity = this.entities[name]
      if (!entity) throw new Error(`No existe la entidad: ${name}`)
      return entity
    },
    find(name: string) {
      return this.entities[name] ?? null
    },
  },
}))

vi.mock('@/core/metadata/entityConfiguration', () => ({ fetchEntityConfiguration: fetchConfig }))
vi.mock('@/core/entities/schema', () => ({ useSchemaStore: () => schemaMock }))

const arg = (name: string) => ({ name, type: 'String', namedType: 'String', required: false, isList: false })
const bus = {
  name: 'Bus',
  scalarFields: ['id', 'codigo'],
  collectionKind: 'page-connection',
  filterArgs: [arg('codigo')],
  orderInput: 'BusFilter_order',
  orderFields: ['codigo'],
}

const manifiesto = (revision: number, configs: Record<string, number> = {}, completa = 0): Manifiesto => ({
  revision,
  esquema: { revision: 1, huella: 'esquema' },
  configs,
  completa,
  aviso: null,
})

describe('store de entidad y la metadata del cliente', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    schemaMock.entities = { Bus: bus }
    schemaMock.huellas = { Bus: 'h1' }
    manifiestoActual.value = null
    fetchConfig.mockResolvedValue({ collectionFieldConfig: [{ field: 'codigo', visible: true }], formFields: [] })
  })
  afterEach(() => {
    olvidarDefinicion('Bus')
    manifiestoActual.value = null
  })

  it('reusa la configuración guardada mientras el manifiesto no diga que cambió', async () => {
    manifiestoActual.value = manifiesto(10, { Bus: 7 })
    const store = defineEntityStore('Bus')()
    await store.init()
    expect(fetchConfig).toHaveBeenCalledOnce()
    expect(store.revisionConfig).toBe(10)

    manifiestoActual.value = manifiesto(12, { Bus: 7, Piloto: 12 })
    await store.init()
    expect(fetchConfig).toHaveBeenCalledOnce()

    manifiestoActual.value = manifiesto(13, { Bus: 13, Piloto: 12 })
    await store.init()
    expect(fetchConfig).toHaveBeenCalledTimes(2)
    expect(store.revisionConfig).toBe(13)
  })

  it('sin manifiesto la pide una vez por página', async () => {
    const store = defineEntityStore('Bus')()
    await store.init()
    await store.init()
    expect(fetchConfig).toHaveBeenCalledOnce()
    expect(store.revisionConfig).toBe(0)
  })

  it('con force la pide siempre', async () => {
    manifiestoActual.value = manifiesto(10)
    const store = defineEntityStore('Bus')()
    await store.init()
    await store.init(true)
    expect(fetchConfig).toHaveBeenCalledTimes(2)
  })

  it('recargar todo descarta también los datos guardados', async () => {
    manifiestoActual.value = manifiesto(10)
    const store = defineEntityStore('Bus')()
    await store.init()
    store.items = [{ id: 1 }]
    store.fullList = [{ value: '/api/buses/1', label: 'Bus 1' }]

    manifiestoActual.value = manifiesto(11, {}, 11)
    await store.init()
    expect(fetchConfig).toHaveBeenCalledTimes(2)
    expect(store.items).toEqual([])
    expect(store.fullList).toEqual([])
  })

  it('si cambió la metadata de la entidad ajusta filtros, orden y caché', async () => {
    manifiestoActual.value = manifiesto(10)
    const store = defineEntityStore('Bus')()
    await store.init()
    expect(store.huellaEsquema).toBe('h1')
    store.filters = { codigo: 'A-1', pilotoAux: '/api/pilotos/3' }
    store.order = [{ codigo: 'ASC' }, { pilotoAux: 'DESC' }]
    store.items = [{ id: 1 }]

    await store.init()
    expect(store.filters).toEqual({ codigo: 'A-1', pilotoAux: '/api/pilotos/3' })

    schemaMock.huellas = { Bus: 'h2' }
    await store.init()
    expect(store.filters).toEqual({ codigo: 'A-1' })
    expect(store.order).toEqual([{ codigo: 'ASC' }])
    expect(store.items).toEqual([])
    expect(store.huellaEsquema).toBe('h2')
    expect(fetchConfig).toHaveBeenCalledOnce()
  })
})
