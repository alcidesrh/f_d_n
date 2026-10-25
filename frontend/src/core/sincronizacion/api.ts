/** Transporte de la sincronización con el sistema anterior (`/api/sincronizacion`, ADR-042). */
import { http, request } from '@/core/http'
import type { EstadoSincronizacion } from './reglas'

const BASE = '/sincronizacion'

export const fetchEstado = (silent = true) => http.get<EstadoSincronizacion>(BASE, { silent })

export const cambiar = (activo: boolean) =>
  request<EstadoSincronizacion & { aviso: string | null }>(BASE, {
    method: 'PUT',
    body: { activo },
  })
