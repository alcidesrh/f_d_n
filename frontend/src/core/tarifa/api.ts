/** Transporte del mapa de tarifas (`/api/gestion-tarifas/*`, permiso `salida.ver`). */
import { http } from '@/core/http'
import type { RutaTarifasMapa } from './mapa'

export const fetchTarifasDeRuta = (rutaId: number) =>
  http.get<RutaTarifasMapa>(`/gestion-tarifas/ruta/${rutaId}`)

export const fetchTarifasDeTrayecto = (origenId: number, destinoId: number) =>
  http.get<RutaTarifasMapa>(`/gestion-tarifas/trayecto/${origenId}/${destinoId}`)
