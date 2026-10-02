/** Transporte de la gestión de salidas (`/api/gestion-salidas/*`, permisos `salida.*`, ADR-024). */
import { http, request } from '@/core/http'
import type {
  CambioSalida,
  Esquema,
  EsquemaPayload,
  OpcionesSalidas,
  PaginaSalidas,
  ProgramacionPayload,
  Propagacion,
  ResultadoOperacion,
  ResultadoProgramacion,
  VistaPrevia,
} from './types'

const BASE = '/gestion-salidas'

export const fetchSalidas = (query: string, silent = false) => http.get<PaginaSalidas>(`${BASE}?${query}`, { silent })

export const fetchOpciones = () => http.get<OpcionesSalidas>(`${BASE}/opciones`)

export const vistaPrevia = (p: ProgramacionPayload) => http.post<VistaPrevia>(`${BASE}/programacion/vista-previa`, p, { silent: true })

export const programar = (p: ProgramacionPayload) => http.post<ResultadoProgramacion>(`${BASE}/programacion`, p)

export const fetchEsquemas = () => http.get<{ items: Esquema[] }>(`${BASE}/esquemas`)

export const crearEsquema = (e: EsquemaPayload) => http.post<Esquema>(`${BASE}/esquemas`, e)

export const guardarEsquema = (id: number, e: EsquemaPayload) => request<Esquema>(`${BASE}/esquemas/${id}`, { method: 'PUT', body: e })

export const eliminarEsquema = (id: number) => http.delete<{ id: number }>(`${BASE}/esquemas/${id}`)

export const fetchPropagacion = (id: number) => http.get<Propagacion>(`${BASE}/${id}/propagacion`)

export const editarSalida = (id: number, cambio: CambioSalida) => request<ResultadoOperacion>(`${BASE}/${id}`, { method: 'PUT', body: cambio })

export const anularSalida = (id: number, propagar: boolean) => http.post<ResultadoOperacion>(`${BASE}/${id}/anular`, { propagar })

export const eliminarSalida = (id: number, propagar: boolean) => http.delete<ResultadoOperacion>(`${BASE}/${id}${propagar ? '?propagar=1' : ''}`)
