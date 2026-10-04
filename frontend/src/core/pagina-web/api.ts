/** Transporte del dashboard de la página web (`/api/pagina/*`, permiso `pagina.administrar`). */
import { http, request } from '@/core/http'
import type { ConfiguracionPagina, MensajeContacto, Opcion, PaginaCompras } from './types'

export const fetchCompras = (query: string, silent = false) => http.get<PaginaCompras>(`/pagina/compras?${query}`, { silent })

export const fetchOpciones = () =>
  http.get<{ empresas: Opcion[]; estaciones: Array<Opcion & { departamento: string | null }>; departamento: string | null }>('/pagina/opciones')

export const fetchConfiguracion = () => http.get<ConfiguracionPagina>('/pagina/configuracion')

export const guardarConfiguracion = (datos: Pick<ConfiguracionPagina, 'recargoPorciento' | 'ventaEnLinea' | 'cierreMinutos'>) =>
  request<ConfiguracionPagina>('/pagina/configuracion', { method: 'PUT', body: datos })

export const fetchMensajes = () => http.get<{ items: MensajeContacto[]; sinLeer: number }>('/pagina/mensajes')

export const marcarMensaje = (id: number, leido: boolean) =>
  request<{ id: number; leido: boolean }>(`/pagina/mensajes/${id}`, { method: 'PATCH', body: { leido } })
