import { esAsiento, plantasDe } from '@/core/croquis/model'
import type { ElementoCroquis } from '@/core/croquis/types'

/** Plantas del croquis con la que tiene clase B delante (en los de dos pisos va abajo). */
export function plantasClaseBPrimero(elementos: readonly ElementoCroquis[]): number[] {
  const conB = (p: number) => elementos.some((e) => e.planta === p && esAsiento(e) && e.clase === 'B')
  return [...plantasDe(elementos)].sort((a, b) => Number(conB(b)) - Number(conB(a)) || a - b)
}
