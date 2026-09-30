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

export const cotizar = (body: {
  salida: number
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
