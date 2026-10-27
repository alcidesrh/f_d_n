/**
 * Registro de stores de entidad: `getEntity("Bus")` devuelve siempre la misma
 * instancia (la crea la primera vez y dispara la carga de su configuración).
 * Al descartar las stores del usuario (`alcanceSesion`) el registro se vacía:
 * la próxima llamada crea la instancia del usuario que entra.
 * Acepta nombre (`VueRoute`) o slug (`vue-route`); lanza si la entidad no existe.
 */
import { alDescartar, olvidarDeTodos } from '@/core/auth/alcanceSesion'
import { defineEntityStore, olvidarDefinicion } from './entityStore'
import { useSchemaStore } from './schema'
import type { EntityStore } from './types'

export const entityStores = new Map<string, EntityStore>()
alDescartar(() => entityStores.clear())

export function getEntity<T = unknown>(name: string): EntityStore<T> {
  const entity = useSchemaStore().require(name)
  let store = entityStores.get(entity.name)
  if (!store) {
    store = defineEntityStore(entity.name)() as unknown as EntityStore
    entityStores.set(entity.name, store)
    void store.init()
  }
  return store as EntityStore<T>
}

/**
 * La entidad ya no está en el schema (ADR-036): suelta su store y lo que
 * guardó de ella cada usuario del navegador.
 */
export function olvidarEntidad(name: string): void {
  const store = entityStores.get(name) as (EntityStore & { $dispose?: () => void }) | undefined
  entityStores.delete(name)
  store?.$dispose?.()
  olvidarDefinicion(name)
  olvidarDeTodos(`entity:${name}`)
}

/**
 * Cambiaron los roles del usuario: la configuración de cada listado que le
 * toca puede ser otra (ADR-043). Las stores vivas la vuelven a pedir.
 */
export function configuracionesVencidas(): void {
  for (const store of entityStores.values()) {
    store.revisionConfig = null
    void store.init()
  }
}
