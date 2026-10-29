/**
 * Arrastrar y soltar con eventos de puntero (ratón, lápiz y táctil) sobre
 * celdas marcadas con `data-celda`. Un gesto que no supera el umbral es un
 * clic y no se trata como arrastre.
 *
 * El "fantasma" que sigue al puntero lo pinta el componente con `puntero`.
 */
import { onBeforeUnmount, ref, shallowRef } from 'vue'

const UMBRAL_PX = 5

export interface OpcionesArrastre<T> {
  /** Suelta sobre una celda (`clave`) o fuera de toda celda (`null`). */
  soltar: (carga: T, clave: string | null) => void
}

export function usePointerDrag<T>(opciones: OpcionesArrastre<T>) {
  /** Carga del arrastre en curso (null si no se arrastra). */
  const arrastrando = shallowRef<T | null>(null)
  /** Celda bajo el puntero durante el arrastre. */
  const destino = ref<string | null>(null)
  const puntero = ref({ x: 0, y: 0 })

  let pendiente: { carga: T; x: number; y: number; id: number } | null = null
  /** El último gesto fue un arrastre: el `click` que le sigue no cuenta. */
  let ultimoFueArrastre = false

  function celdaEn(x: number, y: number): string | null {
    const el = document.elementFromPoint(x, y)
    return el?.closest<HTMLElement>('[data-celda]')?.dataset.celda ?? null
  }

  function onMove(event: PointerEvent) {
    if (!pendiente || event.pointerId !== pendiente.id) return
    if (!arrastrando.value) {
      if (Math.hypot(event.clientX - pendiente.x, event.clientY - pendiente.y) < UMBRAL_PX) return
      arrastrando.value = pendiente.carga
    }
    event.preventDefault()
    puntero.value = { x: event.clientX, y: event.clientY }
    destino.value = celdaEn(event.clientX, event.clientY)
  }

  function onUp(event: PointerEvent) {
    if (!pendiente || event.pointerId !== pendiente.id) return
    const carga = arrastrando.value
    const clave = carga ? celdaEn(event.clientX, event.clientY) : null
    terminar()
    if (!carga) return
    ultimoFueArrastre = true
    // El `click` llega en la misma tarea que el `pointerup`: pasada esta, se olvida.
    setTimeout(() => (ultimoFueArrastre = false))
    opciones.soltar(carga, clave)
  }

  function terminar() {
    pendiente = null
    arrastrando.value = null
    destino.value = null
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
    window.removeEventListener('pointercancel', terminar)
  }

  /** Empieza un posible arrastre (llamar en `pointerdown`). */
  function iniciar(event: PointerEvent, carga: T) {
    if (event.button !== 0) return
    terminar()
    pendiente = { carga, x: event.clientX, y: event.clientY, id: event.pointerId }
    puntero.value = { x: event.clientX, y: event.clientY }
    window.addEventListener('pointermove', onMove, { passive: false })
    window.addEventListener('pointerup', onUp)
    window.addEventListener('pointercancel', terminar)
  }

  /** ¿El gesto que produjo este `click` fue un arrastre? */
  function consumirClick(): boolean {
    const fue = ultimoFueArrastre
    ultimoFueArrastre = false
    return fue
  }

  onBeforeUnmount(terminar)

  return { arrastrando, destino, puntero, iniciar, cancelar: terminar, consumirClick }
}
