import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSchemaStore, SCHEMA_VERSION } from '@/core/entities/schema'
import { repository } from '@/core/entities/repository'
import type { EntityStore, EntityStoreState } from '@/core/entities/types'
import type { AgnosticOption, EntitySchema } from '@/core/graphql/types'
import type { Manifiesto } from '@/core/metadata/manifiesto'

const { apolloMock } = vi.hoisted(() => ({
  apolloMock: {
    introspect: vi.fn<() => Promise<Record<string, EntitySchema>>>(),
    item: vi.fn<(entity: EntitySchema, id: string | number) => Promise<unknown>>(),
    collection: vi.fn<(entity: EntitySchema, spec: unknown) => Promise<unknown>>(),
    create: vi.fn<(entity: EntitySchema, input: Record<string, unknown>) => Promise<unknown>>(),
    update: vi.fn<(entity: EntitySchema, input: Record<string, unknown>) => Promise<unknown>>(),
    delete: vi.fn<(entity: EntitySchema, id: string | number) => Promise<unknown>>(),
    agnosticList: vi.fn<(resource: string) => Promise<AgnosticOption[]>>(),
  },
}))

vi.mock('@/core/graphql/client', () => ({ graphql: apolloMock }))

const boletoSchema: EntitySchema = {
  name: 'Boleto',
  slug: null,
  queryItem: 'boleto',
  queryCollection: 'boletos',
  collectionKind: 'page-connection',
  collectionType: 'BoletoPageConnection',
  paginationType: 'BoletoPaginationInfo',
  orderInput: null,
  orderFields: [],
  itemArgs: [],
  collectionArgs: [],
  filterArgs: [],
  fields: [],
  scalarFields: ['id', 'numero', 'total'],
  relations: [],
  subcollections: [],
  create: {
    kind: 'create',
    field: 'createBoleto',
    inputType: 'createBoletoInput',
    payloadType: 'createBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'numero',
        type: 'String!',
        namedType: 'String',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
  update: {
    kind: 'update',
    field: 'updateBoleto',
    inputType: 'updateBoletoInput',
    payloadType: 'updateBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'numero',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
  delete: {
    kind: 'delete',
    field: 'deleteBoleto',
    inputType: 'deleteBoletoInput',
    payloadType: 'deleteBoletoPayload',
    returnsField: 'boleto',
    inputFields: [
      {
        name: 'clientMutationId',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
        enumValues: [],
      },
    ],
  },
}

function makeStore(): EntityStore<{ id: number; numero: string }> {
  return {
    $id: 'entity:Boleto',
    $patch: vi.fn<(partial: Partial<EntityStoreState<{ id: number; numero: string }>>) => void>(),
    $reset: vi.fn<() => void>(),
    name: 'Boleto',
    columns: [],
    formFields: [],
    items: [],
    pagination: {
      itemsPerPage: 10,
      currentPage: 1,
      totalCount: 0,
      lastPage: 1,
      hasNextPage: false,
    },
    filters: {},
    customFilter: null,
    order: [],
    item: null,
    fullList: [],
    listOptions: {},
    view: { density: 'normal', layout: 'auto', filterMode: 'or', filtersOpen: false },
    huellaEsquema: null,
    revisionConfig: null,
    metadata: boletoSchema,
    slug: 'boleto-asiento',
    init: vi.fn<() => Promise<void>>().mockResolvedValue(undefined),
    reconciliar: vi.fn<() => void>(),
    loadFullList: vi.fn<() => Promise<AgnosticOption[]>>().mockResolvedValue([]),
    fetchItems: vi.fn<() => Promise<Array<{ id: number; numero: string }>>>(),
    fetchItem: vi.fn<(id: string | number) => Promise<{ id: number; numero: string }>>(),
    create: vi.fn<(data: Record<string, unknown>) => Promise<{ id: number; numero: string }>>(),
    update: vi.fn<(data: Record<string, unknown>) => Promise<{ id: number; numero: string }>>(),
    remove: vi.fn<(id: string | number) => Promise<{ id: number; numero: string }>>(),
  }
}

describe('useSchemaStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    apolloMock.introspect.mockResolvedValue({ Boleto: boletoSchema })
    apolloMock.collection.mockResolvedValue({
      items: [{ id: 1, numero: 'AB' }],
      pagination: {
        currentPage: 1,
        itemsPerPage: 10,
        lastPage: 1,
        totalCount: 1,
        hasNextPage: false,
      },
    })
  })

  it('inicializa el schema desde la introspección', async () => {
    const store = useSchemaStore()
    expect(store.status).toBe('idle')
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
    expect(store.status).toBe('ready')
    expect(store.entities.Boleto).toEqual(boletoSchema)
    expect(store.loadedAt).toBeTruthy()
  })

  it('no reintrospecciona si ya hay entidades', async () => {
    const store = useSchemaStore()
    await store.init()
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
  })

  it('marca error si falla la introspección', async () => {
    apolloMock.introspect.mockRejectedValueOnce(new Error('boom'))
    const store = useSchemaStore()
    await store.init()
    expect(store.status).toBe('error')
    expect(store.error).toContain('boom')
  })

  it('reintrospecciona si cambia la versión del schema persistido', async () => {
    const store = useSchemaStore()
    store.entities = { Boleto: boletoSchema }
    store.schemaVersion = SCHEMA_VERSION + 1
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
  })

  describe('con el manifiesto de la metadata (ADR-036)', () => {
    const manifiesto = (huella: string, revision = 3, completa = 0): Manifiesto => ({
      revision,
      esquema: { revision: 1, huella },
      configs: {},
      completa,
      aviso: null,
    })

    it('reusa el guardado si el schema del servidor es el mismo y lo vuelve a pedir si es otro', async () => {
      const store = useSchemaStore()
      await store.init(manifiesto('s1', 3))
      expect(apolloMock.introspect).toHaveBeenCalledOnce()
      expect(store.huellaServidor).toBe('s1')
      expect(store.revision).toBe(3)

      await store.init(manifiesto('s1', 4))
      expect(apolloMock.introspect).toHaveBeenCalledOnce()

      await store.init(manifiesto('s2', 5))
      expect(apolloMock.introspect).toHaveBeenCalledTimes(2)
      expect(store.huellaServidor).toBe('s2')
    })

    it('recargar todo lo vuelve a pedir aunque el schema sea el mismo', async () => {
      const store = useSchemaStore()
      await store.init(manifiesto('s1', 3))
      expect(store.desactualizado(manifiesto('s1', 4, 4))).toBe(true)
      expect(store.desactualizado(manifiesto('s1', 4, 3))).toBe(false)
    })

    it('recargar dice qué entidades cambiaron', async () => {
      const store = useSchemaStore()
      await store.init(manifiesto('s1'))
      const otraVersion = { ...boletoSchema, scalarFields: ['id', 'numero'] }
      apolloMock.introspect.mockResolvedValueOnce({ Boleto: otraVersion, Cliente: { ...boletoSchema, name: 'Cliente' } })
      expect(await store.recargar(manifiesto('s2', 6))).toEqual({ agregadas: ['Cliente'], modificadas: ['Boleto'], eliminadas: [] })
      expect(store.entities.Boleto).toEqual(otraVersion)
      expect(Object.keys(store.huellas)).toEqual(['Boleto', 'Cliente'])
    })

    it('si la recarga falla se queda con el schema que tenía', async () => {
      const store = useSchemaStore()
      await store.init(manifiesto('s1'))
      apolloMock.introspect.mockRejectedValueOnce(new Error('caído'))
      expect(await store.recargar(manifiesto('s2', 6))).toBeNull()
      expect(store.status).toBe('ready')
      expect(store.entities.Boleto).toEqual(boletoSchema)
      expect(store.huellaServidor).toBe('s1')
    })

    it('una copia guardada sin huellas las calcula sin volver a pedir el schema', async () => {
      const store = useSchemaStore()
      store.entities = { Boleto: boletoSchema }
      await store.init()
      expect(apolloMock.introspect).not.toHaveBeenCalled()
      expect(Object.keys(store.huellas)).toEqual(['Boleto'])
    })
  })

  it('carga una colección en el store de la entidad', async () => {
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    const result = await repository.collection(entityStore)
    expect(apolloMock.collection).toHaveBeenCalledWith(
      boletoSchema,
      expect.objectContaining({ currentPage: 1 }),
    )
    expect(entityStore.items).toEqual([{ id: 1, numero: 'AB' }])
    expect(entityStore.pagination?.totalCount).toBe(1)
    expect(result.items).toHaveLength(1)
  })

  it('carga una colección sin paginado (entidad sin estado pagination)', async () => {
    const listSchema: EntitySchema = {
      ...boletoSchema,
      collectionKind: 'list',
      collectionType: null,
      paginationType: null,
    }
    apolloMock.introspect.mockResolvedValue({ Boleto: listSchema })
    apolloMock.collection.mockResolvedValue({
      items: [{ id: 1, numero: 'AB' }],
      pagination: {
        currentPage: 1,
        itemsPerPage: 1,
        lastPage: 1,
        totalCount: 1,
        hasNextPage: false,
      },
    })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    delete (entityStore as { pagination?: unknown }).pagination
    const result = await repository.collection(entityStore)
    const spec = apolloMock.collection.mock.calls[0]![1] as {
      currentPage?: number
      itemsPerPage?: number
    }
    expect(spec.currentPage).toBeUndefined()
    expect(spec.itemsPerPage).toBeUndefined()
    expect(entityStore.items).toEqual([{ id: 1, numero: 'AB' }])
    expect((entityStore as { pagination?: unknown }).pagination).toBeUndefined()
    expect(result.items).toHaveLength(1)
  })

  it('obtiene un item y lo deja en el store', async () => {
    apolloMock.item.mockResolvedValue({ id: 3, numero: 'CD' })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    const item = await repository.item(entityStore, 3)
    expect(item).toEqual({ id: 3, numero: 'CD' })
    expect(entityStore.item).toEqual({ id: 3, numero: 'CD' })
  })

  it('crea, antepone el item y refresca el item seleccionado', async () => {
    apolloMock.create.mockResolvedValue({ id: 9, numero: 'EF' })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [{ id: 1, numero: 'AB' }]
    await repository.create(entityStore, { numero: 'EF' })
    expect(apolloMock.create).toHaveBeenCalledWith(boletoSchema, { numero: 'EF' })
    expect(entityStore.items.map((i) => i.id)).toEqual([9, 1])
    expect(entityStore.item).toEqual({ id: 9, numero: 'EF' })
  })

  it('actualiza el item en el listado', async () => {
    apolloMock.update.mockResolvedValue({ id: 1, numero: 'ZZ' })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [
      { id: 1, numero: 'AB' },
      { id: 2, numero: 'CD' },
    ]
    await repository.update(entityStore, { id: 1, numero: 'ZZ' })
    expect(entityStore.items.map((i) => i.numero)).toEqual(['ZZ', 'CD'])
  })

  it('elimina el item del listado y del seleccionado', async () => {
    apolloMock.delete.mockResolvedValue({ id: 2, numero: 'CD' })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [
      { id: 1, numero: 'AB' },
      { id: 2, numero: 'CD' },
    ]
    entityStore.item = { id: 2, numero: 'CD' }
    await repository.delete(entityStore, 2)
    expect(entityStore.items.map((i) => i.id)).toEqual([1])
    expect(entityStore.item).toBeNull()
  })

  it('carga fullList desde collectionAgnostic y cachea hasta pedir fuerza', async () => {
    const options: AgnosticOption[] = [{ id: '/api/buses/1', label: 'Bus A' }]
    apolloMock.agnosticList.mockResolvedValue(options)
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    const first = await repository.fullList(entityStore)
    expect(first).toEqual(options)
    expect(entityStore.fullList).toEqual(options)
    await repository.fullList(entityStore)
    expect(apolloMock.agnosticList).toHaveBeenCalledExactlyOnceWith('Boleto')
    await repository.fullList(entityStore, { force: true })
    expect(apolloMock.agnosticList).toHaveBeenCalledTimes(2)
  })

  it('pide solo las columnas visibles (+ id) al cargar una colección', async () => {
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.columns = [
      { field: 'id', label: 'ID', visible: true },
      { field: 'numero', label: 'Número', visible: true },
      { field: 'total', label: 'Total', visible: false },
    ]
    await repository.collection(entityStore)
    expect(apolloMock.collection).toHaveBeenCalledWith(
      boletoSchema,
      expect.objectContaining({ fields: ['id', 'numero'] }),
    )
  })

  it('si no hay columnas cargadas no restringe los campos', async () => {
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    await repository.collection(entityStore)
    const spec = apolloMock.collection.mock.calls[0]![1] as { fields?: string[] }
    expect(spec.fields).toBeUndefined()
  })

  it('descarta condiciones de orden sobre campos no soportados', async () => {
    const orderable = {
      ...boletoSchema,
      orderInput: 'BoletoFilter_order',
      orderFields: ['numero'],
    }
    apolloMock.introspect.mockResolvedValue({ Boleto: orderable })
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.order = [{ numero: 'ASC' }, { total: 'DESC' }]
    await repository.collection(entityStore)
    expect(apolloMock.collection).toHaveBeenCalledWith(
      orderable,
      expect.objectContaining({ order: [{ numero: 'ASC' }] }),
    )
  })

  it('find acepta nombre o slug y require lanza si no existe', async () => {
    const store = useSchemaStore()
    await store.init()
    expect(store.find('Boleto')).toBe(store.entities.Boleto)
    expect(store.find('boleto')).toBe(store.entities.Boleto)
    expect(store.find('Nope')).toBeNull()
    expect(() => store.require('Nope')).toThrow('No existe la entidad: Nope')
  })

  it('lanza para entidades sin metadatos', async () => {
    const store = useSchemaStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.name = 'Nope'
    await expect(repository.collection(entityStore)).rejects.toThrow('No existe la entidad: Nope')
  })
})
