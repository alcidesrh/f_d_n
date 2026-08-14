/**
 * `useSchemaRepositoryStore` — punto de entrada a la API GraphQL.
 *
 * Se crea después del cliente Apollo singleton (`src/lib/apollo/client.ts`)
 * y antes de montar cualquier vista. En su primera creación ejecuta la
 * introspección GraphQL y la traduce a metadatos planos por entidad; el
 * estado persistido (pinia-plugin-persistedstate) evita reintrospeccionar.
 *
 * Sus actions (`item`, `collection`, `create`, `update`, `delete`) reciben
 * siempre el store de la entidad (`use{EntityName}Store`): leen su estado
 * (paginación/filtros/orden) y actualizan sus items/item.
 */

import { defineStore } from 'pinia'
import { apollo } from '@/lib/apollo'
import { toMutationInput } from '@/lib/apollo/documents'
import type { AgnosticOption, CollectionResult, EntitySchema } from '@/lib/apollo/types'
import type { EntityStore } from './entities/types'

/** Bump para invalidar el schema persistido cuando cambia el formato de metadatos. */
export const SCHEMA_REPOSITORY_VERSION = 1

export interface SchemaRepositoryState {
  status: 'idle' | 'loading' | 'ready' | 'error'
  error: string
  entities: Record<string, EntitySchema>
  schemaVersion: number
  loadedAt: string | null
}

export const useSchemaRepositoryStore = defineStore('schemaRepository', {
  persist: {
    pick: ['entities', 'schemaVersion', 'loadedAt'],
  },

  state: (): SchemaRepositoryState => ({
    status: 'idle',
    error: '',
    entities: {},
    schemaVersion: SCHEMA_REPOSITORY_VERSION,
    loadedAt: null,
  }),

  getters: {
    ready: (st): boolean => st.status === 'ready',
    hasEntities: (st): boolean => Object.keys(st.entities).length > 0,
  },

  actions: {
    /**
     * Carga (o reusa el persistido) del metadata de entidades. Llamar una
     * vez en el bootstrap, antes de renderizar las vistas.
     */
    async init() {
      if (this.status === 'loading') return
      if (this.schemaVersion !== SCHEMA_REPOSITORY_VERSION) this.entities = {}
      if (this.hasEntities) {
        this.status = 'ready'
        return
      }
      this.status = 'loading'
      this.error = ''
      try {
        this.entities = await apollo.introspect()
        this.loadedAt = new Date().toISOString()
        this.schemaVersion = SCHEMA_REPOSITORY_VERSION
        this.status = 'ready'
      } catch (error) {
        this.status = 'error'
        this.error = error instanceof Error ? error.message : String(error)
        console.error('[schemaRepository] no se pudo cargar el schema GraphQL:', error)
      }
    },

    getEntity(name: string): EntitySchema | null {
      return this.entities[name] ?? null
    },

    has(name: string): boolean {
      return Boolean(this.entities[name])
    },

    requireEntity<T>(store: EntityStore<T>): EntitySchema {
      const entity = this.entities[store.name]
      if (!entity) {
        throw new Error(`[schemaRepository] no hay metadatos para "${store.name}"`)
      }
      return entity
    },

    async item<T>(store: EntityStore<T>, id: string | number): Promise<T> {
      const entity = this.requireEntity(store)
      store.item = await apollo.item<T>(entity, id)
      return store.item
    },

    async collection<T>(store: EntityStore<T>): Promise<CollectionResult<T>> {
      const entity = this.requireEntity(store)
      const result = await apollo.collection<T>(entity, {
        currentPage: store.pagination.currentPage,
        itemsPerPage: store.pagination.itemsPerPage,
        filters: store.filters,
        order: store.order,
      })
      store.items = result.items
      store.pagination = {
        ...result.pagination,
        itemsPerPage: store.pagination.itemsPerPage || result.pagination.itemsPerPage,
      }
      return result
    },

    async create<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.create) {
        throw new Error(`[schemaRepository] "${store.name}" no expone create`)
      }
      const input = toMutationInput(entity, entity.create, data, this.entities)
      const created = await apollo.create<T>(entity, input)
      store.item = created
      store.items = [created, ...store.items]
      return created
    },

    async update<T>(store: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.update) {
        throw new Error(`[schemaRepository] "${store.name}" no expone update`)
      }
      const input = toMutationInput(entity, entity.update, data, this.entities)
      const updated = await apollo.update<T>(entity, input)
      store.item = updated
      const id = (updated as { id?: unknown } | null)?.id
      if (id !== undefined) {
        store.items = store.items.map((item) =>
          (item as { id?: unknown } | null)?.id === id ? updated : item,
        )
      }
      return updated
    },

    async delete<T>(store: EntityStore<T>, id: string | number): Promise<T> {
      const entity = this.requireEntity(store)
      if (!entity.delete) {
        throw new Error(`[schemaRepository] "${store.name}" no expone delete`)
      }
      const deleted = await apollo.delete<T>(entity, id)
      store.items = store.items.filter((item) => (item as { id?: unknown } | null)?.id !== id)
      if ((store.item as { id?: unknown } | null)?.id === id) store.item = null
      return deleted
    },

    /**
     * Lista completa de la entidad (`collectionAgnostic(resource)`) para
     * options de selects de relaciones. Sirve la caché del store salvo con
     * `force: true`; el store la persiste en LocalStorage (pinia-plugin).
     */
    async fullList<T>(
      store: EntityStore<T>,
      opts: { force?: boolean } = {},
    ): Promise<AgnosticOption[]> {
      if (!opts.force && store.fullList.length > 0) return store.fullList
      const list = await apollo.agnosticList(store.name)
      store.fullList = list
      return list
    },
  },
})
