/** Transporte del dashboard de la página web (`/api/pagina/*`, permiso `pagina.administrar`). */
import { http, request } from '@/core/http'
import type { AccionCompra, ConfiguracionPagina, DatosComprador, FacturaReemitida, MensajeContacto, Opcion, PaginaCompras } from './types'

export const fetchCompras = (query: string, silent = false) => http.get<PaginaCompras>(`/pagina/compras?${query}`, { silent })

// Acciones sobre una compra (anular y reasignar van por `/venta/boletos/*`).
export const editarComprador = (id: number, datos: DatosComprador) => request<AccionCompra>(`/pagina/compras/${id}/comprador`, { method: 'PUT', body: datos })

export const posponerCompra = (id: number, motivo: string) => request<AccionCompra<number[]>>(`/pagina/compras/${id}/posponer`, { method: 'POST', body: { motivo } })

export const reemitirFactura = (id: number) => request<AccionCompra<FacturaReemitida>>(`/pagina/compras/${id}/factura`, { method: 'POST', body: {} })

export const reenviarBoleto = (id: number, correo: string) => request<AccionCompra<string>>(`/pagina/compras/${id}/correo`, { method: 'POST', body: { correo } })

export const fetchOpciones = () =>
  http.get<{ empresas: Opcion[]; estaciones: Array<Opcion & { departamento: string | null }>; departamento: string | null }>('/pagina/opciones')

export const fetchConfiguracion = () => http.get<ConfiguracionPagina>('/pagina/configuracion')

export const guardarConfiguracion = (datos: Pick<ConfiguracionPagina, 'recargoPorciento' | 'ventaEnLinea' | 'cierreMinutos'>) =>
  request<ConfiguracionPagina>('/pagina/configuracion', { method: 'PUT', body: datos })

export const fetchMensajes = () => http.get<{ items: MensajeContacto[]; sinLeer: number }>('/pagina/mensajes')

export const marcarMensaje = (id: number, leido: boolean) =>
  request<{ id: number; leido: boolean }>(`/pagina/mensajes/${id}`, { method: 'PATCH', body: { leido } })
