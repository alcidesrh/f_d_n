/** Transporte de `/api/suplantacion/*` (capacidad `iam.suplantar`, solo administradores del sistema). */
import { http, HttpError, request } from '@/core/http'
import type { CambiosSuplantacion, ConfiguracionSuplantacion } from './tipos'

export const fetchConfiguracionSuplantacion = () =>
  http.get<ConfiguracionSuplantacion>('/suplantacion/configuracion')

export const guardarConfiguracionSuplantacion = (cambios: CambiosSuplantacion) =>
  request<ConfiguracionSuplantacion>('/suplantacion/configuracion', {
    method: 'PUT',
    body: cambios,
  })

/** El mensaje del backend (`{ error }` de un `Rechazo`) o el del error. */
export function mensajeDeError(e: unknown): string {
  const cuerpo = e instanceof HttpError ? (e.body as { error?: string } | null) : null
  return cuerpo?.error ?? (e instanceof Error ? e.message : String(e))
}
