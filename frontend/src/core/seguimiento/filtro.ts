/** Filtros del mapa por empresa y salida (reglas puras). */
import type { BusEnRecorrido, FiltroSeguimiento } from './types'

export const FILTRO_VACIO: FiltroSeguimiento = { empresaId: null, salidaId: null }

export function filtrarBuses(buses: BusEnRecorrido[], f: FiltroSeguimiento): BusEnRecorrido[] {
  return buses.filter((b) => (f.empresaId === null || b.empresaId === f.empresaId) && (f.salidaId === null || b.salidaId === f.salidaId))
}

/** Salidas elegibles en el filtro: las de la empresa elegida (o todas), por hora de partida. */
export function salidasDisponibles(buses: BusEnRecorrido[], empresaId: number | null): BusEnRecorrido[] {
  return buses.filter((b) => empresaId === null || b.empresaId === empresaId).sort((a, b) => a.partida - b.partida || a.salidaId - b.salidaId)
}

/** Si la empresa o la salida elegidas ya no están en la respuesta, vuelve a "todas". */
export function filtroVigente(buses: BusEnRecorrido[], f: FiltroSeguimiento): FiltroSeguimiento {
  const salidaOk = f.salidaId === null || buses.some((b) => b.salidaId === f.salidaId && (f.empresaId === null || b.empresaId === f.empresaId))
  const empresaOk = f.empresaId === null || buses.some((b) => b.empresaId === f.empresaId)
  return { empresaId: empresaOk ? f.empresaId : null, salidaId: empresaOk && salidaOk ? f.salidaId : null }
}
