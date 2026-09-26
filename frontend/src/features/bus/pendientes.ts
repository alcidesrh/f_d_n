/**
 * Croquis que no se pudieron guardar tras crear su bus: el formulario pasa a
 * la URL de edición (y se vuelve a montar), así que el borrador se deja aquí
 * para que la sección lo recupere una vez.
 */
import type { ElementoCroquis } from '@/core/croquis/types'

const pendientes = new Map<number, ElementoCroquis[]>()

export function guardarPendiente(busId: number, elementos: ElementoCroquis[]): void {
  pendientes.set(busId, elementos)
}

export function recuperarPendiente(busId: number): ElementoCroquis[] | null {
  const elementos = pendientes.get(busId) ?? null
  pendientes.delete(busId)
  return elementos
}
