import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { EntitySchema } from '@/core/graphql/types'
import type { EntityStore } from '@/core/entities/types'
import { repository } from '@/core/entities/repository'

const { graphqlMock } = vi.hoisted(() => ({
  graphqlMock: { create: vi.fn(), update: vi.fn(), collection: vi.fn() },
}))

const bus = {
  name: 'Bus',
  create: { kind: 'create', field: 'createBus', inputType: 'createBusInput', returnsField: 'bus', inputFields: [{ name: 'codigo', isRelation: false }] },
  update: { kind: 'update', field: 'updateBus', inputType: 'updateBusInput', returnsField: 'bus', inputFields: [{ name: 'id', isRelation: false }, { name: 'codigo', isRelation: false }] },
} as unknown as EntitySchema

vi.mock('@/core/graphql/client', () => ({ graphql: graphqlMock }))
vi.mock('@/core/entities/schema', () => ({
  useSchemaStore: () => ({ require: () => bus, entities: { Bus: bus } }),
}))

const storeDe = (items: Record<string, unknown>[]) =>
  ({ name: 'Bus', item: null, items, fullList: [], pagination: null }) as unknown as EntityStore

describe('repository: guardar con solo los campos enviados', () => {
  beforeEach(() => vi.clearAllMocks())

  it('update pide en el retorno solo los campos enviados y conserva las demás columnas del listado', async () => {
    graphqlMock.update.mockResolvedValue({ id: '/api/buses/1', codigo: 'B', label: 'B' })
    const store = storeDe([{ id: '/api/buses/1', codigo: 'A', label: 'A', capacidad: 40 }])

    await repository.update(store, { id: '/api/buses/1', codigo: 'B' })

    expect(graphqlMock.update).toHaveBeenCalledWith(bus, { id: '/api/buses/1', codigo: 'B' }, ['id', 'codigo'])
    expect(store.items).toEqual([{ id: '/api/buses/1', codigo: 'B', label: 'B', capacidad: 40 }])
    expect(store.item).toEqual({ id: '/api/buses/1', codigo: 'B', label: 'B' })
  })

  it('create pasa los campos enviados al retorno', async () => {
    graphqlMock.create.mockResolvedValue({ id: '/api/buses/2', codigo: 'C', label: 'C' })
    const store = storeDe([])

    await repository.create(store, { codigo: 'C' })

    expect(graphqlMock.create).toHaveBeenCalledWith(bus, { codigo: 'C' }, ['codigo'])
    expect(graphqlMock.collection).not.toHaveBeenCalled()
  })
})
