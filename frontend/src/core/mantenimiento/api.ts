/** Transporte del mantenimiento de flota (`/api/mantenimiento/*`, permiso `orden_mantenimiento.ver`). */
import { http } from '@/core/http'
import type { RespuestaMantenimiento } from './estado'

/** En qué va cada bus con cada plan activo (ADR-065). */
export const fetchMantenimiento = () => http.get<RespuestaMantenimiento>('/mantenimiento/estado')
