/** Transporte del listado de boletos (`/api/boletos`, permiso `boleto.ver`; cancelar y revertir con `boleto.cancelar`). */
import { config } from '@/core/config'
import { HttpError, http } from '@/core/http'
import { useSessionStore } from '@/core/auth/session'
import type { DetalleBoleto, OpcionesBoletos, PaginaBoletos } from './types'

const BASE = '/boletos'

export const fetchOpcionesBoletos = () => http.get<OpcionesBoletos>(`${BASE}/opciones`)

export const fetchBoletosListado = (query: string) => http.get<PaginaBoletos>(`${BASE}?${query}`)

export const fetchDetalleBoleto = (id: number) => http.get<DetalleBoleto>(`${BASE}/${id}`)

export const cancelarBoleto = (id: number, motivo: string) => http.post<{ id: number; estado: string }>(`${BASE}/${id}/cancelar`, { motivo })

export const revertirBoleto = (id: number) => http.post<{ id: number; estado: string }>(`${BASE}/${id}/revertir`, {})

export const enviarBoletoPorCorreo = (id: number, correo: string) => http.post<{ correo: string }>(`${BASE}/${id}/correo`, { correo })

/** PDF del boleto como `Blob` (lleva el Bearer, por eso no es un enlace). */
export async function fetchBoletoPdf(id: number): Promise<Blob> {
  const token = useSessionStore().token
  const res = await fetch(`${config.restUrl}${BASE}/${id}/pdf`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) {
    const cuerpo = (await res.json().catch(() => null)) as { error?: string } | null
    throw new HttpError(res.status, cuerpo, cuerpo?.error ?? `HTTP ${res.status}`)
  }
  return res.blob()
}
