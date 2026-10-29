/** Administración de accesos (`/api/acceso/*`, ADR-039): roles, asignaciones y consultas. */
import { http, request } from '@/core/http'
import type {
  AccesosUsuario,
  AsignacionPayload,
  CatalogoAcceso,
  FilaQuienPuede,
  FilaSinAlcance,
  Rol,
  RolPayload,
} from './tipos'

const BASE = '/acceso'

export const fetchCatalogo = () => http.get<CatalogoAcceso>(`${BASE}/catalogo`)

export const fetchRoles = () => http.get<Rol[]>(`${BASE}/roles`)

export const crearRol = (r: RolPayload) => http.post<Rol>(`${BASE}/roles`, r)

export const guardarRol = (id: number, r: RolPayload) => request<Rol>(`${BASE}/roles/${id}`, { method: 'PUT', body: r })

export const eliminarRol = (id: number) => http.delete<{ id: number }>(`${BASE}/roles/${id}`)

export const fetchAccesos = (usuarioId: number) => http.get<AccesosUsuario>(`${BASE}/usuarios/${usuarioId}`)

export const otorgar = (usuarioId: number, a: AsignacionPayload) => http.post<AccesosUsuario>(`${BASE}/usuarios/${usuarioId}/asignaciones`, a)

export const modificar = (asignacionId: number, a: AsignacionPayload) =>
  request<AccesosUsuario>(`${BASE}/asignaciones/${asignacionId}`, { method: 'PUT', body: a })

export const revocar = (asignacionId: number) => http.delete<AccesosUsuario>(`${BASE}/asignaciones/${asignacionId}`)

export const activar = (usuarioId: number, activo: boolean) => http.post<AccesosUsuario>(`${BASE}/usuarios/${usuarioId}/activo`, { activo })

export function fetchQuienPuede(capacidad: string, empresa: number | null, estacion: number | null) {
  const q = new URLSearchParams({ capacidad })
  if (empresa) q.set('empresa', String(empresa))
  if (estacion) q.set('estacion', String(estacion))
  return http.get<FilaQuienPuede[]>(`${BASE}/quien-puede?${q}`)
}

export const fetchSinAlcance = () => http.get<FilaSinAlcance[]>(`${BASE}/sin-alcance`)
