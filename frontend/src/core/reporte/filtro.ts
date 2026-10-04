/**
 * Filtros de los reportes de venta: valores iniciales, validación y
 * serialización a query. Funciones puras (espejo de `App\Reporte\Filtro*`).
 */
import type { OpcionesReporte } from './types'

/** Máximo de días del detalle de factura (`FiltroDetalle::MAX_DIAS`). */
export const MAX_DIAS_DETALLE = 62

export interface FiltroCuadre {
  fecha: Date | null
  estacion: number | null
  empresa: number | null
  moneda: string | null
}

export interface FiltroDetalle {
  /** `[desde, hasta]`; `hasta` es `null` mientras se elige el segundo día. */
  rango: (Date | null)[] | null
  estacion: number | null
  empresa: number | null
  autorizacion: string
  referencia: string
  soloTarjetas: boolean
  soloReferencias: boolean
}

const dos = (n: number) => String(n).padStart(2, '0')

/** `Date` local → `AAAA-MM-DD`. */
export const aDia = (d: Date) => `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`

/** `AAAA-MM-DD` → `Date` local a medianoche. */
export function deDia(s: string): Date {
  const [a, m, d] = s.split('-').map(Number)
  return new Date(a!, m! - 1, d)
}

const MS_DIA = 86_400_000

/** Días entre dos fechas locales, sin que el cambio de hora desvíe el resultado. */
const diasEntre = (a: Date, b: Date) => Math.round((Date.UTC(b.getFullYear(), b.getMonth(), b.getDate()) - Date.UTC(a.getFullYear(), a.getMonth(), a.getDate())) / MS_DIA)

export const cuadreInicial = (o: OpcionesReporte): FiltroCuadre => ({
  fecha: deDia(o.hoy),
  estacion: o.alcance.estacion,
  empresa: o.alcance.empresa,
  moneda: o.monedas.find((m) => m.sigla === 'GTQ')?.sigla ?? o.monedas[0]?.sigla ?? null,
})

export const detalleInicial = (o: OpcionesReporte): FiltroDetalle => ({
  rango: [deDia(o.hoy), deDia(o.hoy)],
  estacion: o.alcance.estacion,
  empresa: o.alcance.empresa,
  autorizacion: '',
  referencia: '',
  soloTarjetas: false,
  soloReferencias: false,
})

/** Primer problema del filtro, o `null` si se puede consultar. */
export function errorCuadre(f: FiltroCuadre): string | null {
  if (!f.fecha) return 'Elige la fecha de venta.'
  if (!f.moneda) return 'Elige la moneda.'
  return null
}

export function errorDetalle(f: FiltroDetalle): string | null {
  const [desde, hasta] = f.rango ?? []
  if (!desde) return 'Elige la fecha o el rango de fechas.'
  if (hasta && diasEntre(desde, hasta) >= MAX_DIAS_DETALLE) return `El rango no puede pasar de ${MAX_DIAS_DETALLE} días.`
  return null
}

function query(pares: Record<string, string | number | boolean | null | undefined>): string {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(pares)) {
    if (v === null || v === undefined || v === '' || v === false) continue
    p.set(k, v === true ? '1' : String(v))
  }
  return p.toString()
}

/** Query del cuadre (sin `formato`), o `null` si el filtro no es válido. */
export function cuadreQuery(f: FiltroCuadre): string | null {
  if (errorCuadre(f)) return null
  return query({ fecha: aDia(f.fecha!), estacion: f.estacion, empresa: f.empresa, moneda: f.moneda })
}

export function detalleQuery(f: FiltroDetalle): string | null {
  if (errorDetalle(f)) return null
  const [desde, hasta] = f.rango!
  return query({
    desde: aDia(desde!),
    hasta: aDia(hasta ?? desde!),
    estacion: f.estacion,
    empresa: f.empresa,
    autorizacion: f.autorizacion.trim(),
    referencia: f.referencia.trim(),
    soloTarjetas: f.soloTarjetas,
    soloReferencias: f.soloReferencias,
  })
}

const numero = new Intl.NumberFormat('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** Centavos → `GTQ 1,234.50` (el mismo formato de los PDF). */
export const importe = (centavos: number, moneda: string) => `${moneda} ${numero.format(centavos / 100)}`
