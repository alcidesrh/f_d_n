/** Transporte del listado de boletos (`/api/boletos`, permiso `boleto.ver`; cancelar y revertir con `boleto.cancelar`). */
import { http } from '@/core/http'
import type { DetalleBoleto, OpcionesBoletos, PaginaBoletos } from './types'

const BASE = '/boletos'

export const fetchOpcionesBoletos = () => http.get<OpcionesBoletos>(`${BASE}/opciones`)

export const fetchBoletosListado = (query: string) => http.get<PaginaBoletos>(`${BASE}?${query}`)

export const fetchDetalleBoleto = (id: number) => http.get<DetalleBoleto>(`${BASE}/${id}`)

export const cancelarBoleto = (id: number, motivo: string) => http.post<{ id: number; estado: string }>(`${BASE}/${id}/cancelar`, { motivo })

export const revertirBoleto = (id: number) => http.post<{ id: number; estado: string }>(`${BASE}/${id}/revertir`, {})

export const enviarBoletoPorCorreo = (id: number, correo: string) => http.post<{ correo: string }>(`${BASE}/${id}/correo`, { correo })
