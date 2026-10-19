/**
 * Reportes de encomiendas (`/api/reportes/encomiendas/*`, ADR-035): qué pide
 * cada uno, valores iniciales, validación, query y las cifras de la vista
 * previa. Funciones puras (espejo de `App\Reporte\Encomienda\FiltroEncomiendas`).
 */
import { config } from '@/core/config'
import { useSessionStore } from '@/core/auth/session'
import { HttpError, http } from '@/core/http'
import { aDia, deDia, importe } from './filtro'
import type { FormatoReporte, Opcion } from './types'

export type ReporteEncomiendaId = 'manifiesto' | 'cuadre' | 'detalle-factura' | 'detalle-general' | 'guia-interna' | 'pendientes-entrega'

type Campo = 'estacion' | 'empresa' | 'origen' | 'destino' | 'casillas'

export interface ConfigReporteEncomienda {
  titulo: string
  descripcion: string
  icono: string
  /** Un día, un rango o ninguno. */
  fechas: 'dia' | 'rango' | null
  etiquetaFecha?: string
  campos: Campo[]
  etiquetaEstacion?: string
  formatos: FormatoReporte[]
  /** Reporte de caja: pide `reporte.encomiendas`. */
  caja: boolean
}

export const REPORTES_ENCOMIENDA: Record<Exclude<ReporteEncomiendaId, 'manifiesto'>, ConfigReporteEncomienda> = {
  cuadre: {
    titulo: 'Cuadre de encomiendas',
    descripcion: 'Lo facturado al recibir, lo cobrado al entregar, lo enviado por salida y lo anulado en el día.',
    icono: 'request-quote-outline',
    fechas: 'dia',
    etiquetaFecha: 'Fecha',
    campos: ['estacion', 'empresa'],
    etiquetaEstacion: 'Estación',
    formatos: ['pdf'],
    caja: true,
  },
  'detalle-factura': {
    titulo: 'Detalle de factura de encomiendas',
    descripcion: 'Cada encomienda cobrada con su factura electrónica, por día de cobro y destino.',
    icono: 'receipt-long-outline',
    fechas: 'rango',
    etiquetaFecha: 'Fecha de cobro (un día o un rango)',
    campos: ['estacion', 'empresa'],
    etiquetaEstacion: 'Estación de cobro',
    formatos: ['pdf', 'xlsx'],
    caja: true,
  },
  'detalle-general': {
    titulo: 'Detalle general de encomiendas',
    descripcion: 'Las registradas por día, origen y destino, con lo pagado y lo por cobrar.',
    icono: 'list-alt-outline',
    fechas: 'rango',
    etiquetaFecha: 'Fecha de registro (un día o un rango)',
    campos: ['empresa', 'origen', 'destino', 'casillas'],
    formatos: ['pdf', 'xlsx'],
    caja: false,
  },
  'guia-interna': {
    titulo: 'Detalle de guías internas',
    descripcion: 'Las encomiendas sin cobro (autorización interna), con su motivo.',
    icono: 'assignment-ind-outline',
    fechas: 'rango',
    etiquetaFecha: 'Fecha de emisión (un día o un rango)',
    campos: ['estacion', 'empresa'],
    etiquetaEstacion: 'Estación de emisión',
    formatos: ['pdf'],
    caja: true,
  },
  'pendientes-entrega': {
    titulo: 'Encomiendas pendientes de entregar',
    descripcion: 'Las que van hacia la estación y no se han entregado, con los días que llevan.',
    icono: 'pending-actions',
    fechas: null,
    campos: ['estacion', 'empresa', 'casillas'],
    etiquetaEstacion: 'Estación de entrega',
    formatos: ['pdf'],
    caja: false,
  },
}

/** Máximo de días por reporte (`FiltroEncomiendas::MAX_DIAS`). */
export const MAX_DIAS_ENCOMIENDA = 62

export interface OpcionesReporteEncomienda {
  hoy: string
  estaciones: Array<Opcion & { departamento: string | null }>
  /** Todas las estaciones (origen y destino libres). */
  enclaves: Array<Opcion & { departamento: string | null }>
  empresas: Opcion[]
  alcance: { estacion: number | null; empresa: number | null }
  /** Puede ver los reportes de caja. */
  caja: boolean
}

export interface FiltroEncomienda {
  /** `[desde, hasta]` (`hasta` null mientras se elige); en los de un día, solo `desde`. */
  rango: (Date | null)[] | null
  estacion: number | null
  empresa: number | null
  origen: number | null
  destino: number | null
  soloFacturado: boolean
  soloPorCobrar: boolean
}

export const filtroEncomiendaInicial = (o: OpcionesReporteEncomienda): FiltroEncomienda => ({
  rango: [deDia(o.hoy), deDia(o.hoy)],
  estacion: o.alcance.estacion,
  empresa: o.alcance.empresa,
  origen: null,
  destino: null,
  soloFacturado: false,
  soloPorCobrar: false,
})

const diasEntre = (a: Date, b: Date) => Math.round((Date.UTC(b.getFullYear(), b.getMonth(), b.getDate()) - Date.UTC(a.getFullYear(), a.getMonth(), a.getDate())) / 86_400_000)

/** Primer problema del filtro, o null si se puede consultar. */
export function errorEncomienda(c: ConfigReporteEncomienda, f: FiltroEncomienda): string | null {
  if (!c.fechas) return null
  const [desde, hasta] = f.rango ?? []
  if (!desde) return c.fechas === 'dia' ? 'Elige la fecha.' : 'Elige la fecha o el rango de fechas.'
  if (c.fechas === 'rango' && hasta && diasEntre(desde, hasta) >= MAX_DIAS_ENCOMIENDA) return `El rango no puede pasar de ${MAX_DIAS_ENCOMIENDA} días.`
  return null
}

/** Query del reporte (sin `formato`), o null si el filtro no es válido. Solo lleva lo que el reporte usa. */
export function encomiendaQuery(c: ConfigReporteEncomienda, f: FiltroEncomienda): string | null {
  if (errorEncomienda(c, f)) return null
  const p = new URLSearchParams()
  const [desde, hasta] = f.rango ?? []
  if (c.fechas && desde) {
    p.set('desde', aDia(desde))
    if (c.fechas === 'rango') p.set('hasta', aDia(hasta ?? desde))
  }
  const poner = (k: string, v: number | null) => v !== null && p.set(k, String(v))
  if (c.campos.includes('estacion')) poner('estacion', f.estacion)
  if (c.campos.includes('empresa')) poner('empresa', f.empresa)
  if (c.campos.includes('origen')) poner('origen', f.origen)
  if (c.campos.includes('destino')) poner('destino', f.destino)
  if (c.campos.includes('casillas')) {
    if (f.soloFacturado) p.set('soloFacturado', '1')
    if (f.soloPorCobrar) p.set('soloPorCobrar', '1')
  }
  return p.toString()
}

/** Nombre del archivo descargado. */
export function nombreArchivo(id: ReporteEncomiendaId, f: FiltroEncomienda, hoy: string): string {
  const [desde, hasta] = f.rango ?? []
  const base = id.replace(/-/g, '_')
  if (id === 'pendientes-entrega' || !desde) return `encomiendas_${base}_${hoy.replace(/-/g, '')}`
  const d = aDia(desde).replace(/-/g, '')
  return id === 'cuadre' ? `encomiendas_${base}_${d}` : `encomiendas_${base}_${d}_${aDia(hasta ?? desde).replace(/-/g, '')}`
}

export interface CifraVista {
  etiqueta: string
  valor: string | number
  destacada?: boolean
  alerta?: boolean
}

const q = (centavos: number | undefined) => importe(centavos ?? 0, 'GTQ')
type Resumen = Record<string, number | Record<string, number>>
const n = (r: Resumen, k: string) => (typeof r[k] === 'number' ? (r[k] as number) : 0)

/** Las cifras de la vista previa de cada reporte, a partir de su `formato=resumen`. */
export function cifrasEncomienda(id: ReporteEncomiendaId, r: Resumen): CifraVista[] {
  switch (id) {
    case 'cuadre':
      return [
        { etiqueta: 'Ingreso del día', valor: q(n(r, 'ingreso')), destacada: true },
        { etiqueta: `Facturado al recibir · ${n(r, 'facturadas')}`, valor: q(n(r, 'facturado')) },
        { etiqueta: `Cobrado al entregar · ${n(r, 'cobradas')}`, valor: q(n(r, 'cobrado')) },
        { etiqueta: `Por cobrar registrado · ${n(r, 'porCobrarCantidad')}`, valor: q(n(r, 'porCobrar')) },
        { etiqueta: 'Guías internas', valor: n(r, 'internas') },
        { etiqueta: 'Salidas con encomiendas', valor: n(r, 'salidas') },
        { etiqueta: 'Sin enviar del día', valor: n(r, 'pendientesEnvio'), alerta: n(r, 'pendientesEnvio') > 0 },
        { etiqueta: 'Anuladas', valor: n(r, 'anuladas'), alerta: n(r, 'anuladas') > 0 },
      ]
    case 'detalle-factura':
      return [
        { etiqueta: `Total facturado · ${n(r, 'cantidad')}`, valor: q(n(r, 'total')), destacada: true },
        { etiqueta: 'Pagadas al recibir', valor: n(r, 'pagadas') },
        { etiqueta: 'Por cobrar entregadas', valor: n(r, 'porCobrar') },
        { etiqueta: 'Sin número DTE', valor: n(r, 'pendientes'), alerta: n(r, 'pendientes') > 0 },
      ]
    case 'detalle-general':
      return [
        { etiqueta: 'Encomiendas', valor: n(r, 'cantidad') },
        { etiqueta: 'Bultos', valor: n(r, 'bultos') },
        { etiqueta: 'Pagado', valor: q(n(r, 'pagado')), destacada: true },
        { etiqueta: 'Por cobrar', valor: q(n(r, 'porCobrar')), destacada: true },
        { etiqueta: 'Anuladas (no suman)', valor: n(r, 'anuladas') },
      ]
    case 'guia-interna':
      return [
        { etiqueta: 'Guías internas', valor: n(r, 'cantidad'), destacada: true },
        { etiqueta: 'Bultos', valor: n(r, 'bultos') },
        { etiqueta: 'Días con guías', valor: n(r, 'dias') },
        { etiqueta: 'Anuladas', valor: n(r, 'anuladas') },
      ]
    case 'pendientes-entrega':
      return [
        { etiqueta: 'Pendientes', valor: n(r, 'cantidad'), destacada: true },
        { etiqueta: 'Bultos', valor: n(r, 'bultos') },
        { etiqueta: 'Por cobrar', valor: q(n(r, 'porCobrar')) },
        { etiqueta: 'La más antigua', valor: `${n(r, 'masAntigua')} ${n(r, 'masAntigua') === 1 ? 'día' : 'días'}`, alerta: n(r, 'masAntigua') > 7 },
      ]
    case 'manifiesto':
      return [
        { etiqueta: 'Encomiendas', valor: n(r, 'cantidad'), destacada: true },
        { etiqueta: 'Bultos', valor: n(r, 'bultos') },
        { etiqueta: 'Destinos', valor: n(r, 'destinos') },
        { etiqueta: 'Por cobrar', valor: q(n(r, 'porCobrar')) },
      ]
  }
}

// —— Transporte ——

const BASE = '/reportes/encomiendas'

export const fetchOpcionesEncomienda = () => http.get<OpcionesReporteEncomienda>(`${BASE}/opciones`)

export const fetchResumenEncomienda = (id: ReporteEncomiendaId, query: string) => http.get<Resumen>(`${BASE}/${id}?${query}&formato=resumen`, { silent: true })

/** Archivo del reporte como `Blob` (lleva el Bearer, por eso no es un enlace). */
export async function fetchArchivoEncomienda(id: ReporteEncomiendaId, query: string, formato: FormatoReporte): Promise<Blob> {
  const token = useSessionStore().token
  const res = await fetch(`${config.restUrl}${BASE}/${id}?${query}&formato=${formato}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) {
    const cuerpo = (await res.json().catch(() => null)) as { error?: string } | null
    throw new HttpError(res.status, cuerpo, cuerpo?.error ?? `HTTP ${res.status}`)
  }
  return res.blob()
}
