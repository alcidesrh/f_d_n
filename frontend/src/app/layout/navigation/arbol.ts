/**
 * Ids de todos los ítems de un árbol de menú, a cualquier profundidad. Sirve
 * para pliegar de una vez los grupos que cuelgan de una sección.
 */
import type { NavItem } from '@/core/navigation/userMenus'

export function idsDelArbol(items: NavItem[]): number[] {
  return items.flatMap((item) => [item.id, ...idsDelArbol(item.children)])
}
