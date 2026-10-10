/**
 * Reordenar columnas arrastrando su cabecera (GSAP Draggable, solo en x).
 *
 * Se arrastra desde el tirador (`[data-grid-grip]`) para que el resto de la
 * cabecera siga sirviendo para ordenar, filtrar u ocultar. Mientras se
 * arrastra, las cabeceras que quedan en medio se corren el ancho de la
 * arrastrada; al soltar se emite `(from, to)` y la arrastrada aterriza en su
 * nuevo lugar con una transición (FLIP). El cuerpo se reordena con el
 * cambio de columnas que haga el padre.
 *
 * Docs: https://gsap.com/docs/v3/Plugins/Draggable/
 */
import { gsap } from 'gsap'
import { Draggable } from 'gsap/Draggable'
import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue'
import { dropIndex, shiftFor } from './layout'

gsap.registerPlugin(Draggable)

interface DragOptions {
  /** Fila de cabecera: contiene las celdas `[data-grid-col]` en orden. */
  header: Ref<HTMLElement | null>
  /** Claves de las columnas en orden (para recrear los draggables). */
  keys: () => string[]
  enabled: () => boolean
  onMove: (from: number, to: number) => void
}

export function useColumnDrag({ header, keys, enabled, onMove }: DragOptions) {
  let draggables: Draggable[] = []
  let cells: HTMLElement[] = []
  let centers: number[] = []
  let from = -1
  let to = -1
  let width = 0

  function kill() {
    draggables.forEach((d) => d.kill())
    draggables = []
  }

  function onPress(this: Draggable) {
    const target = this.target as HTMLElement
    cells = [...(header.value?.querySelectorAll<HTMLElement>('[data-grid-col]') ?? [])]
    from = cells.indexOf(target)
    to = from
    const rects = cells.map((cell) => cell.getBoundingClientRect())
    centers = rects.map((r) => r.left + r.width / 2)
    width = rects[from]?.width ?? 0
  }

  function onDragStart(this: Draggable) {
    const target = this.target as HTMLElement
    target.classList.add('is-dragging')
    header.value?.classList.add('is-reordering')
  }

  function onDrag(this: Draggable) {
    if (from < 0) return
    const next = dropIndex(centers, from, (centers[from] ?? 0) + this.x)
    if (next === to) return
    to = next
    cells.forEach((cell, index) => {
      if (index === from) return
      gsap.to(cell, { x: shiftFor(index, from, to, width), duration: 0.22, ease: 'power2.out', overwrite: 'auto' })
    })
  }

  async function onRelease(this: Draggable) {
    const target = this.target as HTMLElement
    header.value?.classList.remove('is-reordering')
    target.classList.remove('is-dragging')
    if (from < 0 || to === from) {
      from = -1
      gsap.to(cells, { x: 0, duration: 0.2, ease: 'power2.out', clearProps: 'transform' })
      return
    }
    const before = target.getBoundingClientRect().left
    const moved = { from, to }
    gsap.killTweensOf(cells)
    gsap.set(cells, { clearProps: 'transform' })
    from = -1
    if (moved.from !== moved.to) onMove(moved.from, moved.to)
    await nextTick()
    // La celda arrastrada puede ser otro nodo tras el re-render: se busca por clave.
    const key = target.dataset.gridCol
    const landed = header.value?.querySelector<HTMLElement>(`[data-grid-col="${key ?? ''}"]`) ?? target
    const after = landed.getBoundingClientRect().left
    gsap.fromTo(landed, { x: before - after }, { x: 0, duration: 0.25, ease: 'power2.out', clearProps: 'transform' })
  }

  async function sync() {
    kill()
    await nextTick()
    if (!enabled() || !header.value) return
    // Límite: la fila de cabecera entera. Con el contenedor con scroll como
    // límite, GSAP empujaba hacia adentro las cabeceras que arrancan fuera de
    // la parte visible y se desalineaban de su columna.
    const bounds = header.value
    for (const cell of header.value.querySelectorAll<HTMLElement>('[data-grid-col]')) {
      const grip = cell.querySelector<HTMLElement>('[data-grid-grip]')
      if (!grip) continue
      const [draggable] = Draggable.create(cell, {
        type: 'x',
        trigger: grip,
        bounds,
        autoScroll: 1,
        zIndexBoost: true,
        cursor: 'grab',
        activeCursor: 'grabbing',
        minimumMovement: 4,
        onPress,
        onDragStart,
        onDrag,
        onRelease,
      })
      if (draggable) draggables.push(draggable)
    }
  }

  watch(() => [keys().join('|'), enabled()], sync, { flush: 'post' })
  onBeforeUnmount(kill)

  return { sync }
}
