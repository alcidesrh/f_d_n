import type { Action } from "~/types/action";
import { defineStore } from "pinia";

export const useActionStore = defineStore("actionStore", {
  state: () => createStore<Action>("Action"),
  persist: {
    ...persist,
    afterHydrate: (ctx) => {
      if (ctx.store.items.length == 0) {
        ctx.store.getItems();
      }
    },
  },
});
