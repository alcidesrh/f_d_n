/** Transporte de `/api/bitacora-diaria/*` (capacidades `bitacora.usuarios` y `bitacora.configurar`). */
import { http, HttpError, request } from '@/core/http'
import type { CambiosBitacora, ConfiguracionBitacora, DiaBitacora, DiaDeUsuario, UsuarioBuscado } from './types'

export const CAPACIDAD_CONSULTA = 'bitacora.usuarios'
export const CAPACIDAD_CONFIGURACION = 'bitacora.configurar'
const BASE = '/bitacora-diaria'

export const fetchDia = (fecha: string) => http.get<DiaBitacora>(`${BASE}/dia?fecha=${encodeURIComponent(fecha)}`)

export const fetchDiaDeUsuario = (usuario: number, fecha: string) =>
  http.get<DiaDeUsuario>(`${BASE}/dia/${usuario}?fecha=${encodeURIComponent(fecha)}`)

export const fetchConfiguracionBitacora = () => http.get<ConfiguracionBitacora>(`${BASE}/configuracion`)

export const guardarConfiguracionBitacora = (cambios: CambiosBitacora) =>
  request<ConfiguracionBitacora>(`${BASE}/configuracion`, { method: 'PUT', body: cambios })

export const buscarUsuarios = (q: string) =>
  http.get<UsuarioBuscado[]>(`${BASE}/usuarios?q=${encodeURIComponent(q)}`, { silent: true })

/** El mensaje del backend (`{ error }` de un `Rechazo`) o el del error. */
export function mensajeDeError(e: unknown): string {
  const cuerpo = e instanceof HttpError ? (e.body as { error?: string } | null) : null
  return cuerpo?.error ?? (e instanceof Error ? e.message : String(e))
}
