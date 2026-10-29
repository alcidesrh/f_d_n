/** Transporte de las autorizaciones (`/api/autorizaciones`, ADR-040). */
import { http } from '@/core/http'
import type { Autorizable, Autorizacion, EstadoAutorizacion, PaginaAutorizaciones, ResumenAutorizaciones, VistaAutorizaciones } from './types'

const BASE = '/autorizaciones'

export function fetchAutorizaciones(vista: VistaAutorizaciones, estado: EstadoAutorizacion | null, pagina = 1, silent = false) {
  const q = new URLSearchParams({ vista, pagina: String(pagina) })
  if (estado) q.set('estado', estado)
  return http.get<PaginaAutorizaciones>(`${BASE}?${q}`, { silent })
}

export const fetchResumen = (silent = true) => http.get<ResumenAutorizaciones>(`${BASE}/resumen`, { silent })

export const fetchAutorizacion = (id: number) => http.get<Autorizacion>(`${BASE}/${id}`)

export const solicitar = (a: Pick<Autorizable, 'capacidad' | 'dispensa' | 'sujeto' | 'ids' | 'cantidad'>, motivo: string) =>
  http.post<Autorizacion>(BASE, { capacidad: a.capacidad, dispensa: a.dispensa, sujeto: a.sujeto, ids: a.ids, cantidad: a.cantidad, motivo })

export const aprobar = (id: number, nota: string) => http.post<Autorizacion>(`${BASE}/${id}/aprobar`, { nota: nota.trim() || null })

export const rechazar = (id: number, motivo: string) => http.post<Autorizacion>(`${BASE}/${id}/rechazar`, { motivo })

export const retirar = (id: number) => http.post<Autorizacion>(`${BASE}/${id}/retirar`)
