/**
 * Elegir el bus de una salida (ADR-027): primero la distribución de asientos
 * (molde de croquis) y la clase de bus, luego un bus compatible. Puro.
 */
import type { BusOpcion, CroquisMolde } from './types'

export interface FiltroBus {
  croquisId: number | null
  claseId: number | null
}

export const filtroBusVacio = (): FiltroBus => ({ croquisId: null, claseId: null })

/** El filtro con el croquis y la clase de ese bus (vacío si no hay bus). */
export const filtroDeBus = (bus: BusOpcion | null | undefined): FiltroBus => ({
  croquisId: bus?.croquisId ?? null,
  claseId: bus?.claseId ?? null,
})

export const cumpleFiltro = (bus: BusOpcion, f: FiltroBus): boolean =>
  (f.croquisId === null || bus.croquisId === f.croquisId) && (f.claseId === null || bus.claseId === f.claseId)

/**
 * Buses que cumplen el filtro. El ya elegido (`actual`) se conserva aunque no
 * lo cumpla, para no vaciar una selección hecha antes de filtrar.
 */
export function busesCompatibles(buses: readonly BusOpcion[], f: FiltroBus, actual?: number | null): BusOpcion[] {
  return buses.filter((b) => b.id === actual || cumpleFiltro(b, f))
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
