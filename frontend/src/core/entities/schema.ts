/**
 * Metadata de las entidades de la API: la introspección GraphQL aplanada por
 * entidad (`EntitySchema`). Se carga una vez al arrancar y se persiste; subir
 * `SCHEMA_VERSION` invalida la copia persistida cuando cambia su formato.
 */
import { defineStore } from 'pinia'
import { graphql } from '@/core/graphql/client'
import type { EntitySchema } from '@/core/graphql/types'
import { entityNameFromSlug } from './slug'

export const SCHEMA_VERSION = 5

export const useSchemaStore = defineStore('schema', {
  persist: { pick: ['entities', 'schemaVersion', 'loadedAt'] },

  state: () => ({
    status: 'idle' as 'idle' | 'loading' | 'ready' | 'error',
    error: '',
    entities: {} as Record<string, EntitySchema>,
    schemaVersion: SCHEMA_VERSION,
    loadedAt: null as string | null,
  }),

  getters: {
    ready: (st) => st.status === 'ready',
  },

  actions: {
    /** Introspección (o reutiliza la persistida). Llamar una vez al arrancar. */
    async init() {
      if (this.status === 'loading') return
      if (this.schemaVersion !== SCHEMA_VERSION) this.entities = {}
      if (Object.keys(this.entities).length > 0) {
        this.status = 'ready'
        return
      }
      this.status = 'loading'
      this.error = ''
      try {
        this.entities = await graphql.introspect()
        this.loadedAt = new Date().toISOString()
        this.schemaVersion = SCHEMA_VERSION
        this.status = 'ready'
      } catch (error) {
        this.status = 'error'
        this.error = error instanceof Error ? error.message : String(error)
        console.error('[schema] no se pudo cargar el schema GraphQL:', error)
      }
    },

    /** Metadata por nombre (`VueRoute`) o slug (`vue-route`); `null` si no existe. */
    find(name: string): EntitySchema | null {
      return this.entities[name] ?? this.entities[entityNameFromSlug(name)] ?? null
    },

    /** Como `find`, pero lanza si la entidad no existe. */
    require(name: string): EntitySchema {
      const entity = this.find(name)
      if (!entity) throw new Error(`No existe la entidad: ${name}`)
      return entity
    },
  },
})
