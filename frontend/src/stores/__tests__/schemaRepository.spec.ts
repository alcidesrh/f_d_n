import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSchemaRepositoryStore, SCHEMA_REPOSITORY_VERSION } from '@/stores/schemaRepository'
import type { CollectionFieldConfig, EntityStore, EntityStoreState } from '@/stores/entities/types'
import type { EntitySchema } from '@/lib/apollo/types'

const { apolloMock } = vi.hoisted(() => ({
  apolloMock: {
    introspect: vi.fn<() => Promise<Record<string, EntitySchema>>>(),
    item: vi.fn<(entity: EntitySchema, id: string | number) => Promise<unknown>>(),
    collection: vi.fn<(entity: EntitySchema, spec: unknown) => Promise<unknown>>(),
    create: vi.fn<(entity: EntitySchema, input: Record<string, unknown>) => Promise<unknown>>(),
    update: vi.fn<(entity: EntitySchema, input: Record<string, unknown>) => Promise<unknown>>(),
    delete: vi.fn<(entity: EntitySchema, id: string | number) => Promise<unknown>>(),
  },
}))

vi.mock('@/lib/apollo', () => ({ apollo: apolloMock }))

const boletoSchema: EntitySchema = {
  name: 'Boleto',
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
      },
      {
        name: 'numero',
        type: 'String!',
        namedType: 'String',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
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
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
      },
      {
        name: 'numero',
        type: 'String',
        namedType: 'String',
        kind: 'SCALAR',
        required: false,
        isList: false,
        isRelation: false,
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
      },
      {
        name: 'id',
        type: 'ID!',
        namedType: 'ID',
        kind: 'SCALAR',
        required: true,
        isList: false,
        isRelation: false,
      },
    ],
  },
}

function makeStore(): EntityStore<{ id: number; numero: string }> {
  return {
    $id: 'entity:Boleto',
    $state: undefined as never,
    $patch: vi.fn<(partial: Partial<EntityStoreState<{ id: number; numero: string }>>) => void>(),
    $reset: vi.fn<() => void>(),
    $dispose: vi.fn<() => void>(),
    name: 'Boleto',
    columns: [],
    items: [],
    pagination: {
      itemsPerPage: 10,
      currentPage: 1,
      totalCount: 0,
      lastPage: 1,
      hasNextPage: false,
    },
    filters: {},
    order: [],
    item: null,
    loadColumns: vi.fn<() => Promise<CollectionFieldConfig[]>>().mockResolvedValue([]),
    fetchItems: vi.fn<() => Promise<Array<{ id: number; numero: string }>>>(),
    fetchItem: vi.fn<(id: string | number) => Promise<{ id: number; numero: string }>>(),
    create: vi.fn<(data: Record<string, unknown>) => Promise<{ id: number; numero: string }>>(),
    update: vi.fn<(data: Record<string, unknown>) => Promise<{ id: number; numero: string }>>(),
    remove: vi.fn<(id: string | number) => Promise<{ id: number; numero: string }>>(),
  }
}

describe('useSchemaRepositoryStore', () => {
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
    const store = useSchemaRepositoryStore()
    expect(store.status).toBe('idle')
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
    expect(store.status).toBe('ready')
    expect(store.entities.Boleto).toEqual(boletoSchema)
    expect(store.loadedAt).toBeTruthy()
  })

  it('no reintrospecciona si ya hay entidades', async () => {
    const store = useSchemaRepositoryStore()
    await store.init()
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
  })

  it('marca error si falla la introspección', async () => {
    apolloMock.introspect.mockRejectedValueOnce(new Error('boom'))
    const store = useSchemaRepositoryStore()
    await store.init()
    expect(store.status).toBe('error')
    expect(store.error).toContain('boom')
  })

  it('reintrospecciona si cambia la versión del schema persistido', async () => {
    const store = useSchemaRepositoryStore()
    store.entities = { Boleto: boletoSchema }
    store.schemaVersion = SCHEMA_REPOSITORY_VERSION + 1
    await store.init()
    expect(apolloMock.introspect).toHaveBeenCalledOnce()
  })

  it('carga una colección en el store de la entidad', async () => {
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    const result = await store.collection(entityStore)
    expect(apolloMock.collection).toHaveBeenCalledWith(
      boletoSchema,
      expect.objectContaining({ currentPage: 1 }),
    )
    expect(entityStore.items).toEqual([{ id: 1, numero: 'AB' }])
    expect(entityStore.pagination.totalCount).toBe(1)
    expect(result.items).toHaveLength(1)
  })

  it('obtiene un item y lo deja en el store', async () => {
    apolloMock.item.mockResolvedValue({ id: 3, numero: 'CD' })
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    const item = await store.item(entityStore, 3)
    expect(item).toEqual({ id: 3, numero: 'CD' })
    expect(entityStore.item).toEqual({ id: 3, numero: 'CD' })
  })

  it('crea, antepone el item y refresca el item seleccionado', async () => {
    apolloMock.create.mockResolvedValue({ id: 9, numero: 'EF' })
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [{ id: 1, numero: 'AB' }]
    await store.create(entityStore, { numero: 'EF' })
    expect(apolloMock.create).toHaveBeenCalledWith(boletoSchema, { numero: 'EF' })
    expect(entityStore.items.map((i) => i.id)).toEqual([9, 1])
    expect(entityStore.item).toEqual({ id: 9, numero: 'EF' })
  })

  it('actualiza el item en el listado', async () => {
    apolloMock.update.mockResolvedValue({ id: 1, numero: 'ZZ' })
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [
      { id: 1, numero: 'AB' },
      { id: 2, numero: 'CD' },
    ]
    await store.update(entityStore, { id: 1, numero: 'ZZ' })
    expect(entityStore.items.map((i) => i.numero)).toEqual(['ZZ', 'CD'])
  })

  it('elimina el item del listado y del seleccionado', async () => {
    apolloMock.delete.mockResolvedValue({ id: 2, numero: 'CD' })
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.items = [
      { id: 1, numero: 'AB' },
      { id: 2, numero: 'CD' },
    ]
    entityStore.item = { id: 2, numero: 'CD' }
    await store.delete(entityStore, 2)
    expect(entityStore.items.map((i) => i.id)).toEqual([1])
    expect(entityStore.item).toBeNull()
  })

  it('lanza para entidades sin metadatos', async () => {
    const store = useSchemaRepositoryStore()
    await store.init()
    const entityStore = makeStore()
    entityStore.name = 'Nope'
    await expect(store.collection(entityStore)).rejects.toThrow(/no hay metadatos/)
  })
})
