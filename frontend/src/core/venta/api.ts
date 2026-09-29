/**
 * Transporte de la venta de asientos (`/api/venta/*`, `/api/agencias/*`).
 * Los errores de negocio llegan como `HttpError` con `body: ErrorVenta`.
 */
import { config } from '@/core/config'
import { HttpError, http, request } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import type {
  AsientoOcupado,
  Cliente,
  ClienteDatos,
  Comprobante,
  ContextoVenta,
  Cotizacion,
  ErrorVenta,
  EstadoAgencia,
  PedidoVenta,
  RecorridoDetalle,
  RecorridoResumen,
} from './types'

export const fetchContexto = () => http.get<ContextoVenta>('/venta/contexto')

export const fetchRecorridos = (fecha: string, estacion: number | null) =>
  http.get<RecorridoResumen[]>(
    `/venta/recorridos?fecha=${encodeURIComponent(fecha)}${estacion ? `&estacion=${estacion}` : ''}`,
  )

export const fetchRecorrido = (id: number) => http.get<RecorridoDetalle>(`/venta/recorridos/${id}`)

/** Ocupación para un trayecto; `silent` para refrescos por Mercure. */
export async function fetchOcupacion(
  recorrido: number,
  trayecto: number | null,
  silent = false,
): Promise<AsientoOcupado[]> {
  const q = trayecto ? `?trayecto=${trayecto}` : ''
  return (
    await http.get<{ asientos: AsientoOcupado[] }>(`/venta/recorridos/${recorrido}/ocupacion${q}`, {
      silent,
    })
  ).asientos
}

export const cotizar = (body: {
  recorrido: number
  trayecto: number | null
  asientos: number[]
  cobrarTrayectoCompleto?: boolean
  cortesia?: boolean
}) => http.post<Cotizacion>('/venta/cotizacion', body, { silent: true })

export const vender = (pedido: PedidoVenta) =>
  http.post<Comprobante>('/venta/ventas', pedido, { loadingKey: 'venta' })

export const fetchComprobante = (id: number) => http.get<Comprobante>(`/venta/ventas/${id}`)

export const buscarClientes = (q: string) =>
  http.get<Cliente[]>(`/venta/clientes?q=${encodeURIComponent(q)}`, { silent: true })

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
