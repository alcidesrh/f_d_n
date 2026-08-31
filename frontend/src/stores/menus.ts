import { defineStore } from "pinia";
const API_BASE = import.meta.env.VITE_REST_ENDPOINT ?? "http://localhost/api";

export interface MenuItem {
  id: string | number;
  nombre: string;
  label: string;
  icon: string | null;
  sort: number | null;
  ruta: string | null;
  routeName: string | null;
  children: MenuItem[];
}

export interface MenuArea {
  area: string;
  items: MenuItem[];
}

export interface MenusState {
  areas: MenuArea[];
  loading: boolean;
  fetched: boolean;
  error: string;
}

export const useMenusStore = defineStore("menus", {
  persist: true,

  state: (): MenusState => ({
    areas: [],
    loading: false,
    fetched: false,
    error: "",
  }),

  getters: {
    getMenuByArea:
      (st) =>
      (area: string): MenuItem[] => {
        const found = st.areas.find((a) => a.area === area);
        return found?.items ?? [];
      },

    sidebarLeftItems: (st): MenuItem[] => {
      const found = st.areas.find((a) => a.area === "sidebar_left");
      return found?.items ?? [];
    },

    sidebarRightItems: (st): MenuItem[] => {
      const found = st.areas.find((a) => a.area === "sidebar_right");
      return found?.items ?? [];
    },

    topbarRightItems: (st): MenuItem[] => {
      const found = st.areas.find((a) => a.area === "topbar_right");
      return found?.items ?? [];
    },
  },

  actions: {
    async fetchMenusByArea(): Promise<void> {
      if (this.loading) return;
      this.loading = true;
      this.error = "";
      try {
        const res = await fetch(`${API_BASE}/menus-by-area`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data: { areas: MenuArea[] } = await res.json();
        this.areas = data.areas ?? [];
        this.fetched = true;
      } catch (e) {
        this.error = e instanceof Error ? e.message : String(e);
        console.error("[menus] Error fetching menus by area:", e);
      } finally {
        this.loading = false;
      }
    },

    invalidate(): void {
      this.areas = [];
      this.fetched = false;
    },
  },
});
