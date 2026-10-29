/**
 * Metadata de las entidades de la API: la introspección GraphQL aplanada por
 * entidad (`EntitySchema`). Se persiste y se reusa; se vuelve a pedir cuando
 * el manifiesto dice que el schema del servidor es otro (ADR-036, ver
 * `core/metadata/sincronizacion`). Subir `SCHEMA_VERSION` invalida la copia
 * persistida cuando cambia su formato.
 */
import { defineStore } from 'pinia'
import { graphql } from '@/core/graphql/client'
import type { EntitySchema } from '@/core/graphql/types'
import { diferenciasEsquema, huellasDe, type DiferenciasEsquema } from '@/core/metadata/huellas'
import type { Manifiesto } from '@/core/metadata/manifiesto'
import { entityNameFromSlug } from './slug'

export const SCHEMA_VERSION = 15

/** Introspección en curso: la piden el arranque, los avisos y la red de seguridad a la vez. */
let recarga: Promise<DiferenciasEsquema | null> | null = null

export const useSchemaStore = defineStore('schema', {
  global: true,
  persist: { pick: ['entities', 'schemaVersion', 'loadedAt', 'huellas', 'huellaServidor', 'revision'] },

  state: () => ({
    status: 'idle' as 'idle' | 'loading' | 'ready' | 'error',
    error: '',
    entities: {} as Record<string, EntitySchema>,
    schemaVersion: SCHEMA_VERSION,
    loadedAt: null as string | null,
    /** Huella de cada entidad de `entities` (`core/metadata/huellas`). */
    huellas: {} as Record<string, string>,
    /** Huella del schema del servidor según el manifiesto con que se pidió `entities`; null: no se sabe. */
    huellaServidor: null as string | null,
    /** Revisión de ese manifiesto. */
    revision: null as number | null,
  }),

  getters: {
    ready: (st) => st.status === 'ready',
  },

  actions: {
    /**
     * Reutiliza la introspección persistida o la pide. Con el manifiesto, la
     * vuelve a pedir si el schema del servidor cambió desde que se guardó.
     */
    async init(manifiesto: Manifiesto | null = null) {
      if (this.status === 'loading') return
      if (this.schemaVersion !== SCHEMA_VERSION) this.entities = {}
      if (Object.keys(this.entities).length > 0 && !(manifiesto && this.desactualizado(manifiesto))) {
        // Copia guardada antes de las huellas.
        if (Object.keys(this.huellas).length === 0) this.huellas = huellasDe(this.entities)
        this.status = 'ready'
        return
      }
      await this.recargar(manifiesto)
    },

    /** El schema guardado no es el del manifiesto (o se pidió recargar todo después). */
    desactualizado(manifiesto: Manifiesto): boolean {
      return this.huellaServidor !== manifiesto.esquema.huella || (this.revision ?? 0) < manifiesto.completa
    },

    /**
     * Vuelve a pedir la introspección y devuelve qué entidades cambiaron. Si
     * falla se queda con la que tenía (null).
     */
    recargar(manifiesto: Manifiesto | null = null): Promise<DiferenciasEsquema | null> {
      recarga ??= (async () => {
        const primera = Object.keys(this.entities).length === 0
        if (primera) {
          this.status = 'loading'
          this.error = ''
        }
        try {
          const antes = primera ? {} : Object.keys(this.huellas).length > 0 ? this.huellas : huellasDe(this.entities)
          const entities = await graphql.introspect()
          const huellas = huellasDe(entities)
          const diferencias = diferenciasEsquema(antes, huellas)
          this.entities = entities
          this.huellas = huellas
          this.huellaServidor = manifiesto?.esquema.huella ?? null
          this.revision = manifiesto?.revision ?? null
          this.loadedAt = new Date().toISOString()
          this.schemaVersion = SCHEMA_VERSION
          this.status = 'ready'
          return diferencias
        } catch (error) {
          if (primera) {
            this.status = 'error'
            this.error = error instanceof Error ? error.message : String(error)
          }
          console.error('[schema] no se pudo cargar el schema GraphQL:', error)
          return null
        }
      })().finally(() => {
        recarga = null
      })
      return recarga
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
