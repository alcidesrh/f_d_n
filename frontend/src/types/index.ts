export type ThemeMode = 'light' | 'dark'

export type ThemePreset = 'tailwind' | 'aura' | 'lara' | 'material' | 'nora'

export type PrimaryColor =
  | 'blue'
  | 'emerald'
  | 'amber'
  | 'violet'
  | 'rose'
  | 'teal'
  | 'orange'
  | 'indigo'
  | 'neutral'
  | 'stone'
  | 'gray'
  | 'slate'
  | 'verde'
  | 'cyan'
  | 'sky'
  | 'yellow'
  | 'red'

export type SurfacePalette = 'slate' | 'gray' | 'zinc' | 'neutral' | 'stone'

export type PanelState = 'open' | 'mini' | 'close'

export interface Kpi {
  label: string
  value: string
  delta: string
  trend: 'up' | 'down'
  icon: string
  points: string
}

export interface RouteOccupancy {
  name: string
  pct: number
}

export interface FleetStatusSlice {
  label: string
  value: number
  color: string
}

export type DepartureStatus = 'A tiempo' | 'Abordando' | 'Retrasado'

export interface Departure {
  time: string
  route: string
  bus: string
  driver: string
  status: DepartureStatus
}

export interface NotificationItem {
  icon: string
  color: string
  bg: string
  title: string
  desc: string
  time: string
}

export type BusStatus = 'en-ruta' | 'mantenimiento' | 'deposito' | 'inactivo'

export interface Bus {
  id: string
  plate: string
  model: string
  capacity: number
  route: string
  driver: string
  status: BusStatus
  location: string
  occ: number
}

export interface BusRoute {
  name: string
  od: string
  stops: number
  km: number
  buses: number
  occ: number
}

export type DriverStatus = 'activo' | 'descanso' | 'vacaciones'

export interface Driver {
  name: string
  license: string
  status: DriverStatus
  bus: string
  rating: number
  hours: number
}

export type TicketStatus = 'pagado' | 'pendiente' | 'reembolsado'

export interface Ticket {
  id: string
  passenger: string
  route: string
  seat: string
  date: string
  amount: string
  payment: string
  status: TicketStatus
}

export type IncidentSeverity = 'alta' | 'media' | 'baja'
export type IncidentStatus = 'abierto' | 'en revisión' | 'resuelto'

export interface Incident {
  sev: IncidentSeverity
  desc: string
  route: string
  bus: string
  time: string
  status: IncidentStatus
}

export interface ReportDef {
  title: string
  desc: string
  icon: string
}

export interface NavItem {
  to: string
  label: string
  icon: string
  count?: number
}
