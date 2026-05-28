import { defineStore } from "pinia";
import api from "src/services/api";
import type { Role } from "src/types/role";

interface State {
  deleted?: Role;
  mercureDeleted?: Role;
  isLoading: boolean;
  error?: string;
}

export const useRoleDeleteStore = defineStore("roleDelete", {
  state: (): State => ({
    deleted: undefined,
    mercureDeleted: undefined,
    isLoading: false,
    error: undefined,
  }),

  actions: {
    async deleteItem(item: Role) {
      this.toggleLoading();

      if (!item?.["@id"]) {
        this.setError("No role found. Please reload");
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

    setDeleted(deleted: Role) {
      this.deleted = deleted;
    },

    setMercureDeleted(mercureDeleted?: Role) {
      this.mercureDeleted = mercureDeleted;
    },

    setError(error: string) {
      this.error = error;
    },
  },
});
