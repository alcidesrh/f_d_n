/**
 * Filtro del listado de compras de la página web (dashboard, ADR-023):
 * estado del formulario → query string de `/api/pagina/compras` (espejo de
 * `App\Venta\EnLinea\FiltroComprasWeb`), rangos rápidos de fechas y la
 * suma de la selección. Funciones puras.
 */
import type { CompraWeb, EstadoPago } from './types'

export interface FiltroCompras {
  estados: EstadoPago[]
  /** `AAAA-MM-DD` */
  creadoDesde: string | null
  creadoHasta: string | null
  salidaDesde: string | null
  salidaHasta: string | null
  empresa: number | null
  origen: number | null
  destino: number | null
  idaVuelta: 'todos' | 'si' | 'no'
  facturacion: 'certificada' | 'pendiente' | 'no_aplica' | null
  marca: 'visa' | 'mastercard' | null
  /** Quetzales */
  montoMinimo: number | null
  montoMaximo: number | null
  texto: string
  orden: 'creado' | 'monto' | 'salida'
  direccion: 'asc' | 'desc'
  pagina: number
  porPagina: number
}

export const ESTADOS: Array<{ valor: EstadoPago; etiqueta: string; severidad: 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' }> = [
  { valor: 'completado', etiqueta: 'Completada', severidad: 'success' },
  { valor: 'rechazado', etiqueta: 'Rechazada', severidad: 'danger' },
  { valor: 'autenticacion', etiqueta: 'Sin terminar', severidad: 'secondary' },
  { valor: 'aprobado', etiqueta: 'Cobrada (registrando)', severidad: 'info' },
  { valor: 'reembolsado', etiqueta: 'Reembolsada', severidad: 'warn' },
  { valor: 'reembolso_pendiente', etiqueta: 'Reembolso pendiente', severidad: 'danger' },
]

export const etiquetaEstado = (e: EstadoPago) => ESTADOS.find((x) => x.valor === e) ?? { valor: e, etiqueta: e, severidad: 'secondary' as const }

/** Por defecto: las completadas, las más recientes primero (lo que pidió el negocio). */
export function filtroInicial(): FiltroCompras {
  return {
    estados: ['completado'],
    creadoDesde: null,
    creadoHasta: null,
    salidaDesde: null,
    salidaHasta: null,
    empresa: null,
    origen: null,
    destino: null,
    idaVuelta: 'todos',
    facturacion: null,
    marca: null,
    montoMinimo: null,
    montoMaximo: null,
    texto: '',
    orden: 'creado',
    direccion: 'desc',
    pagina: 1,
    porPagina: 25,
  }
}

/** Query string para el backend; sin los valores vacíos. Estados vacíos = todos. */
export function aQuery(f: FiltroCompras): string {
  const p = new URLSearchParams()
  p.set('estado', f.estados.join(','))
  const poner = (k: string, v: string | number | null | undefined) => {
    if (v !== null && v !== undefined && v !== '') p.set(k, String(v))
  }
  poner('creadoDesde', f.creadoDesde)
  poner('creadoHasta', f.creadoHasta)
  poner('salidaDesde', f.salidaDesde)
  poner('salidaHasta', f.salidaHasta)
  poner('empresa', f.empresa)
  poner('origen', f.origen)
  poner('destino', f.destino)
  if (f.idaVuelta !== 'todos') p.set('idaVuelta', f.idaVuelta)
  poner('facturacion', f.facturacion)
  poner('marca', f.marca)
  poner('montoMinimo', f.montoMinimo)
  poner('montoMaximo', f.montoMaximo)
  poner('q', f.texto.trim())
  p.set('orden', f.orden)
  p.set('direccion', f.direccion)
  p.set('pagina', String(f.pagina))
  p.set('porPagina', String(f.porPagina))
  return p.toString()
}

/** Cuántos filtros (además de estado y orden) están activos: para el botón "Más filtros". */
export function filtrosActivos(f: FiltroCompras): number {
  return [
    f.salidaDesde,
    f.salidaHasta,
    f.empresa,
    f.origen,
    f.destino,
    f.idaVuelta !== 'todos' ? f.idaVuelta : null,
    f.facturacion,
    f.marca,
    f.montoMinimo,
    f.montoMaximo,
  ].filter((v) => v !== null && v !== '').length
}

const dia = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

export type Rango = 'hoy' | 'ayer' | '7d' | '30d' | 'mes' | 'mesAnterior' | 'todo'

export const RANGOS: Array<{ valor: Rango; etiqueta: string }> = [
  { valor: 'hoy', etiqueta: 'Hoy' },
  { valor: 'ayer', etiqueta: 'Ayer' },
  { valor: '7d', etiqueta: '7 días' },
  { valor: '30d', etiqueta: '30 días' },
  { valor: 'mes', etiqueta: 'Este mes' },
  { valor: 'mesAnterior', etiqueta: 'Mes anterior' },
  { valor: 'todo', etiqueta: 'Todo' },
]

/** Fechas de compra (desde, hasta) de un rango rápido, respecto de `hoy`. */
export function rango(r: Rango, hoy = new Date()): { desde: string | null; hasta: string | null } {
  const h = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())
  const menos = (n: number) => new Date(h.getFullYear(), h.getMonth(), h.getDate() - n)
  switch (r) {
    case 'hoy':
      return { desde: dia(h), hasta: dia(h) }
    case 'ayer':
      return { desde: dia(menos(1)), hasta: dia(menos(1)) }
    case '7d':
      return { desde: dia(menos(6)), hasta: dia(h) }
    case '30d':
      return { desde: dia(menos(29)), hasta: dia(h) }
    case 'mes':
      return { desde: dia(new Date(h.getFullYear(), h.getMonth(), 1)), hasta: dia(h) }
    case 'mesAnterior':
      return { desde: dia(new Date(h.getFullYear(), h.getMonth() - 1, 1)), hasta: dia(new Date(h.getFullYear(), h.getMonth(), 0)) }
    default:
      return { desde: null, hasta: null }
  }
}

/** Calculadora de la selección: cuántas compras, asientos y cuánto suman. */
export function sumaSeleccion(compras: readonly CompraWeb[]): { compras: number; asientos: number; centavos: number } {
  return compras.reduce(
    (t, c) => ({
      compras: t.compras + 1,
      asientos: t.asientos + c.ventas.reduce((n, v) => n + v.cantidad, 0),
      centavos: t.centavos + c.monto.centavos,
    }),
    { compras: 0, asientos: 0, centavos: 0 },
  )
}

export const quetzales = (centavos: number) =>
  new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' }).format(centavos / 100)
