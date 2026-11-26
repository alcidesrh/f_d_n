/** Transporte de las afectaciones (`/api/afectaciones`, ADR-070). */
import { http } from '@/core/http'
import type { Afectacion, CapacidadElegible, PaginaAfectaciones, ParaReportar, Reporte, VistaAfectaciones } from './tipos'

const BASE = '/afectaciones'

export function fetchAfectaciones(vista: VistaAfectaciones, tipo: number | null, pagina = 1, silent = false) {
  const q = new URLSearchParams({ vista, pagina: String(pagina) })
  if (tipo) q.set('tipo', String(tipo))
  return http.get<PaginaAfectaciones>(`${BASE}?${q}`, { silent })
}

export const fetchAfectacion = (id: number) => http.get<Afectacion>(`${BASE}/${id}`)

export const fetchParaReportar = () => http.get<ParaReportar>(`${BASE}/para-reportar`)

export const reportar = (r: Reporte) => http.post<Afectacion>(BASE, r)

export const editar = (id: number, descripcion: string) => http.post<Afectacion>(`${BASE}/${id}/editar`, { descripcion })

export const resolver = (id: number, resolucion: string) => http.post<Afectacion>(`${BASE}/${id}/resolver`, { resolucion: resolucion.trim() || null })

export const fetchCapacidades = () => http.get<CapacidadElegible[]>(`${BASE}/capacidades`)
