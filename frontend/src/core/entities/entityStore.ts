/**
 * Fábrica de stores de entidad. `defineEntityStore(name)` devuelve la
 * definición Pinia (`entity:{name}`, persistida: el listado se reencuentra
 * como se dejó). Se usa vía `getEntity(name)` (`registry.ts`).
 */
import { defineStore } from 'pinia'
import { fetchEntityConfiguration } from '@/core/metadata/entityConfiguration'
import { repository } from './repository'
import { useSchemaStore } from './schema'
import { entitySlug } from './slug'
import type { CollectionFieldConfig, EntityStore, EntityStoreState } from './types'

const DEFAULT_PAGINATION = {
  itemsPerPage: 10,
  currentPage: 1,
  totalCount: 0,
  lastPage: 1,
  hasNextPage: false,
}

/** Carga de configuración en curso por entidad (evita pedirla dos veces a la vez). */
const configRequests = new Map<string, Promise<void>>()

/**
 * Columnas por defecto cuando el backend no tiene `entity_configurations`:
 * todas las propiedades escalares (menos `id`); ordenables solo las que acepta
 * el input de orden.
 */
export function buildFallbackColumns(name: string): CollectionFieldConfig[] {
  const schema = useSchemaStore().require(name)
  const orderable = new Set(schema.orderFields)
  return schema.scalarFields
    .filter((field) => field !== 'id' && field !== '_id')
    .map((field, index) => ({
      field,
      label: field,
      position: index + 1,
      visible: true,
      sortable: Boolean(schema.orderInput) && orderable.has(field),
      filterable: true,
      showFilter: false,
    }))
}

function createEntityStore(name: string) {
  const schema = useSchemaStore()
  const entity = schema.require(name)
  const paginated = entity.collectionKind === 'page-connection'

  return defineStore(`entity:${entity.name}`, {
    persist: true,
    state: (): EntityStoreState => ({
      name: entity.name,
      columns: [],
      formFields: [],
      items: [],
      filters: {},
      order: [],
      item: null,
      fullList: [],
      ...(paginated ? { pagination: { ...DEFAULT_PAGINATION } } : {}),
    }),
    getters: {
      metadata: (st) => schema.require(st.name),
      slug: (st) => entitySlug(st.name),
    },
    actions: {
      async init(force = false) {
        if (!force && this.columns.length > 0 && this.formFields.length > 0) return
        let request = configRequests.get(this.name)
        if (!request) {
          request = fetchEntityConfiguration(this.name)
            .then((config) => {
              this.formFields = config?.formFields ?? []
              const columns = config?.collectionFieldConfig ?? []
              this.columns = columns.length
                ? columns.map((column) => ({ ...column, showFilter: false }))
                : buildFallbackColumns(this.name)
            })
            .catch((error: unknown) =>
              console.warn(`[entity:${this.name}] sin configuración REST:`, error),
            )
            .finally(() => configRequests.delete(this.name))
          configRequests.set(this.name, request)
        }
        await request
      },
      async fetchItems() {
        await repository.collection(this as unknown as EntityStore)
        return this.items
      },
      fetchItem(id: string | number, fields?: string[]) {
        return repository.item(this as unknown as EntityStore, id, fields)
      },
      create(data: Record<string, unknown>) {
        return repository.create(this as unknown as EntityStore, data)
      },
      update(data: Record<string, unknown>) {
        return repository.update(this as unknown as EntityStore, data)
      },
      remove(id: string | number) {
        return repository.delete(this as unknown as EntityStore, id)
      },
      loadFullList(force = false) {
        return repository.fullList(this as unknown as EntityStore, { force })
      },
    },
  })
}

const definitions = new Map<string, ReturnType<typeof createEntityStore>>()

export function defineEntityStore(name: string) {
  let definition = definitions.get(name)
  if (!definition) {
    definition = createEntityStore(name)
    definitions.set(name, definition)
  }
  return definition
}
