/**
 * `useEntityRegistry` — registro de stores de entidades dinámicos.
 *
 * `getEntity(name)` devuelve `use{Name}Store` (el store de la entidad). Si
 * no existe todavía, lo crea por demanda (vía `defineEntityStore`) y lo
 * registra; las siguientes llamadas devuelven la misma instancia. Al crear
 * un store nuevo dispara la carga de columnas del listado.
 */

import { defineEntityStore } from "./entityStore";
import type { EntityStore } from "./types";
import { router } from "@/app/router";
import type { EntitySchema } from "@/core/graphql/types";
import { notify } from "@/core/notify";
import { useSchemaRepositoryStore } from "./schema";

export const stores = new Map<string, EntityStore<unknown>>();

export function getEntity<T = unknown>(entityName?: string): EntityStore<T> {
  let entity: EntitySchema | null = null;
  if (!(entity = useSchemaRepositoryStore().getEntityMetadata(entityName || router.currentRoute.value.params?.entity))) {
    notify.error(`No existe la entidad: ${entityName}`);
    throw new Error(`No existe la entidad: ${entityName}`);
    return null;
  }
  const existing = stores.get(entity.name);
  if (existing) return existing as unknown as EntityStore<T>;

  const store = defineEntityStore(entity.name)() as unknown as EntityStore<T>;
  stores.set(entity.name, store as unknown as EntityStore<unknown>);
  void store.init();
  return store;
}

export function useEntityRegistry() {
  return {
    getEntity,
  };
}
