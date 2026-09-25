/**
 * `useMenuBuilderStore` — estado del editor de menús (ADR-018): catálogo
 * (menús, ítems navegables, roles, rutas), el esquema con sangría del menú
 * seleccionado y la distribución de menús por área del shell. Cada parte
 * guarda por separado y tiene su propio snapshot para `dirty`.
 *
 * Tras cada guardado recarga los menús del usuario para que el shell refleje
 * el cambio sin recargar la página.
 */
import { defineStore } from 'pinia'
import { useUserMenusStore, type LayoutArea } from '@/core/navigation/userMenus'
import {
  deleteMenu,
  deleteMenuItem,
  ensureIcon,
  fetchCatalog,
  fetchLayout,
  fetchTree,
  numericId,
  saveLayout,
  saveMenu,
  saveMenuItem,
  saveTree,
  type MenuDto,
  type MenuItemDto,
  type MenuLayoutDto,
  type RoleDto,
  type VueRouteDto,
} from './api'
import { flatten, liftRow, toTree, type OutlineRow } from './outline'

export interface MenuItemDraft {
  id?: string
  nombre: string
  /** Nombre de ícono Tabler (el `Icon` se busca o se crea al guardar). */
  icon: string
  /** IRI de la `VueRoute`. */
  route: string
}

export interface MenuDraft {
  id?: string
  nombre: string
  /** IRIs de `Role`. */
  roles: string[]
}

const emptyLayout = (): MenuLayoutDto => ({ sidebar_left: [], topbar_right: [], sidebar_right: [] })
const treeSnapshot = (rows: OutlineRow[]) => JSON.stringify(toTree(rows))

export const useMenuBuilderStore = defineStore('menuBuilder', {
  state: () => ({
    menus: [] as MenuDto[],
    items: [] as MenuItemDto[],
    roles: [] as RoleDto[],
    routes: [] as VueRouteDto[],
    selectedMenuId: null as number | null,
    rows: [] as OutlineRow[],
    treeBaseline: '',
    layout: emptyLayout(),
    layoutBaseline: '',
    status: 'idle' as 'idle' | 'loading' | 'ready' | 'error',
    treeLoading: false,
    saving: false,
    error: '',
  }),

  getters: {
    itemsById: (st): Map<number, MenuItemDto> =>
      new Map(st.items.map((item) => [numericId(item.id), item])),
    menusById: (st): Map<number, MenuDto> =>
      new Map(st.menus.map((menu) => [numericId(menu.id), menu])),
    selectedMenu(): MenuDto | null {
      return this.selectedMenuId === null ? null : (this.menusById.get(this.selectedMenuId) ?? null)
    },
    /** Ítems que todavía no están en el menú seleccionado (la paleta). */
    paletteItems(st): MenuItemDto[] {
      const used = new Set(st.rows.map((row) => row.id))
      return st.items.filter((item) => !used.has(numericId(item.id)))
    },
    treeDirty: (st): boolean => st.treeBaseline !== '' && st.treeBaseline !== treeSnapshot(st.rows),
    layoutDirty: (st): boolean =>
      st.layoutBaseline !== '' && st.layoutBaseline !== JSON.stringify(st.layout),
  },

  actions: {
    async load(): Promise<void> {
      this.status = 'loading'
      this.error = ''
      try {
        const [catalog, layout] = await Promise.all([fetchCatalog(), fetchLayout()])
        // Apollo entrega los resultados congelados: copias para poder hacer push/splice.
        this.menus = [...catalog.menus]
        this.items = [...catalog.menuItems]
        this.roles = catalog.roles
        this.routes = catalog.vueRoutes.filter((route) => route.vueRouteName)
        this.applyLayout(layout)
        this.status = 'ready'
        const first = this.menus[0]
        if (this.selectedMenuId === null && first) await this.selectMenu(numericId(first.id))
      } catch (error) {
        this.status = 'error'
        this.error = error instanceof Error ? error.message : String(error)
      }
    },

    async selectMenu(menuId: number | null): Promise<void> {
      this.selectedMenuId = menuId
      this.applyTree([])
      this.treeBaseline = ''
      if (menuId === null) return
      this.treeLoading = true
      try {
        this.applyTree(flatten(await fetchTree(menuId)))
      } finally {
        this.treeLoading = false
      }
    },

    applyTree(rows: OutlineRow[]): void {
      this.rows = rows
      this.treeBaseline = treeSnapshot(rows)
    },

    applyLayout(layout: MenuLayoutDto): void {
      this.layout = { ...emptyLayout(), ...layout }
      this.layoutBaseline = JSON.stringify(this.layout)
    },

    async saveTree(): Promise<void> {
      if (this.selectedMenuId === null || this.saving) return
      this.saving = true
      try {
        this.applyTree(flatten(await saveTree(this.selectedMenuId, toTree(this.rows))))
        void useUserMenusStore().load()
      } finally {
        this.saving = false
      }
    },

    /** Crea o actualiza nombre y roles de un menú; uno nuevo queda seleccionado. */
    async saveMenu(draft: MenuDraft): Promise<MenuDto> {
      const saved = await saveMenu(draft)
      const index = this.menus.findIndex((menu) => menu.id === saved.id)
      if (index >= 0) this.menus.splice(index, 1, saved)
      else {
        this.menus.push(saved)
        await this.selectMenu(numericId(saved.id))
      }
      void useUserMenusStore().load()
      return saved
    },

    async removeMenu(menu: MenuDto): Promise<void> {
      await deleteMenu(menu.id)
      const id = numericId(menu.id)
      this.menus = this.menus.filter((item) => item.id !== menu.id)
      // Sus colocaciones se borran en cascada: se reflejan en la distribución guardada.
      const layout = Object.fromEntries(
        Object.entries(this.layout).map(([area, ids]) => [
          area,
          ids.filter((other) => other !== id),
        ]),
      ) as MenuLayoutDto
      const pending = this.layoutDirty
      this.layout = layout
      if (!pending) this.layoutBaseline = JSON.stringify(layout)
      if (this.selectedMenuId === id) {
        const first = this.menus[0]
        await this.selectMenu(first ? numericId(first.id) : null)
      }
      void useUserMenusStore().load()
    },

    async saveItem(draft: MenuItemDraft): Promise<MenuItemDto> {
      const icon = await ensureIcon(draft.icon)
      const saved = await saveMenuItem({ ...draft, icon })
      const index = this.items.findIndex((item) => item.id === saved.id)
      if (index >= 0) this.items.splice(index, 1, saved)
      else this.items.push(saved)
      if (draft.id) void useUserMenusStore().load()
      return saved
    },

    /** Borra el ítem; en el menú abierto sus hijos suben a su lugar (igual que en el backend). */
    async removeItem(item: MenuItemDto): Promise<void> {
      await deleteMenuItem(item.id)
      const id = numericId(item.id)
      this.items = this.items.filter((other) => other.id !== item.id)
      const index = this.rows.findIndex((row) => row.id === id)
      if (index >= 0) {
        const pending = this.treeDirty
        this.rows = liftRow(this.rows, index)
        if (!pending) this.treeBaseline = treeSnapshot(this.rows)
      }
      void useUserMenusStore().load()
    },

    setArea(area: LayoutArea, menuIds: number[]): void {
      this.layout = { ...this.layout, [area]: menuIds }
    },

    async saveLayout(): Promise<void> {
      if (this.saving) return
      this.saving = true
      try {
        this.applyLayout(await saveLayout(this.layout))
        void useUserMenusStore().load()
      } finally {
        this.saving = false
      }
    },
  },
})
