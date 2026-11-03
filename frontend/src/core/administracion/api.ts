/**
 * Cajas de `/administracion` (`/api/administracion/*`). Verlas, cualquiera
 * con sesión: el backend manda solo las de su audiencia con los enlaces que
 * puede abrir (ADR-049); configurarlas, `config.editar`. Todo lo que escribe
 * responde la configuración completa.
 */
import { http, request } from '@/core/http'

/** Ruta del frontend por nombre (con sus parámetros) o un path escrito a mano. */
export interface Destino {
  ruta: string | null
  parametros: Record<string, string>
  path: string | null
}

export interface Persona {
  id: number
  label: string
}


export interface Enlace {
  id: number
  etiqueta: string
  icono: string
  /** Otros nombres por los que se busca (los del legado). */
  claves: string[]
  abrir: Destino
  agregar: Destino | null
  activo: boolean
  /** Solo al configurar: los roles a los que se muestra; vacía, a todos. */
  audiencia?: Persona[]
}

export interface Seccion {
  id: number
  titulo: string
  icono: string
  /** Rampa de color (`blue`, `emerald`…), ver `COLORES`. */
  color: string
  activa: boolean
  enlaces: Enlace[]
  /** Solo al configurar: los roles a los que se muestra; vacía, a todos. */
  audiencia?: Persona[]
}

export interface Administracion {
  puedeConfigurar: boolean
  secciones: Seccion[]
}

export interface SeccionPayload {
  titulo: string
  icono: string
  color: string
  activa?: boolean
  /** Ids de rol; vacía, a todos. */
  audiencia?: number[]
}

export interface EnlacePayload {
  etiqueta: string
  icono: string
  claves: string[]
  abrir: Destino
  agregar: Destino | null
  activo?: boolean
  /** Ids de rol; vacía, a todos. */
  audiencia?: number[]
}

const BASE = '/administracion'

export const fetchAdministracion = (configurar = false) =>
  http.get<Administracion>(configurar ? `${BASE}?configurar=1` : BASE)

export const crearSeccion = (p: SeccionPayload) => http.post<Administracion>(`${BASE}/secciones`, p)

export const guardarSeccion = (id: number, p: SeccionPayload) =>
  request<Administracion>(`${BASE}/secciones/${id}`, { method: 'PUT', body: p })

export const eliminarSeccion = (id: number) => http.delete<Administracion>(`${BASE}/secciones/${id}`)

export const crearEnlace = (seccionId: number, p: EnlacePayload) =>
  http.post<Administracion>(`${BASE}/secciones/${seccionId}/enlaces`, p)

export const guardarEnlace = (id: number, p: EnlacePayload) =>
  request<Administracion>(`${BASE}/enlaces/${id}`, { method: 'PUT', body: p })

export const eliminarEnlace = (id: number) => http.delete<Administracion>(`${BASE}/enlaces/${id}`)

export const guardarOrden = (secciones: { id: number; enlaces: number[] }[]) =>
  request<Administracion>(`${BASE}/orden`, { method: 'PUT', body: { secciones } })
