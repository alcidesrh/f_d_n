/** Transporte de la gestión de rutas (`/api/gestion-rutas/*`; ver `salida.ver`, editar `ruta.editar`, ADR-030). */
import { http, request } from '@/core/http'
import type { RutaOpcion } from '@/core/salida/types'
import type { OpcionesRutas, RutaDetalle, RutaFila, RutaPayload } from './types'

const BASE = '/gestion-rutas'

/** Rutas que coinciden con `q`, buscadas en la base de datos (`truncado` si hay más del límite). */
export const fetchRutas = (q: string, inactivas: boolean) =>
  http.get<{ items: RutaFila[]; truncado: boolean; puedeEditar: boolean }>(
    `${BASE}?${new URLSearchParams({ q, inactivas: inactivas ? '1' : '0' })}`,
  )

/** Opciones del programador: las que coinciden con `q` y las de `ids` (la ya elegida). */
export const fetchBuscarRutas = (q: string, ids: number[] = []) =>
  http.get<{ items: RutaOpcion[] }>(
    `${BASE}/buscar?${new URLSearchParams({ q, ids: ids.join(',') })}`,
  )

export const fetchOpcionesRutas = () => http.get<OpcionesRutas>(`${BASE}/opciones`)

export const fetchRuta = (id: number) => http.get<RutaDetalle>(`${BASE}/${id}`)

export const crearRuta = (p: RutaPayload) => http.post<RutaDetalle>(BASE, p)

export const guardarRuta = (id: number, p: RutaPayload) =>
  request<RutaDetalle>(`${BASE}/${id}`, { method: 'PUT', body: p })

/** Copia inactiva de la ruta; con `invertir`, la de regreso. */
export const duplicarRuta = (id: number, invertir: boolean) =>
  http.post<RutaDetalle>(`${BASE}/${id}/duplicar`, { invertir })

export const eliminarRuta = (id: number) => http.delete<null>(`${BASE}/${id}`)
