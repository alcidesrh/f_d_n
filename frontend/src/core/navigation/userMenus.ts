/**
 * Menús de navegación del usuario actual (`GET /api/me/menus`): por área del
 * shell, los menús que sus roles pueden ver, en orden, con su árbol de ítems.
 * Vive en `core/` porque lo leen el shell (`app/layout`) y lo recarga el
 * editor de menús (`features/menu-builder`) tras guardar.
 */
import { defineStore } from 'pinia'
import { http } from '@/core/http'

/** Áreas del shell (`LayoutArea` del backend), una por slot `menu-content`. */
export const LAYOUT_AREAS = ['sidebar_left', 'topbar_right', 'sidebar_right'] as const
export type LayoutArea = (typeof LAYOUT_AREAS)[number]

export const AREA_LABELS: Record<LayoutArea, string> = {
  sidebar_left: 'Barra lateral izquierda',
  topbar_right: 'Cabecera',
  sidebar_right: 'Barra lateral derecha',
}

export interface NavRoute {
  id: number
  /** `name` de vue-router. */
  name: string | null
  path: string | null
  params: string[]
}

export interface NavItem {
  id: number
  label: string
  icon: string | null
  route: NavRoute
  children: NavItem[]
}

export interface NavMenu {
  id: number
  nombre: string
  items: NavItem[]
}

export type MenusByArea = Record<LayoutArea, NavMenu[]>

const emptyAreas = (): MenusByArea => ({ sidebar_left: [], topbar_right: [], sidebar_right: [] })

export const useUserMenusStore = defineStore('userMenus', {
  state: () => ({
    areas: emptyAreas(),
    loaded: false,
  }),

  actions: {
    /** Carga (o recarga) los menús; un fallo deja las áreas vacías sin romper el shell. */
    async load(): Promise<void> {
      try {
        const data = await http.get<Partial<MenusByArea>>('/me/menus', { silent: true })
        this.areas = { ...emptyAreas(), ...data }
      } catch (error) {
        this.areas = emptyAreas()
        console.warn('[menus] no se pudieron cargar los menús del usuario:', error)
      } finally {
        this.loaded = true
      }
    },

    clear(): void {
      this.areas = emptyAreas()
      this.loaded = false
    },
  },
})
