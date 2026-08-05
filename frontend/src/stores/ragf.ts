import { defineStore } from "pinia";
import { createRagf, httpErrorMessage } from "@/ragf";
import type { RagfInstance, RagfOptions, RagfStatus } from "@/ragf/types";

/**
 * RAGF lifecycle store. Owns the single runtime instance for the app and
 * tracks bootstrap status so views can wait for the schema/metadata layer
 * (ready flag) before rendering dynamic CRUD.
 */
export const useRagf = defineStore("ragf", {
  state: () => ({
    status: "idle" as RagfStatus,
    error: "" as string,
    instance: null as RagfInstance | null,
  }),

  getters: {
    ready: (st): boolean => st.status === "ready",
    client: (st) => st.instance?.graphql ?? null,
    config: (st) => st.instance?.config ?? null,
  },

  actions: {
    async bootstrap(options: RagfOptions = {}) {
      if (this.instance) return;
      this.status = "loading";
      this.error = "";
      const instance = createRagf(options);
      this.instance = instance;
      try {
        const ok = await instance.ping();
        if (!ok) {
          throw new Error("GraphQL enpoint responded but returned no data");
        }
        this.status = "ready";
      } catch (error) {
        this.status = "error";
        this.error = httpErrorMessage(error);
        options.onError?.(error);
        console.warn("[ragf] bootstrap failed:", error);
      }
    },
    /** Re-runs the connectivity check without rebuilding the transports. */
    async ping() {
      const instance = this.instance;
      if (!instance) return false;
      try {
        return await instance.ping();
      } catch (error) {
        this.error = httpErrorMessage(error);
        return false;
      }
    },
  },
});