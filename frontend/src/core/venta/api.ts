/**
 * Transporte de la venta de asientos (`/api/venta/*`, `/api/agencias/*`).
 * Los errores de negocio llegan como `HttpError` con `body: ErrorVenta`.
 */
import { config } from '@/core/config'
import { HttpError, http, request } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import type {
  AsientoOcupado,
  BoletoOperable,
  Cliente,
  ClienteDatos,
  Comprobante,
  ContextoVenta,
  Cotizacion,
  DetalleAsiento,
  ErrorVenta,
  EstadoAgencia,
  PedidoReasignacion,
  PedidoVenta,
  PermisosBoleto,
  ResultadoAnulacion,
  SalidaDetalle,
  SalidaResumen,
} from './types'

export const fetchContexto = () => http.get<ContextoVenta>('/venta/contexto')

export const fetchSalidas = (fecha: string, estacion: number | null) =>
  http.get<SalidaResumen[]>(
    `/venta/salidas?fecha=${encodeURIComponent(fecha)}${estacion ? `&estacion=${estacion}` : ''}`,
  )

export const fetchSalida = (id: number) => http.get<SalidaDetalle>(`/venta/salidas/${id}`)

/** Ocupación para un trayecto; `silent` para refrescos por Mercure. */
export async function fetchOcupacion(
  salida: number,
  trayecto: number | null,
  silent = false,
): Promise<AsientoOcupado[]> {
  const q = trayecto ? `?trayecto=${trayecto}` : ''
  return (
    await http.get<{ asientos: AsientoOcupado[] }>(`/venta/salidas/${salida}/ocupacion${q}`, {
      silent,
    })
  ).asientos
}

export const fetchDetalleAsiento = (salida: number, asiento: number) =>
  http.get<DetalleAsiento>(`/venta/salidas/${salida}/asientos/${asiento}`, { silent: true })

export const cotizar = (body: {
  salida: number
  trayecto: number | null
  asientos: number[]
  cobrarTrayectoCompleto?: boolean
  cortesia?: boolean
  /** Al reasignar: cotiza como esa venta (su cortesía y su recargo). */
  venta?: number
}) => http.post<Cotizacion>('/venta/cotizacion', body, { silent: true })

export const vender = (pedido: PedidoVenta) =>
  http.post<Comprobante>('/venta/ventas', pedido, { loadingKey: 'venta' })

/** Comprobante de una venta; con `boletos`, solo esos (de un mismo viaje). */
export const fetchComprobante = (id: number, boletos?: number[]) =>
  http.get<Comprobante>(`/venta/ventas/${id}${boletos?.length ? `?boletos=${boletos.join(',')}` : ''}`)

export const fetchPermisosBoleto = () => http.get<PermisosBoleto>('/venta/boletos/permisos', { silent: true })

export const fetchBoletos = (ids: number[]) =>
  http.get<BoletoOperable[]>(`/venta/boletos?ids=${ids.join(',')}`)

export const anularBoletos = (boletos: number[], motivo: string) =>
  http.post<ResultadoAnulacion>('/venta/boletos/anular', { boletos, motivo }, { loadingKey: 'anular-boletos' })

/** Devuelve el comprobante de los boletos nuevos (para imprimir el ticket). */
export const reasignarBoletos = (pedido: PedidoReasignacion) =>
  http.post<Comprobante>('/venta/boletos/reasignar', pedido, { loadingKey: 'reasignar-boletos' })

export const buscarClientes = (q: string) =>
  http.get<Cliente[]>(`/venta/clientes?q=${encodeURIComponent(q)}`, { silent: true })

/** Razón social de un NIT según la SAT (404 si no existe). */
export const consultarNit = (nit: string) =>
  http.get<{ nit: string; nombre: string }>(`/venta/nit?nit=${encodeURIComponent(nit)}`, {
    silent: true,
  })

export const crearCliente = (datos: ClienteDatos) => http.post<Cliente>('/venta/clientes', datos)

export const editarCliente = (id: number, datos: ClienteDatos) =>
  request<Cliente>(`/venta/clientes/${id}`, { method: 'PUT', body: datos })

export const fetchSaldoAgencia = (id: number) => http.get<EstadoAgencia>(`/agencias/${id}/saldo`)

export const depositarAgencia = (
  id: number,
  body: { importe: number; referencia?: string; observacion?: string; bonificacion?: boolean },
) => http.post<EstadoAgencia>(`/agencias/${id}/depositos`, body)

export const ajustarAgencia = (id: number, body: { importe: number; observacion: string }) =>
  http.post<EstadoAgencia>(`/agencias/${id}/ajustes`, body)

/** PDF del comprobante como `Blob` (lleva el Bearer, por eso no es un enlace). */
export async function fetchComprobantePdf(id: number): Promise<Blob> {
  const token = useSessionStore().token
  const res = await fetch(`${config.restUrl}/venta/ventas/${id}/pdf`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) throw new HttpError(res.status, null, `HTTP ${res.status}`)
  return res.blob()
}

/** Cuerpo de negocio de un error de la API de venta, si lo es. */
export function errorVenta(error: unknown): ErrorVenta | null {
  if (!(error instanceof HttpError)) return null
  const body = error.body as Partial<ErrorVenta> | null
  return body && typeof body.error === 'string'
    ? ({ codigo: 'error', ...body } as ErrorVenta)
    : null
}
