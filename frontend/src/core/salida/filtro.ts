/**
 * Filtro del listado de salidas (espejo de `App\Salida\FiltroSalidas`):
 * estado del formulario → query string de `/api/gestion-salidas`, estados
 * con su etiqueta y utilidades de fechas. Funciones puras.
 */
import type { EstadoSalida } from './types'

export interface FiltroSalidas {
  /** Vacío = todos. */
  estados: EstadoSalida[]
  empresa: number | null
  trayecto: number | null
  bus: number | null
  /** `AAAA-MM-DD`, inclusive */
  desde: string | null
  hasta: string | null
  /** `proximas`: iniciadas, abordando y programadas de la más próxima a la más lejana. */
  orden: 'proximas' | 'fecha'
  direccion: 'asc' | 'desc'
  pagina: number
  porPagina: number
}

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'

export const ESTADOS: Array<{ valor: EstadoSalida; etiqueta: string; severidad: Severidad }> = [
  { valor: 'iniciada', etiqueta: 'En ruta', severidad: 'success' },
  { valor: 'abordando', etiqueta: 'Abordando', severidad: 'info' },
  { valor: 'programada', etiqueta: 'Programada', severidad: 'secondary' },
  { valor: 'finalizada', etiqueta: 'Finalizada', severidad: 'contrast' },
  { valor: 'cancelada', etiqueta: 'Anulada', severidad: 'danger' },
]

export const etiquetaEstado = (e: EstadoSalida) => ESTADOS.find((x) => x.valor === e) ?? { valor: e, etiqueta: e, severidad: 'secondary' as const }

/** Por defecto: sin finalizadas ni anuladas, las en curso primero y luego las más próximas. */
export function filtroInicial(): FiltroSalidas {
  return {
    estados: ['iniciada', 'abordando', 'programada'],
    empresa: null,
    trayecto: null,
    bus: null,
    desde: null,
    hasta: null,
    orden: 'proximas',
    direccion: 'asc',
    pagina: 1,
    porPagina: 25,
  }
}

/** Query string para el backend; sin los valores vacíos. Estados vacíos = todos. */
export function aQuery(f: FiltroSalidas): string {
  const p = new URLSearchParams()
  p.set('estado', f.estados.join(','))
  for (const k of ['empresa', 'trayecto', 'bus', 'desde', 'hasta'] as const) {
    const v = f[k]
    if (v !== null && v !== '') p.set(k, String(v))
  }
  p.set('orden', f.orden)
  if (f.orden === 'fecha') p.set('direccion', f.direccion)
  p.set('pagina', String(f.pagina))
  p.set('porPagina', String(f.porPagina))
  return p.toString()
}

/** Cuántos filtros (además del estado) están activos: para el botón "Filtros". */
export function filtrosActivos(f: FiltroSalidas): number {
  return [f.empresa, f.trayecto, f.bus, f.desde, f.hasta].filter((v) => v !== null && v !== '').length
}

const dos = (n: number) => String(n).padStart(2, '0')

/** `Date` local → `AAAA-MM-DD`. */
export const aDia = (d: Date) => `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`

/** `AAAA-MM-DD` → `Date` local a medianoche. */
export function deDia(s: string): Date {
  const [a, m, d] = s.split('-').map(Number)
  return new Date(a!, m! - 1, d)
}

/** `Date` local → `AAAA-MM-DDTHH:MM` (lo que espera la edición de una salida). */
export const aFechaHora = (d: Date) => `${aDia(d)}T${dos(d.getHours())}:${dos(d.getMinutes())}`

/** `HH:MM` de una fecha ISO, en hora local. */
export const horaDe = (iso: string) => {
  const d = new Date(iso)
  return `${dos(d.getHours())}:${dos(d.getMinutes())}`
}
