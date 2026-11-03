/** Transporte del seguimiento de buses (`/api/seguimiento/*`, permiso `salida.ver`). */
import { http } from '@/core/http'
import type { RespuestaSeguimiento, RespuestaTrayectorias, Trayectoria } from './types'

export const fetchBusesEnRecorrido = (silent = false) => http.get<RespuestaSeguimiento>('/seguimiento/buses', { silent })

/** Salidas de un día (`AAAA-MM-DD`) con lecturas GPS guardadas (ADR-050). */
export const fetchTrayectorias = (fecha: string) => http.get<RespuestaTrayectorias>(`/seguimiento/trayectorias?fecha=${encodeURIComponent(fecha)}`)

/** Trayectoria GPS de una salida, para reproducirla. */
export const fetchTrayectoria = (salidaId: number) => http.get<Trayectoria>(`/seguimiento/salidas/${salidaId}/trayectoria`)
