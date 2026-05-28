import type { Role } from "~/types/role";
import { defineStore } from "pinia";

export const useRoleStore = defineStore("roleStore", {
  state: () => createStore<Role>("Role"),
  persist: {
    ...persist,
    afterHydrate: (ctx) => {
      if (ctx.store.items.length == 0) {
        ctx.store.getItems();
      }
    },
  },
});
