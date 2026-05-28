import { defineStore } from "pinia";

export interface Menu {
  toggle: Ref<boolean>;
  menu: Ref<Array<any>>;
}

const definedStores = new Map<string, ReturnType<typeof createStore>>();

function storeFactory(storeId: string, menu: Array<any>) {
  if (!definedStores.has(storeId)) {
    definedStores.set(storeId, createStore(storeId, menu));
  }

  return definedStores.get(storeId) as ReturnType<typeof createStore>;
}
function createStore(storeId: string, menu: Array<any>) {
  return defineStore(storeId, {
    persist: true,
    state: (): Menu => {
      return {
        toggle: true,
        menu: menu,
      };
    },
  });
}
export function useMenuStateStore(storeId: string, menu: Array<any>) {
  return storeFactory(storeId, menu)();
}
