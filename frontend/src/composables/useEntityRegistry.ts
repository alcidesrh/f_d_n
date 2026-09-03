/**
 * `useEntityRegistry` — registro de stores de entidades dinámicos.
 *
 * `getEntity(name)` devuelve `use{Name}Store` (el store de la entidad). Si
 * no existe todavía, lo crea por demanda (vía `defineEntityStore`) y lo
 * registra; las siguientes llamadas devuelven la misma instancia. Al crear
 * un store nuevo dispara la carga de columnas del listado.
 */

import { defineEntityStore } from "@/stores/entities/factory";
import type { EntityStore } from "@/stores/entities/types";
import router from "@/router";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import type { EntitySchema } from "@/lib/apollo";

export const stores = new Map<string, EntityStore<unknown>>();

export function getEntity<T = unknown>(entityName?: string): EntityStore<T> {
  const schemaRepo = useSchemaRepositoryStore();
  let entity: EntitySchema | null = null;
  if (
    !(entity = schemaRepo.getEntityMetadata(entityName || router.currentRoute.value.params?.entity))
  ) {
    throw new Error(`No existe la entidad: ${entityName}`);
    return null;
  }
  const existing = stores.get(entity.name);
  if (existing) return existing as unknown as EntityStore<T>;

  const store = defineEntityStore(entity.name)() as unknown as EntityStore<T>;
  stores.set(entity.name, store as unknown as EntityStore<unknown>);
  void store.loadColumns();
  return store;
}

export function useEntityRegistry() {
  return {
    getEntity,
  };
}
