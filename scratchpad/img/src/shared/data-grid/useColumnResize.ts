/**
 * Redimensionar una columna arrastrando el borde derecho de su cabecera.
 * Mientras se arrastra el ancho vive en `live` (la plantilla del grid lo
 * usa); al soltar se emite el ancho final en px. Doble clic en el borde:
 * vuelve al ancho configurado (`null`).
 */
import { reactive } from 'vue'
import { RESIZE_MIN_PX } from './layout'

export function useColumnResize(onCommit: (key: string, width: string | null) => void) {
  const live = reactive<Record<string, number>>({})

  function start(event: PointerEvent, key: string) {
    const handle = event.currentTarget as HTMLElement
    const cell = handle.closest<HTMLElement>('[data-grid-col]')
    if (!cell || event.button !== 0) return
    event.preventDefault()
    event.stopPropagation()
    const startX = event.clientX
    const startWidth = cell.getBoundingClientRect().width
    handle.setPointerCapture(event.pointerId)
    document.body.classList.add('grid-resizing')

    const move = (e: PointerEvent) => {
      live[key] = Math.max(RESIZE_MIN_PX, startWidth + e.clientX - startX)
    }
    const end = () => {
      handle.removeEventListener('pointermove', move)
      handle.removeEventListener('pointerup', end)
      handle.removeEventListener('pointercancel', end)
      document.body.classList.remove('grid-resizing')
      const width = live[key]
      delete live[key]
      if (width !== undefined && Math.abs(width - startWidth) >= 2) onCommit(key, `${Math.round(width)}px`)
    }
    handle.addEventListener('pointermove', move)
    handle.addEventListener('pointerup', end)
    handle.addEventListener('pointercancel', end)
  }

  function reset(key: string) {
    onCommit(key, null)
  }

  return { live, start, reset }
}
