import { defineStore } from "pinia";
import api from "src/services/api";
import type { Permiso } from "src/types/permiso";

interface State {
  deleted?: Permiso;
  mercureDeleted?: Permiso;
  isLoading: boolean;
  error?: string;
}

export const usePermisoDeleteStore = defineStore("permisoDelete", {
  state: (): State => ({
    deleted: undefined,
    mercureDeleted: undefined,
    isLoading: false,
    error: undefined,
  }),

  actions: {
    async deleteItem(item: Permiso) {
      this.toggleLoading();

      if (!item?.["@id"]) {
        this.setError("No permiso found. Please reload");
        return;
      }

      try {
        await api(item["@id"], { method: "DELETE" });

        this.toggleLoading();
        this.setDeleted(item);
        this.setMercureDeleted(undefined);
      } catch (error) {
        this.toggleLoading();

        if (error instanceof Error) {
          this.setError(error.message);
        }
      }
    },

    toggleLoading() {
      this.isLoading = !this.isLoading;
    },

    setDeleted(deleted: Permiso) {
      this.deleted = deleted;
    },

    setMercureDeleted(mercureDeleted?: Permiso) {
      this.mercureDeleted = mercureDeleted;
    },

    setError(error: string) {
      this.error = error;
    },
  },
});
