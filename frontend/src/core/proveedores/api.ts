/** Transporte de `/api/configuracion-proveedores/*` (capacidad `config.proveedores`, ADR-047). */
import { http, HttpError, request } from '@/core/http'
import type { CambiosCybersource, CambiosForcon, ConfiguracionCybersource, ConfiguracionForcon, DatosEstablecimiento } from './types'

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
