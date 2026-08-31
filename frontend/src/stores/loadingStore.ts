// src/stores/loading.ts
export const useLoadingStore = defineStore("loading", {
  state: () => ({
    globalCount: 0,
    ops: new Map<string, { count: number; priority: number }>(),
  }),

  getters: {
    loading: (s) => s.globalCount > 0,
    isOpLoading: (s) => (key: string) => (s.ops.get(key)?.count ?? 0) > 0,
    highestPriority: (s) => {
      let p = 0;
      s.ops.forEach((v) => {
        if (v.count > 0) p = Math.max(p, v.priority);
      });
      return p;
    },
  },
  actions: {
    start(key: string, priority = 1) {
      this.globalCount++;
      const rec = this.ops.get(key) ?? { count: 0, priority };
      rec.count++;
      rec.priority = Math.max(rec.priority, priority);
      this.ops.set(key, rec);
    },
    stop(key: string) {
      if (this.globalCount > 0) this.globalCount--;
      const rec = this.ops.get(key);
      if (!rec) return;
      rec.count--;
      if (rec.count <= 0) this.ops.delete(key);
      else this.ops.set(key, rec);
    },
    reset() {
      this.globalCount = 0;
      this.ops.clear();
    },
  },
});
