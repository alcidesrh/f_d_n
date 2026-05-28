import { defineStore } from "pinia";
import api from "src/services/api";
import type { User } from "src/types/user";

interface State {
  deleted?: User;
  mercureDeleted?: User;
  isLoading: boolean;
  error?: string;
}

export const useUserDeleteStore = defineStore("userDelete", {
  state: (): State => ({
    deleted: undefined,
    mercureDeleted: undefined,
    isLoading: false,
    error: undefined,
  }),

  actions: {
    async deleteItem(item: User) {
      this.toggleLoading();

      if (!item?.["@id"]) {
        this.setError("No user found. Please reload");
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

    setDeleted(deleted: User) {
      this.deleted = deleted;
    },

    setMercureDeleted(mercureDeleted?: User) {
      this.mercureDeleted = mercureDeleted;
    },

    setError(error: string) {
      this.error = error;
    },
  },
});
