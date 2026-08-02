/**
 * Mock / seed data for the operational dashboard.
 *
 * In a real deployment these would be replaced by calls to the
 * Symfony + API Platform backend (see `src/services` once that layer
 * is introduced) — kept as typed constants here so every view has
 * realistic content out of the box.
 */
import type {
  Kpi,
  RouteOccupancy,
  FleetStatusSlice,
  Departure,
  NotificationItem,
  Bus,
  BusRoute,
  Driver,
  Ticket,
  Incident,
  ReportDef
} from '@/types'

export const KPIS: Kpi[] = [
  { label: 'Buses en ruta', value: '42', delta: '+3 vs ayer', trend: 'up', icon: 'bus', points: '0,22 10,18 20,20 30,14 40,16 50,10 60,12 70,6 80,8 90,3 100,5' },
  { label: 'Pasajeros hoy', value: '8,624', delta: '+12.4%', trend: 'up', icon: 'users', points: '0,20 10,19 20,15 30,16 40,11 50,13 60,9 70,10 80,6 90,7 100,3' },
  { label: 'Ingresos del día', value: 'Q 96,420', delta: '-2.4%', trend: 'down', icon: 'ticket', points: '0,6 10,8 20,7 30,10 40,9 50,13 60,12 70,16 80,15 90,18 100,20' },
  { label: 'Puntualidad', value: '94.2%', delta: '+1.1 pts', trend: 'up', icon: 'gauge', points: '0,16 10,15 20,17 30,13 40,14 50,10 60,11 70,8 80,9 90,5 100,6' }
]

export const ROUTE_OCCUPANCY: RouteOccupancy[] = [
  { name: 'Ruta 12 · Centro / Aeropuerto', pct: 92 },
  { name: 'Ruta 04 · Zona Norte', pct: 81 },
  { name: 'Ruta 07 · Terminal / Universidad', pct: 76 },
  { name: 'Ruta 21 · Circuito Industrial', pct: 64 },
  { name: 'Ruta 09 · Costa Sur Express', pct: 58 },
  { name: 'Ruta 15 · Anillo Periférico', pct: 47 }
]

export const FLEET_STATUS: FleetStatusSlice[] = [
  { label: 'En ruta', value: 42, color: 'var(--c-info)' },
  { label: 'Depósito', value: 9, color: 'var(--text-muted)' },
  { label: 'Mantenimiento', value: 6, color: 'var(--c-warning)' },
  { label: 'Inactivo', value: 3, color: 'var(--c-danger)' }
]

export const DEPARTURES: Departure[] = [
  { time: '08:15', route: 'Ruta 12 · Aeropuerto', bus: 'B-104', driver: 'C. Méndez', status: 'A tiempo' },
  { time: '08:30', route: 'Ruta 04 · Zona Norte', bus: 'B-088', driver: 'L. Ramírez', status: 'Abordando' },
  { time: '08:40', route: 'Ruta 21 · Industrial', bus: 'B-112', driver: 'J. Ortiz', status: 'Retrasado' },
  { time: '08:55', route: 'Ruta 07 · Universidad', bus: 'B-071', driver: 'A. Ical', status: 'A tiempo' },
  { time: '09:10', route: 'Ruta 09 · Costa Sur', bus: 'B-095', driver: 'M. Sique', status: 'A tiempo' }
]

export const NOTIFICATIONS: NotificationItem[] = [
  { icon: 'alert', color: 'var(--c-danger)', bg: 'var(--c-danger-soft)', title: 'Incidente reportado', desc: 'Bus B-112 con falla mecánica menor en Ruta 21.', time: 'hace 6 min' },
  { icon: 'check', color: 'var(--c-success)', bg: 'var(--c-success-soft)', title: 'Mantenimiento completado', desc: 'Bus B-057 aprobado para volver a operación.', time: 'hace 22 min' },
  { icon: 'users', color: 'var(--c-info)', bg: 'var(--c-info-soft)', title: 'Alta ocupación', desc: 'Ruta 12 supera el 90% de ocupación en hora pico.', time: 'hace 41 min' },
  { icon: 'ticket', color: 'var(--c-info)', bg: 'var(--c-info-soft)', title: 'Venta grupal registrada', desc: '32 boletos vendidos para Ruta 09 · Costa Sur.', time: 'hace 1 h' },
  { icon: 'clock', color: 'var(--c-warning)', bg: 'var(--c-warning-soft)', title: 'Salida retrasada', desc: 'Bus B-112 con 8 min de retraso en su salida.', time: 'hace 1 h' }
]

export const BUSES: Bus[] = [
  { id: 'B-104', plate: 'P-245ABC', model: 'Volvo 9800 · 2022', capacity: 44, route: 'Ruta 12 · Centro/Aeropuerto', driver: 'Carlos Méndez', status: 'en-ruta', location: 'Av. Reforma km 8', occ: 78 },
  { id: 'B-088', plate: 'P-118LKM', model: 'Mercedes O500 · 2021', capacity: 40, route: 'Ruta 04 · Zona Norte', driver: 'Laura Ramírez', status: 'en-ruta', location: 'Calz. Roosevelt km 3', occ: 64 },
  { id: 'B-112', plate: 'P-330XTR', model: 'Volvo 9800 · 2023', capacity: 44, route: 'Ruta 21 · Circuito Industrial', driver: 'Jorge Ortiz', status: 'mantenimiento', location: 'Taller central', occ: 0 },
  { id: 'B-071', plate: 'P-902QWE', model: 'Scania K360 · 2020', capacity: 42, route: 'Ruta 07 · Terminal/Universidad', driver: 'Ana Ical', status: 'en-ruta', location: '6a Av. Zona 4', occ: 88 },
  { id: 'B-095', plate: 'P-556JHN', model: 'Mercedes O500 · 2022', capacity: 40, route: 'Ruta 09 · Costa Sur Express', driver: 'Manuel Sique', status: 'en-ruta', location: 'CA-9 km 42', occ: 55 },
  { id: 'B-057', plate: 'P-771ZXP', model: 'Volvo 9800 · 2019', capacity: 44, route: 'Sin asignar', driver: '—', status: 'deposito', location: 'Depósito Zona 12', occ: 0 },
  { id: 'B-063', plate: 'P-284MNB', model: 'Scania K360 · 2021', capacity: 42, route: 'Ruta 15 · Anillo Periférico', driver: 'Elena Rax', status: 'en-ruta', location: 'Anillo Periférico km 6', occ: 41 },
  { id: 'B-039', plate: 'P-410VBN', model: 'Mercedes O500 · 2018', capacity: 40, route: 'Sin asignar', driver: '—', status: 'inactivo', location: 'Depósito Zona 12', occ: 0 }
]

export const ROUTES: BusRoute[] = [
  { name: 'Ruta 12 · Centro / Aeropuerto', od: 'Zona 1 → Aeropuerto La Aurora', stops: 14, km: 38, buses: 5, occ: 92 },
  { name: 'Ruta 04 · Zona Norte', od: 'CENMA → Zona 18', stops: 11, km: 24, buses: 4, occ: 81 },
  { name: 'Ruta 07 · Terminal / Universidad', od: 'Terminal Zona 4 → USAC', stops: 9, km: 17, buses: 6, occ: 76 },
  { name: 'Ruta 21 · Circuito Industrial', od: 'Zona 12 → Amatitlán', stops: 16, km: 29, buses: 3, occ: 64 },
  { name: 'Ruta 09 · Costa Sur Express', od: 'Guatemala → Escuintla', stops: 6, km: 58, buses: 4, occ: 58 },
  { name: 'Ruta 15 · Anillo Periférico', od: 'Zona 7 → Zona 21', stops: 13, km: 22, buses: 3, occ: 47 }
]

export const DRIVERS: Driver[] = [
  { name: 'Carlos Méndez', license: 'A-118834', status: 'activo', bus: 'B-104', rating: 4.9, hours: 38 },
  { name: 'Laura Ramírez', license: 'A-227741', status: 'activo', bus: 'B-088', rating: 4.8, hours: 35 },
  { name: 'Jorge Ortiz', license: 'A-330982', status: 'descanso', bus: '—', rating: 4.6, hours: 22 },
  { name: 'Ana Ical', license: 'A-118120', status: 'activo', bus: 'B-071', rating: 4.9, hours: 40 },
  { name: 'Manuel Sique', license: 'A-556410', status: 'activo', bus: 'B-095', rating: 4.7, hours: 36 },
  { name: 'Elena Rax', license: 'A-284552', status: 'vacaciones', bus: '—', rating: 4.8, hours: 0 }
]

export const TICKETS: Ticket[] = [
  { id: 'TK-88421', passenger: 'Marta Solís', route: 'Ruta 12 · Aeropuerto', seat: '14B', date: '30 jul · 08:15', amount: 'Q 45.00', payment: 'Tarjeta', status: 'pagado' },
  { id: 'TK-88422', passenger: 'Diego Pérez', route: 'Ruta 09 · Costa Sur', seat: '07A', date: '30 jul · 09:10', amount: 'Q 85.00', payment: 'Efectivo', status: 'pagado' },
  { id: 'TK-88423', passenger: 'Sofía Reyes', route: 'Ruta 04 · Zona Norte', seat: '22C', date: '30 jul · 08:30', amount: 'Q 12.50', payment: 'Tarjeta', status: 'pendiente' },
  { id: 'TK-88424', passenger: 'Luis Cabrera', route: 'Ruta 07 · Universidad', seat: '03A', date: '30 jul · 08:55', amount: 'Q 10.00', payment: 'App móvil', status: 'pagado' },
  { id: 'TK-88425', passenger: 'Karen Us', route: 'Ruta 21 · Industrial', seat: '18B', date: '30 jul · 08:40', amount: 'Q 15.00', payment: 'Efectivo', status: 'reembolsado' }
]

export const INCIDENTS: Incident[] = [
  { sev: 'alta', desc: 'Falla mecánica en sistema de frenos', route: 'Ruta 21 · Industrial', bus: 'B-112', time: 'hace 6 min', status: 'abierto' },
  { sev: 'media', desc: 'Retraso por congestión vial reportado', route: 'Ruta 12 · Aeropuerto', bus: 'B-104', time: 'hace 34 min', status: 'en revisión' },
  { sev: 'baja', desc: 'Reclamo de pasajero por cambio de tarifa', route: 'Ruta 04 · Zona Norte', bus: 'B-088', time: 'hace 1 h', status: 'resuelto' },
  { sev: 'media', desc: 'Aire acondicionado con funcionamiento intermitente', route: 'Ruta 07 · Universidad', bus: 'B-071', time: 'hace 2 h', status: 'en revisión' },
  { sev: 'alta', desc: 'Accidente menor en parada, sin heridos', route: 'Ruta 15 · Periférico', bus: 'B-063', time: 'hace 3 h', status: 'resuelto' }
]

export const REPORTS: ReportDef[] = [
  { title: 'Ingresos mensuales', desc: 'Consolidado de ventas de boletos y comparativo mensual por ruta.', icon: 'ticket' },
  { title: 'Puntualidad de flota', desc: 'Cumplimiento de horarios de salida y llegada por unidad.', icon: 'clock' },
  { title: 'Ocupación por ruta', desc: 'Tendencia de ocupación promedio y horas pico por ruta.', icon: 'route' },
  { title: 'Mantenimiento', desc: 'Historial de mantenimientos preventivos y correctivos por unidad.', icon: 'wrench' }
]
