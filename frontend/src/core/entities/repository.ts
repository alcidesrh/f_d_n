/**
 * Operaciones de datos de una entidad sobre su store: leen el estado del
 * listado (paginación, filtros, orden, columnas) y escriben el resultado
 * (`items`, `item`, `pagination`, `fullList`). Sin estado propio.
 */
import { graphql } from '@/core/graphql/client'
import { toMutationInput } from '@/core/graphql/documents'
import type {
  AgnosticOption,
  CollectionResult,
  EntitySchema,
  MutationSchema,
} from '@/core/graphql/types'
import { useSchemaStore } from './schema'
import type { EntityStore } from './types'

const idOf = (item: unknown) => (item as { id?: unknown } | null)?.id

function mutationOf(entity: EntitySchema, kind: 'create' | 'update' | 'delete'): MutationSchema {
  const mutation = entity[kind]
  if (!mutation) throw new Error(`"${entity.name}" no expone ${kind}`)
  return mutation
}

function toInput(entity: EntitySchema, kind: 'create' | 'update', data: Record<string, unknown>) {
  return toMutationInput(entity, mutationOf(entity, kind), data, useSchemaStore().entities)
}

export const repository = {
  async item<T>(store: EntityStore<T>, id: string | number, fields?: string[]): Promise<T> {
    store.item = await graphql.item<T>(useSchemaStore().require(store.name), id, fields)
    return store.item
  },

  /**
   * Página actual del listado. Pide solo las columnas visibles (+ `id`) y solo
   * condiciones de orden sobre campos que el backend acepta. Las entidades sin
   * paginación (sin `store.pagination`) se cargan completas.
   */
  async collection<T>(store: EntityStore<T>): Promise<CollectionResult<T>> {
    const entity = useSchemaStore().require(store.name)
    const visible = store.columns.filter((col) => col.visible !== false).map((col) => col.field)
    const fields = visible.length > 0 ? [...new Set(['id', ...visible])] : undefined
    const order = entity.orderInput
      ? store.order.filter((cond) =>
          Object.keys(cond).every((field) => entity.orderFields.includes(field)),
        )
      : []
    const page = store.pagination

    const result = await graphql.collection<T>(entity, {
      ...(page ? { currentPage: page.currentPage, itemsPerPage: page.itemsPerPage } : {}),
      filters: store.filters,
      order,
      fields,
    })
    store.items = result.items
    if (page)
      store.pagination = {
        ...result.pagination,
        itemsPerPage: page.itemsPerPage || result.pagination.itemsPerPage,
      }
    return result
  },

  async create<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
    const entity = useSchemaStore().require(store.name)
    const created = await graphql.create<T>(entity, toInput(entity, 'create', data))
    store.item = created
    store.items = [created, ...store.items]
    if (store.fullList.length) void repository.fullList(store, { force: true })
    return created
  },

  async update<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
    const entity = useSchemaStore().require(store.name)
    const updated = await graphql.update<T>(entity, toInput(entity, 'update', data))
    store.item = updated
    const id = idOf(updated)
    if (id !== undefined)
      store.items = store.items.map((item) => (idOf(item) === id ? updated : item))
    return updated
  },

  async delete<T>(store: EntityStore<T>, id: string | number): Promise<T> {
    const entity = useSchemaStore().require(store.name)
    mutationOf(entity, 'delete')
    const deleted = await graphql.delete<T>(entity, id)
    store.items = store.items.filter((item) => idOf(item) !== id)
    if (idOf(store.item) === id) store.item = null
    return deleted
  },

  /** Todos los registros como `{ value: IRI, label }` (options de relaciones); cacheado en el store. */
  async fullList<T>(store: EntityStore<T>, { force = false } = {}): Promise<AgnosticOption[]> {
    if (!force && store.fullList.length > 0) return store.fullList
    store.fullList = await graphql.agnosticList(store.name)
    return store.fullList
  },
}
