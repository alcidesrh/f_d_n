/**
 * Fábrica de stores de entidades dinámicos (`use{EntityName}Store`).
 *
 * `defineEntityStore(name)` devuelve la definición Pinia del store para la
 * entidad `name` (id `entity:{name}`). Cada nombre se define una sola vez
 * (Pinia rechaza ids duplicados); el uso real se hace por demanda vía
 * `useEntityRegistry().getEntity(name)`.
 */

import { defineStore } from 'pinia'
import type { StoreDefinition } from 'pinia'
import { rest } from '@/lib/apollo/rest'
import { useSchemaRepositoryStore } from '@/stores/schemaRepository'
import type { CollectionFieldConfig, EntityStore, EntityStoreState } from './types'

const definitions = new Map<string, StoreDefinition>()

export function defineEntityStore(name: string): StoreDefinition {
  let definition = definitions.get(name)
  if (!definition) {
    definition = createEntityStore(name)
    definitions.set(name, definition)
  }
  return definition
}

function createEntityStore(name: string): StoreDefinition {
  return defineStore(`entity:${name}`, {
    persist: true,
    state: (): EntityStoreState => ({
      name,
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
    }),

    actions: {
      /**
       * Carga las columnas del listado desde el endpoint REST
       * `/entity_configurations?entityClass={name}`. Si el backend no tiene
       * configuración, usa todas las propiedades (scalars) del schema.
       */
      async loadColumns(this: EntityStore): Promise<CollectionFieldConfig[]> {
        try {
          const config = await rest.getEntityConfiguration(this.name)
          const columns = config?.collectionFieldConfig
          if (columns && columns.length > 0) {
            this.columns = columns
            return columns
          }
        } catch (error) {
          console.warn(
            `[entity:${this.name}] falló la carga de columnas REST, usando schema:`,
            error,
          )
        }
        const fallback = buildFallbackColumns(this.name)
        this.columns = fallback
        return fallback
      },

      async fetchItems<T>(this: EntityStore<T>): Promise<T[]> {
        await useSchemaRepositoryStore().collection(this)
        return this.items
      },

      async fetchItem<T>(this: EntityStore<T>, id: string | number): Promise<T> {
        return useSchemaRepositoryStore().item(this, id)
      },

      async create<T>(this: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
        return useSchemaRepositoryStore().create(this, data)
      },

      async update<T>(this: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
        return useSchemaRepositoryStore().update(this, data)
      },

      async remove<T>(this: EntityStore<T>, id: string | number): Promise<T> {
        return useSchemaRepositoryStore().delete(this, id)
      },
    },
  })
}

/**
 * Columnas por defecto cuando el backend no tiene `entity_configurations`:
 * todas las propiedades escalares de la entidad según el schema.
 */
export function buildFallbackColumns(name: string): CollectionFieldConfig[] {
  const schema = useSchemaRepositoryStore().getEntity(name)
  const fields = (schema?.scalarFields ?? []).filter((field) => field !== 'id' && field !== '_id')
  return fields.map((field, index) => ({
    field,
    label: field,
    position: index + 1,
    visible: true,
    sortable: true,
    filterable: true,
  }))
}
