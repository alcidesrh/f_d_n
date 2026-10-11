/** Transporte de la gestión de rutas (`/api/gestion-rutas/*`; ver `salida.ver`, editar `ruta.editar`, ADR-030). */
import { http, request } from '@/core/http'
import type { OpcionesRutas, RutaDetalle, RutaFila, RutaPayload } from './types'

const BASE = '/gestion-rutas'

export const fetchRutas = () => http.get<{ items: RutaFila[]; puedeEditar: boolean }>(BASE)

export const fetchOpcionesRutas = () => http.get<OpcionesRutas>(`${BASE}/opciones`)

export const fetchRuta = (id: number) => http.get<RutaDetalle>(`${BASE}/${id}`)

export const crearRuta = (p: RutaPayload) => http.post<RutaDetalle>(BASE, p)

export const guardarRuta = (id: number, p: RutaPayload) => request<RutaDetalle>(`${BASE}/${id}`, { method: 'PUT', body: p })

/** Copia inactiva de la ruta; con `invertir`, la de regreso. */
export const duplicarRuta = (id: number, invertir: boolean) => http.post<RutaDetalle>(`${BASE}/${id}/duplicar`, { invertir })

export const eliminarRuta = (id: number) => http.delete<null>(`${BASE}/${id}`)
