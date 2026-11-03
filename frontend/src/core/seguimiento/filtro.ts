/** Filtros del mapa por empresa, salida y fuente de la posición (reglas puras). */
import type { BusEnRecorrido, FiltroSeguimiento } from './types'

/** Por defecto solo los buses que están enviando GPS; `soloGps: false` muestra también los simulados. */
export const FILTRO_VACIO: FiltroSeguimiento = { empresaId: null, salidaId: null, soloGps: true }

const pasaFuente = (b: BusEnRecorrido, soloGps: boolean) => !soloGps || b.posicion.fuente === 'gps'

export function filtrarBuses(buses: BusEnRecorrido[], f: FiltroSeguimiento): BusEnRecorrido[] {
  return buses.filter((b) => pasaFuente(b, f.soloGps) && (f.empresaId === null || b.empresaId === f.empresaId) && (f.salidaId === null || b.salidaId === f.salidaId))
}

/** Salidas elegibles en el filtro: las de la empresa elegida (o todas) y de la fuente pedida, por hora de partida. */
export function salidasDisponibles(buses: BusEnRecorrido[], empresaId: number | null, soloGps = false): BusEnRecorrido[] {
  return buses.filter((b) => pasaFuente(b, soloGps) && (empresaId === null || b.empresaId === empresaId)).sort((a, b) => a.partida - b.partida || a.salidaId - b.salidaId)
}

/** Si la empresa o la salida elegidas ya no están en la respuesta (o dejaron de enviar GPS), vuelve a "todas". */
export function filtroVigente(buses: BusEnRecorrido[], f: FiltroSeguimiento): FiltroSeguimiento {
  const salidaOk = f.salidaId === null || buses.some((b) => b.salidaId === f.salidaId && pasaFuente(b, f.soloGps) && (f.empresaId === null || b.empresaId === f.empresaId))
  const empresaOk = f.empresaId === null || buses.some((b) => b.empresaId === f.empresaId)
  return { empresaId: empresaOk ? f.empresaId : null, salidaId: empresaOk && salidaOk ? f.salidaId : null, soloGps: f.soloGps }
}
