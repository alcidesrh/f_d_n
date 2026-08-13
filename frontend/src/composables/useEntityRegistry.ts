/**
 * `useEntityRegistry` — registro de stores de entidades dinámicos.
 *
 * `getEntity(name)` devuelve `use{Name}Store` (el store de la entidad). Si
 * no existe todavía, lo crea por demanda (vía `defineEntityStore`) y lo
 * registra; las siguientes llamadas devuelven la misma instancia. Al crear
 * un store nuevo dispara la carga de columnas del listado.
 */

import { defineEntityStore } from '@/stores/entities/factory'
import type { EntityStore } from '@/stores/entities/types'

const stores = new Map<string, EntityStore<unknown>>()

export function getEntity<T = unknown>(entityName: string): EntityStore<T> {
  const existing = stores.get(entityName)
  if (existing) return existing as unknown as EntityStore<T>

  const store = defineEntityStore(entityName)() as unknown as EntityStore<T>
  stores.set(entityName, store as unknown as EntityStore<unknown>)
  void store.loadColumns()
  return store
}

export function useEntityRegistry() {
  return {
    getEntity,
  }
}
