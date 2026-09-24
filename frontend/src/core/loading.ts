/**
 * Contador global de peticiones en curso (barra de carga, spinners). Cada
 * petición se registra con una `key` para poder preguntar por una operación
 * concreta (`isLoading("login")`).
 */
import { defineStore } from 'pinia'

export const useLoadingStore = defineStore('loading', {
  state: () => ({ ops: {} as Record<string, number> }),
  getters: {
    /** Peticiones en curso (todas las keys). */
    count: (st) => Object.values(st.ops).reduce((sum, n) => sum + n, 0),
    loading: (st) => Object.keys(st.ops).length > 0,
    isLoading: (st) => (key: string) => (st.ops[key] ?? 0) > 0,
  },
  actions: {
    start(key = 'anonymous') {
      this.ops[key] = (this.ops[key] ?? 0) + 1
    },
    stop(key = 'anonymous') {
      const count = (this.ops[key] ?? 0) - 1
      if (count > 0) this.ops[key] = count
      else delete this.ops[key]
    },
  },
})
