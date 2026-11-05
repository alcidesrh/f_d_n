/** Transporte de `/api/actividad` (bitácora de actividad, acciones de pantalla). */
import { http, request } from '@/core/http'
import type { EventoPantalla } from './cola'

export interface AjustesActividad {
  /** Si esta SPA debe mandar sus acciones de pantalla (nivel máximo y usuario seguido). */
  registrar: boolean
  intervaloSegundos: number
}

export const fetchAjustesActividad = () => http.get<AjustesActividad>('/actividad/ajustes', { silent: true })

/** `keepalive`: sale aunque la pestaña se esté cerrando. Un 401 no cierra la sesión (es de fondo). */
export const enviarActividad = (eventos: EventoPantalla[], keepalive = false) =>
  request<null>('/actividad', { method: 'POST', body: { eventos }, silent: true, keepalive, skipUnauthorized: true })
