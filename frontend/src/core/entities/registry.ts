/**
 * Registro de stores de entidad: `getEntity("Bus")` devuelve siempre la misma
 * instancia (la crea la primera vez y dispara la carga de su configuración).
 * Acepta nombre (`VueRoute`) o slug (`vue-route`); lanza si la entidad no existe.
 */
import { defineEntityStore } from "./entityStore";
import { useSchemaStore } from "./schema";
import type { EntityStore } from "./types";

export const entityStores = new Map<string, EntityStore>();

export function getEntity<T = unknown>(name: string): EntityStore<T> {
  const entity = useSchemaStore().require(name);
  let store = entityStores.get(entity.name);
  if (!store) {
    store = defineEntityStore(entity.name)() as unknown as EntityStore;
    entityStores.set(entity.name, store);
    void store.init();
  }
  return store as EntityStore<T>;
}
