/** Transporte de `/api/configuracion-proveedores/*` (capacidad `config.proveedores`, ADR-047 y ADR-048). */
import { http, HttpError, request } from '@/core/http'
import type { CambiosCorreo, CambiosCybersource, CambiosForcon, ConfiguracionCorreo, ConfiguracionCybersource, ConfiguracionForcon, DatosEstablecimiento } from './types'

export const CAPACIDAD = 'config.proveedores'
const BASE = '/configuracion-proveedores'

export const URL_PRUEBAS_FORCON = 'https://pruebasfel.eforcon.com'
export const URLS_CYBERSOURCE = { pruebas: 'https://apitest.cybersource.com', produccion: 'https://api.cybersource.com' } as const

export const fetchForcon = () => http.get<ConfiguracionForcon>(`${BASE}/forcon`)

export const guardarForcon = (datos: CambiosForcon) => request<ConfiguracionForcon>(`${BASE}/forcon`, { method: 'PUT', body: datos })

export const probarForcon = (nit: string, establecimiento: number) =>
  request<DatosEstablecimiento>(`${BASE}/forcon/probar`, { method: 'POST', body: { nit, establecimiento } })

export const fetchCybersource = () => http.get<ConfiguracionCybersource>(`${BASE}/cybersource`)

export const guardarCybersource = (datos: CambiosCybersource) => request<ConfiguracionCybersource>(`${BASE}/cybersource`, { method: 'PUT', body: datos })

export const fetchConfigCorreo = () => http.get<ConfiguracionCorreo>(`${BASE}/correo`)

export const guardarConfigCorreo = (datos: CambiosCorreo) => request<ConfiguracionCorreo>(`${BASE}/correo`, { method: 'PUT', body: datos })

export const probarConfigCorreo = (dominio: string, para: string) =>
  request<{ ok: true }>(`${BASE}/correo/probar`, { method: 'POST', body: { dominio, para } })

/** Dominio en minúsculas como lo valida el backend (`ejemplo.com`, `correo.ejemplo.com`). */
export const dominioValido = (d: string) => /^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(d.trim().toLowerCase())

export const direccionValida = (d: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.trim())

/** Clave pública Ed25519 en base64 (32 bytes), como la da MailPace. */
export function claveValida(clave: string): boolean {
  const c = clave.trim()
  if (c === '') return true
  try {
    return atob(c).length === 32
  } catch {
    return false
  }
}

/** Mensaje para el usuario de un error de estos endpoints (`{ error }` del backend o el de la excepción). */
export function mensajeDeError(e: unknown): string {
  const cuerpo = e instanceof HttpError ? (e.body as { error?: string } | null) : null
  return cuerpo?.error ?? (e instanceof Error ? e.message : String(e))
}

/** URL https sin barra final, como la valida el backend. */
export function urlValida(url: string): boolean {
  try {
    return new URL(url.trim()).protocol === 'https:'
  } catch {
    return false
  }
}
