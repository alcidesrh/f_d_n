import { defineStore } from "pinia";

export interface SidebarState {
  mode: Ref<string>;
  position: string;
  w: string;
  t: number;
}

const definedStores = new Map<string, ReturnType<typeof defineSidebarStore>>();

function sidebarStoreFactory(storeId: string, position: string) {
  if (!definedStores.has(storeId)) {
    definedStores.set(storeId, defineSidebarStore(storeId, position));
  }

  return definedStores.get(storeId) as ReturnType<typeof defineSidebarStore>;
}
function defineSidebarStore<Id extends string, Position extends string>(
  storeId: Id,
  position: Position,
) {
  const $q = useQuasar();

  return defineStore(storeId, {
    persist: true,
    state: (): SidebarState => {
      return {
        mode: position === "right" ? "close" : ($q.screen.lt.sm ? "close" : "large"),
        position,
        w: 200,
        t: 70,
      };
    },
    getters: {
      modeStates: (state) => {
        return {
          large: `large`,
          mini: `mini`,
          onhover: `onhover`,
          close: `close`,
          prev: "",
        };
      },
    },
    actions: {
      setMode(mode) {
        this.modeStates.prev = this.mode;
        this.mode = mode || this.modeStates.large;
      },
    },
    /* ... */
  });
}
export function useSidebarStore(storeId: string, position = "left") {
  return sidebarStoreFactory(storeId, position)();
}
