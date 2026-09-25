/**
 * Destino de vue-router de un ítem de menú. Un ítem no es navegable si su
 * ruta ya no está en el router o si la ruta exige parámetros (el ítem no los
 * guarda): en ese caso se pinta deshabilitado en lugar de romper el enlace.
 */
import type { RouteLocationRaw, Router } from 'vue-router'
import type { NavItem } from '@/core/navigation/userMenus'

export function navTarget(
  item: NavItem,
  router: Pick<Router, 'hasRoute'>,
): RouteLocationRaw | null {
  const { name, params } = item.route
  if (!name || params.length > 0 || !router.hasRoute(name)) return null
  return { name }
}
