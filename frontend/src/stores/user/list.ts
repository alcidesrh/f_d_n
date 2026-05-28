import { defineStore } from "pinia";
import api from "src/services/api";
import { extractHubURL } from "src/utils/mercure";
import type { PagedCollection } from "src/types/collection";
import type { ListParams } from "src/types/list";
import type { User } from "src/types/user";
import type { View } from "src/types/view";

interface State {
  items: User[];
  totalItems: number;
  hubUrl?: URL;
  isLoading: boolean;
  view?: View;
  error?: string;
}

export const useUserListStore = defineStore("userList", {
  state: (): State => ({
    items: [],
    totalItems: 0,
    hubUrl: undefined,
    isLoading: false,
    view: undefined,
    error: undefined,
  }),

  actions: {
    async getItems(page: string, params: ListParams) {
      this.toggleLoading();

      try {
        const path = page ? `users?page=${page}` : "users";
        const response = await api(path, { params });
        const data: PagedCollection<User> = await response.json();
        const hubUrl = extractHubURL(response);

        this.toggleLoading();

        this.setItems(data["member"]);
        this.setTotalItems(data["totalItems"] ?? 0);
        this.setView(data["view"]);

        if (hubUrl) {
          this.setHubUrl(hubUrl);
        }
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

    setItems(items: User[]) {
      this.items = items;
    },

    setTotalItems(totalItems: number) {
      this.totalItems = totalItems;
    },

    setHubUrl(hubUrl: URL) {
      this.hubUrl = hubUrl;
    },

    setView(view: View) {
      this.view = view;
    },

    setError(error: string) {
      this.error = error;
    },

    updateItem(updatedItem: User) {
      const item: User | undefined = this.items.find(
        (i) => i["@id"] === updatedItem["@id"],
      );

      if (!item) return;

      Object.assign(item, updatedItem);
    },

    deleteItem(deletedItem: User) {
      this.items = this.items.filter((item) => {
        return item["@id"] !== deletedItem["@id"];
      });
    },
  },
});
