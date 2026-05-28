import type { Localidad } from "@/types/localidad";
import { defineStore } from "pinia";

export const useLocalidadStore = defineStore("localidadStore", {
  state: () => createStore<Role>("Localidad"),
  persist: {
    ...persist,
    afterHydrate: (ctx) => {
      if (ctx.store.items.length == 0) {
        ctx.store.getItems();
      }
    },
  },
});
