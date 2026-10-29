/**
 * Menús de navegación del usuario actual (`GET /api/me/menus`): por área del
 * shell, los menús que sus roles pueden ver, en orden, con su árbol de ítems.
 * Vive en `core/` porque lo leen el shell (`app/layout`) y lo recarga el
 * editor de menús (`features/menu-builder`) tras guardar.
 */
import { defineStore } from 'pinia'
import type { RouteLocationRaw } from 'vue-router'
import { podarMenu } from '@/core/acceso/reglas'
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
  /** Ruta del router; nula si el ítem es un enlace o un grupo. */
  route: NavRoute | null
  /** Enlace escrito a mano (`/salidas/chequeo`); nulo si navega por ruta o es un grupo. */
  path: string | null
  /** Con `route` y `path` nulos es un grupo: agrupa a sus hijos y no navega. */
  children: NavItem[]
}

export interface NavMenu {
  id: number
  nombre: string
  items: NavItem[]
}

export type MenusByArea = Record<LayoutArea, NavMenu[]>

const emptyAreas = (): MenusByArea => ({ sidebar_left: [], topbar_right: [], sidebar_right: [] })

/** Qué pantallas puede abrir el usuario (lo fija el shell con `core/acceso`). */
let filtro: ((destino: RouteLocationRaw) => boolean) | undefined

export function filtrarMenusCon(permitido: ((destino: RouteLocationRaw) => boolean) | undefined): void {
  filtro = permitido
}

export const useUserMenusStore = defineStore('userMenus', {
  state: () => ({
    areas: emptyAreas(),
    loaded: false,
  }),

  actions: {
    /**
     * Carga (o recarga) los menús; un fallo deja las áreas vacías sin romper
     * el shell. Con `permitido`, sin los ítems de pantallas que el usuario no
     * puede abrir (ADR-039) ni los menús que se quedan vacíos.
     */
    async load(permitido: ((destino: RouteLocationRaw) => boolean) | undefined = filtro): Promise<void> {
      try {
        const data = { ...emptyAreas(), ...(await http.get<Partial<MenusByArea>>('/me/menus', { silent: true })) }
        if (permitido) {
          for (const area of LAYOUT_AREAS) {
            data[area] = data[area].map((m) => ({ ...m, items: podarMenu(m.items, permitido) })).filter((m) => m.items.length > 0)
          }
        }
        this.areas = data
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
