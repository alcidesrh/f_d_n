/** Transporte del seguimiento de buses (`/api/seguimiento/*`, permiso `salida.ver`). */
import { http } from '@/core/http'
import type { RespuestaSeguimiento, RespuestaTrayectorias, Trayectoria, TrayectoriaDeTelefono } from './types'

export const fetchBusesEnRecorrido = (silent = false) => http.get<RespuestaSeguimiento>('/seguimiento/buses', { silent })

/** Salidas de un día (`AAAA-MM-DD`) con lecturas GPS guardadas (ADR-050). */
export const fetchTrayectorias = (fecha: string) => http.get<RespuestaTrayectorias>(`/seguimiento/trayectorias?fecha=${encodeURIComponent(fecha)}`)

/** Trayectoria GPS de una salida, para reproducirla. */
export const fetchTrayectoria = (salidaId: number) => http.get<Trayectoria>(`/seguimiento/salidas/${salidaId}/trayectoria`)

/** Trayectoria de un teléfono de prueba que empieza en `desde` (epoch), para reproducirla. */
export const fetchTrayectoriaDeTelefono = (dispositivo: string, desde: number) =>
  http.get<TrayectoriaDeTelefono>(`/seguimiento/telefonos/trayectoria?dispositivo=${encodeURIComponent(dispositivo)}&desde=${desde}`)

/** Borra la trayectoria GPS de una salida (sus lecturas; la salida se queda). Permiso `salida.eliminar`. */
export const borrarTrayectoria = (salidaId: number) => http.delete<{ borradas: number }>(`/seguimiento/salidas/${salidaId}/trayectoria`)

/** Borra la trayectoria de un teléfono de prueba que empieza en `desde` (epoch). */
export const borrarTrayectoriaDeTelefono = (dispositivo: string, desde: number) =>
  http.delete<{ borradas: number }>(`/seguimiento/telefonos/trayectoria?dispositivo=${encodeURIComponent(dispositivo)}&desde=${desde}`)
