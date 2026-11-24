/** Transporte del tablero de gestión (`GET /api/tablero`, permiso `reporte.ventas`). */
import { http } from '@/core/http'
import type { DiasTablero, DistribucionVentas, Tablero } from './types'

/** `silent`: la actualización automática no enciende la barra de carga. */
export function fetchTablero(dias: DiasTablero, empresa: number | null, silent = false): Promise<Tablero> {
  const query = new URLSearchParams({ dias: String(dias) })
  if (empresa !== null) query.set('empresa', String(empresa))
  return http.get<Tablero>(`/tablero?${query}`, { silent })
}

/** Reparto de lo vendido en un día o un rango (`AAAA-MM-DD`, ambos incluidos); sin empresa, todas las del alcance. */
export function fetchDistribucion(desde: string, hasta: string, empresa: number | null, silent = false): Promise<DistribucionVentas> {
  const query = new URLSearchParams({ desde, hasta })
  if (empresa !== null) query.set('empresa', String(empresa))
  return http.get<DistribucionVentas>(`/tablero/distribucion?${query}`, { silent })
}
