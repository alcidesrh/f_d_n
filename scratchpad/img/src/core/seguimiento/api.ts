/** Transporte del seguimiento de buses (`/api/seguimiento/*`, permiso `salida.ver`). */
import { http } from '@/core/http'
import type { RespuestaSeguimiento } from './types'

export const fetchBusesEnRecorrido = (silent = false) => http.get<RespuestaSeguimiento>('/seguimiento/buses', { silent })
