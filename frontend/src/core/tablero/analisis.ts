/** Lecturas derivadas del tablero (lo que el gerente debería notar). Puro (con tests). */
import type { CeldaDemanda, RutaTablero } from './types'

/** Franja más llena y más vacía del mapa de demanda, con al menos `minSalidas` salidas para no juzgar por una. */
export function extremosDemanda(celdas: CeldaDemanda[], minSalidas = 2): { llena: CeldaDemanda | null; vacia: CeldaDemanda | null } {
  const validas = celdas.filter((c) => c.ocupacion !== null && c.salidas >= minSalidas)
  if (!validas.length) return { llena: null, vacia: null }
  const orden = [...validas].sort((a, b) => b.ocupacion! - a.ocupacion! || b.salidas - a.salidas)
  return { llena: orden[0]!, vacia: orden.length > 1 ? orden[orden.length - 1]! : null }
}

/** Horas (de la primera a la última con salidas) que pinta el mapa de demanda. */
export function horasDemanda(celdas: CeldaDemanda[]): number[] {
  if (!celdas.length) return []
  const horas = celdas.map((c) => c.hora)
  const r: number[] = []
  for (let h = Math.min(...horas); h <= Math.max(...horas); h++) r.push(h)
  return r
}

export interface ResumenRutas {
  saturadas: number
  bajas: number
  /** Parte de los ingresos que aportan las rutas con señal `baja`. */
  ingresoBajas: number
  /** Cuántas rutas hacen el 80% de los ingresos (concentración). */
  rutas80: number
}

export function resumenRutas(rutas: RutaTablero[]): ResumenRutas {
  const total = rutas.reduce((s, r) => s + r.ingresos, 0)
  const bajas = rutas.filter((r) => r.senal === 'baja')
  let acumulado = 0
  let rutas80 = 0
  for (const r of [...rutas].sort((a, b) => b.ingresos - a.ingresos)) {
    if (total > 0 && acumulado >= total * 0.8) break
    acumulado += r.ingresos
    rutas80++
  }
  return {
    saturadas: rutas.filter((r) => r.senal === 'saturada').length,
    bajas: bajas.length,
    ingresoBajas: total > 0 ? bajas.reduce((s, r) => s + r.ingresos, 0) / total : 0,
    rutas80,
  }
}
