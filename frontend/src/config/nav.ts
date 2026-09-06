import type { NavItem } from '@/types'

export const NAV_MAIN: NavItem[] = [
  { to: '/', label: 'Resumen', icon: 'grid' },
  { to: '/demo', label: 'Demo FormKit', icon: 'layout' },
  { to: '/demo/picklist', label: 'PickList DnD', icon: 'columns' },
  { to: '/flota', label: 'Flota de buses', icon: 'bus', count: 8 },
  { to: '/rutas', label: 'Rutas', icon: 'route' },
  { to: '/choferes', label: 'Choferes', icon: 'users' },
  { to: '/boletos', label: 'Boletos y ventas', icon: 'ticket' },
]

export const NAV_OPS: NavItem[] = [
  { to: '/incidentes', label: 'Incidentes', icon: 'alert', count: 2 },
  { to: '/reportes', label: 'Reportes', icon: 'barchart' },
  { to: '/agnostic', label: 'CRUD dinámico', icon: 'table' },
  { to: '/entity-form', label: 'Formulario dinámico', icon: 'file-edit' },
  { to: '/auth/login', label: 'Iniciar Sesión', icon: 'lock' },
  { to: '/ajustes', label: 'Ajustes', icon: 'settings' },
]
