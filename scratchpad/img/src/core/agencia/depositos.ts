/**
 * Depósitos de agencia (espejo de `App\Venta\Agencia\FiltroDepositos` y de
 * los estados de `AgenciaDeposito`): filtros → query string, etiquetas,
 * qué admite el depósito elegido y validación del registro. Funciones puras.
 */
import type { DepositoFila, EstadoDeposito, NuevoDeposito } from './types'

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary'

export const ESTADOS_DEPOSITO: Array<{ valor: EstadoDeposito; etiqueta: string; severidad: Severidad }> = [
  { valor: 'registrado', etiqueta: 'Registrado', severidad: 'warn' },
  { valor: 'acreditado', etiqueta: 'Acreditado', severidad: 'success' },
  { valor: 'rechazado', etiqueta: 'Rechazado', severidad: 'danger' },
]

export const etiquetaEstadoDeposito = (e: string) =>
  ESTADOS_DEPOSITO.find((x) => x.valor === e) ?? { valor: e, etiqueta: e, severidad: 'secondary' as const }

export interface FiltroDepositos {
  /** `AAAA-MM-DD` por la fecha del depósito; sin ellas, ±1 mes (como el legado). */
  desde: string | null
  hasta: string | null
  id: number | null
  agencia: number | null
  boleta: string
  /** Vacío = todos. */
  estados: EstadoDeposito[]
}

export const filtroDepositosInicial = (): FiltroDepositos => ({ desde: null, hasta: null, id: null, agencia: null, boleta: '', estados: [] })

export function filtrosDepositosActivos(f: FiltroDepositos): number {
  return [f.desde, f.id, f.agencia, f.boleta.trim() || null, f.estados.length ? f.estados : null].filter((v) => v !== null).length
}

export function queryDepositos(f: FiltroDepositos, pagina: number, porPagina: number): string {
  const q = new URLSearchParams()
  if (f.id !== null) q.set('id', String(f.id))
  if (f.desde) q.set('desde', f.desde)
  if (f.hasta ?? f.desde) q.set('hasta', (f.hasta ?? f.desde)!)
  if (f.agencia !== null) q.set('agencia', String(f.agencia))
  if (f.boleta.trim()) q.set('boleta', f.boleta.trim())
  if (f.estados.length) q.set('estado', f.estados.join(','))
  q.set('pagina', String(pagina))
  q.set('porPagina', String(porPagina))
  return q.toString()
}

export type AccionDeposito = 'acreditar' | 'rechazar'

/** Si la acción se ofrece y, si no se puede, por qué. */
export function disponibilidadDeposito(
  d: DepositoFila | null,
  puedeAcreditar: boolean,
): Record<AccionDeposito, { visible: boolean; habilitada: boolean; motivo: string | null }> {
  const una = () => {
    if (!d) return { visible: puedeAcreditar, habilitada: false, motivo: 'Elija un depósito de la lista' }
    if (d.estado !== 'registrado') return { visible: puedeAcreditar, habilitada: false, motivo: `El depósito ya está ${etiquetaEstadoDeposito(d.estado).etiqueta.toLowerCase()}` }
    return { visible: puedeAcreditar, habilitada: puedeAcreditar, motivo: null }
  }
  return { acreditar: una(), rechazar: una() }
}

export interface BorradorDeposito {
  agencia: number | null
  fecha: string | null
  /** Quetzales (con decimales), como se teclea. */
  importe: number | null
  numeroBoleta: string
  aplicaBono: boolean
  observacion: string
}

export const MAX_BOLETA = 30

/** Errores por campo (vacío = válido) con la fecha de hoy `AAAA-MM-DD`. */
export function erroresDeposito(b: BorradorDeposito, hoy: string): Partial<Record<keyof BorradorDeposito, string>> {
  const e: Partial<Record<keyof BorradorDeposito, string>> = {}
  if (b.agencia === null) e.agencia = 'Seleccione la agencia.'
  if (!b.fecha) e.fecha = 'Indique la fecha del depósito.'
  else if (b.fecha > hoy) e.fecha = 'La fecha no puede ser futura.'
  if (b.importe === null || !(b.importe > 0)) e.importe = 'El importe debe ser mayor que cero.'
  const boleta = b.numeroBoleta.trim()
  if (!boleta) e.numeroBoleta = 'Indique el número de boleta.'
  else if (boleta.length > MAX_BOLETA) e.numeroBoleta = `Máximo ${MAX_BOLETA} caracteres.`
  return e
}

/** Cuerpo para `POST /depositos-agencia` (el importe en centavos). */
export function aNuevoDeposito(b: BorradorDeposito): NuevoDeposito {
  const observacion = b.observacion.trim()
  return {
    agencia: b.agencia!,
    fecha: b.fecha!,
    importe: Math.round((b.importe ?? 0) * 100),
    numeroBoleta: b.numeroBoleta.trim(),
    aplicaBono: b.aplicaBono,
    ...(observacion ? { observacion } : {}),
  }
}

/** Bonificación prevista en centavos (redondeo hacia abajo, como `SaldoAgencia::bonificacion`). */
export function bonoPrevisto(centavos: number, porcentaje: string | null, aplica: boolean): number {
  const p = Number(porcentaje ?? 0)
  if (!aplica || !(p > 0) || !(centavos > 0)) return 0
  return Math.floor((centavos * Math.round(p * 100)) / 10000)
}

export const quetzales = (centavos: number, moneda = 'GTQ') =>
  `${moneda === 'GTQ' ? 'Q' : moneda} ${(centavos / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
