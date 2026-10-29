/**
 * Destino de vue-router de un ítem de menú. Un enlace navega a su path tal
 * cual lo escribieron. Una ruta no es navegable si ya no está en el router o
 * si exige parámetros (el ítem no los guarda): en ese caso se pinta
 * deshabilitada en lugar de romper el enlace. Un grupo (sin ruta ni path)
 * no navega.
 */
import type { RouteLocationRaw, Router } from 'vue-router'
import type { NavItem } from '@/core/navigation/userMenus'

export function navTarget(
  item: NavItem,
  router: Pick<Router, 'hasRoute'>,
): RouteLocationRaw | null {
  if (item.path) return item.path
  const route = item.route
  if (!route?.name || route.params.length > 0 || !router.hasRoute(route.name)) return null
  return { name: route.name }
}
