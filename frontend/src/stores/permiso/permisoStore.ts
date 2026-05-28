import type { Permiso } from "~/types/permiso";
import { defineStore } from "pinia";

export const usePermisoStore = defineStore(
  "permisoStore",
  () => {
    // const { collection, schema, collection, remove, removeMultiple, resource, items, getItems, entity, iniCollection, sortCollection, submit } =
    return createStore<Permiso>("Permiso");
  },
  // persist,
);
