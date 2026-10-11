/**
 * Elegir el bus de una salida (ADR-027, ADR-031): primero la distribución de
 * asientos (molde de croquis) y luego un bus con ese molde. Toda salida
 * tiene bus; con asientos vendidos se cambia por otro del mismo molde. Puro.
 */
import type { BusOpcion, CroquisMolde, RutaOpcion } from './types'

/**
 * Buses con ese molde (cualquiera si no hay molde) y de esa empresa (si se
 * pide). El ya elegido (`actual`) se conserva aunque no cumpla, para no
 * vaciar una selección hecha antes.
 */
export function busesPara(buses: readonly BusOpcion[], f: { croquisId: number | null; empresaId?: number | null }, actual?: number | null): BusOpcion[] {
  return buses.filter((b) => b.id === actual || ((f.croquisId === null || b.croquisId === f.croquisId) && (!f.empresaId || !b.empresaId || b.empresaId === f.empresaId)))
}

/** `47 asientos · 12 B · 2 plantas` */
export function etiquetaCroquis(m: Pick<CroquisMolde, 'asientos' | 'asientosB' | 'plantas'>): string {
  return [
    `${m.asientos} asientos`,
    m.asientosB > 0 && m.asientosB < m.asientos ? `${m.asientosB} B` : m.asientosB === m.asientos ? 'todos B' : null,
    m.plantas === 2 ? '2 plantas' : null,
  ]
    .filter(Boolean)
    .join(' · ')
}

/**
 * Minutos hasta la última parada con esa clase de bus (como
 * `Ruta::duracionPara`): los de la clase o, si no tiene, los generales.
 */
export function duracionConClase(r: Pick<RutaOpcion, 'duracionMinutos' | 'duracionGeneral' | 'duracionPorClase'>, claseId: number | null): number | null {
  const propia = claseId !== null ? r.duracionPorClase?.[String(claseId)] : undefined
  return propia ?? r.duracionGeneral ?? r.duracionMinutos
}

/** `HH:MM` + minutos → la hora de llegada y cuántos días después. */
export function sumarMinutos(hora: string, minutos: number): { hora: string; dias: number } {
  const [h, m] = hora.split(':').map(Number)
  const total = (h ?? 0) * 60 + (m ?? 0) + minutos
  const enDia = ((total % 1440) + 1440) % 1440
  return { hora: `${String(Math.floor(enDia / 60)).padStart(2, '0')}:${String(enDia % 60).padStart(2, '0')}`, dias: Math.floor(total / 1440) }
}
