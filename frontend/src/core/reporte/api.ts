/** Transporte de los reportes de venta (`/api/reportes/*`, permiso `reporte.ventas`). */
import { config } from '@/core/config'
import { useSessionStore } from '@/core/auth/session'
import { HttpError, http } from '@/core/http'
import { prefijoLegado } from '@/temporal/legado/estado' // TEMPORAL-LEGADO
import type { FormatoReporte, OpcionesReporte, ResumenCuadre, ResumenDetalle } from './types'

const base = () => `${prefijoLegado()}/reportes` // TEMPORAL-LEGADO

export type ReporteId = 'cuadre-venta-boletos' | 'detalle-factura-boletos'

export const fetchOpciones = () => http.get<OpcionesReporte>(`${base()}/opciones`)

/** Cifras para la vista previa; petición de fondo (no enciende la barra de carga). */
export const fetchResumenCuadre = (query: string) => http.get<ResumenCuadre>(`${base()}/cuadre-venta-boletos?${query}&formato=resumen`, { silent: true })

export const fetchResumenDetalle = (query: string) => http.get<ResumenDetalle>(`${base()}/detalle-factura-boletos?${query}&formato=resumen`, { silent: true })

/** Archivo del reporte como `Blob` (lleva el Bearer, por eso no es un enlace). */
export async function fetchArchivo(reporte: ReporteId, query: string, formato: FormatoReporte): Promise<Blob> {
  const token = useSessionStore().token
  const res = await fetch(`${config.restUrl}${base()}/${reporte}?${query}&formato=${formato}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) {
    const cuerpo = (await res.json().catch(() => null)) as { error?: string } | null
    throw new HttpError(res.status, cuerpo, cuerpo?.error ?? `HTTP ${res.status}`)
  }
  return res.blob()
}
