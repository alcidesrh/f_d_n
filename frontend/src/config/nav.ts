import type { NavItem } from '@/types'

export const NAV_MAIN: NavItem[] = [
  { to: '/', label: 'Resumen', icon: 'grid' },
  { to: '/flota', label: 'Flota de buses', icon: 'bus', count: 8 },
  { to: '/rutas', label: 'Rutas', icon: 'route' },
  { to: '/choferes', label: 'Choferes', icon: 'users' },
  { to: '/boletos', label: 'Boletos y ventas', icon: 'ticket' }
]

export const NAV_OPS: NavItem[] = [
  { to: '/incidentes', label: 'Incidentes', icon: 'alert', count: 2 },
  { to: '/reportes', label: 'Reportes', icon: 'barchart' },
  { to: '/ajustes', label: 'Ajustes', icon: 'settings' }
]
