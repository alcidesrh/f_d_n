/**
 * Transporte del editor de menús (ADR-018).
 *
 * - GraphQL (CRUD genérico): `Menu` (nombre, roles), `MenuItem` (nombre,
 *   ícono, ruta) y catálogos (roles, rutas). Los ids que devuelve son IRIs.
 * - REST (`MenuController`): el árbol de cada menú y la distribución de
 *   menús por área, que trabajan con ids numéricos.
 *
 * Lecturas `network-only`: se editan en esta misma pantalla.
 */
import { gql } from '@apollo/client'
import { graphql } from '@/core/graphql/client'
import { http, request } from '@/core/http'
import type { LayoutArea } from '@/core/navigation/userMenus'
import type { TreeNode } from './outline'

export interface RoleDto {
  id: string
  nombre: string
}

export interface VueRouteDto {
  id: string
  nombre: string
  vueRouteName: string | null
  path: string | null
  params: string[] | null
}

export interface MenuItemDto {
  id: string
  nombre: string
  icon: { id: string; icon: string }
  route: VueRouteDto
}

export interface MenuDto {
  id: string
  nombre: string
  roles: RoleDto[]
}

export interface CatalogDto {
  menus: MenuDto[]
  menuItems: MenuItemDto[]
  roles: RoleDto[]
  vueRoutes: VueRouteDto[]
}

export type MenuLayoutDto = Record<LayoutArea, number[]>

/** Id numérico de un IRI (`/api/menus/7` → 7). */
export const numericId = (iri: string) => Number(iri.split('/').pop())

const ROUTE_FIELDS = 'id nombre vueRouteName path params'
const ITEM_FIELDS = `id nombre icon { id icon } route { ${ROUTE_FIELDS} }`
const MENU_FIELDS = 'id nombre roles { id nombre }'

const CATALOG_QUERY = gql`
  query MenuBuilderCatalog {
    menus { ${MENU_FIELDS} }
    menuItems { ${ITEM_FIELDS} }
    roles { id nombre }
    vueRoutes { ${ROUTE_FIELDS} }
  }
`

export async function fetchCatalog(): Promise<CatalogDto> {
  const result = await graphql.client.query<CatalogDto>({
    query: CATALOG_QUERY,
    fetchPolicy: 'network-only',
  })
  const data = result.data
  return {
    menus: data?.menus ?? [],
    menuItems: data?.menuItems ?? [],
    roles: data?.roles ?? [],
    vueRoutes: data?.vueRoutes ?? [],
  }
}

/**
 * Ejecuta `operation(input: $input) { field { selection } }` y devuelve
 * `field` (p. ej. `createMenu` → `menu`).
 */
async function mutate<T>(
  operation: string,
  field: string,
  selection: string,
  input: Record<string, unknown>,
): Promise<T> {
  const result = await graphql.client.mutate<Record<string, Record<string, T> | null>>({
    mutation: gql(
      `mutation($input: ${operation}Input!) { ${operation}(input: $input) { ${field} { ${selection} } } }`,
    ),
    variables: { input },
  })
  const entity = result.data?.[operation]?.[field]
  if (!entity) throw new Error(`[menuBuilder] ${operation} no devolvió el registro`)
  return entity
}

export const saveMenu = (input: { id?: string; nombre: string; roles: string[] }) =>
  mutate<MenuDto>(input.id ? 'updateMenu' : 'createMenu', 'menu', MENU_FIELDS, input)

export const deleteMenu = (id: string) => mutate<{ id: string }>('deleteMenu', 'menu', 'id', { id })

export const saveMenuItem = (input: { id?: string; nombre: string; icon: string; route: string }) =>
  mutate<MenuItemDto>(
    input.id ? 'updateMenuItem' : 'createMenuItem',
    'menuItem',
    ITEM_FIELDS,
    input,
  )

export const deleteMenuItem = (id: string) =>
  mutate<{ id: string }>('deleteMenuItem', 'menuItem', 'id', { id })

const ICONS_BY_NAME = gql`
  query MenuBuilderIcon($icon: String!) {
    icons(icon: $icon, itemsPerPage: 50) {
      collection {
        id
        icon
      }
    }
  }
`

/**
 * IRI del `Icon` con ese nombre de Tabler; lo crea si no existe (el
 * `IconPicker` elige nombres del catálogo, `MenuItem.icon` es una relación).
 */
export async function ensureIcon(name: string): Promise<string> {
  const result = await graphql.client.query<{
    icons: { collection: Array<{ id: string; icon: string }> } | Array<{ id: string; icon: string }>
  }>({ query: ICONS_BY_NAME, variables: { icon: name }, fetchPolicy: 'network-only' })
  const icons = result.data?.icons
  const list = Array.isArray(icons) ? icons : (icons?.collection ?? [])
  const found = list.find((icon) => icon.icon === name)
  if (found) return found.id
  const created = await mutate<{ id: string }>('createIcon', 'icon', 'id', { icon: name, name })
  return created.id
}

export const fetchTree = (menuId: number) => http.get<TreeNode[]>(`/menus/${menuId}/tree`)

export const saveTree = (menuId: number, tree: TreeNode[]) =>
  request<TreeNode[]>(`/menus/${menuId}/tree`, { method: 'PUT', body: tree })

export const fetchLayout = () => http.get<MenuLayoutDto>('/menu-layout')

export const saveLayout = (layout: Partial<MenuLayoutDto>) =>
  request<MenuLayoutDto>('/menu-layout', { method: 'PUT', body: layout })
