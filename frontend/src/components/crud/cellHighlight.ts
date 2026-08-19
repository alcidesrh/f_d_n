/**
 * Resaltado de coincidencias de filtro en celdas del listado (CSS Custom
 * Highlight API). Un único `Highlight` compartido bajo `list-filter-match`
 * agrega los rangos de todas las celdas; `::highlight(list-filter-match)`
 * los pinta de amarillo claro sin modificar el DOM.
 *
 * Cada celda registra sus rangos con una `key` única y los limpia en unmount
 * o al vaciarse el filtro (si un rango apunta a un nodo eliminado, el
 * renderer puede fallar al re-estilizar).
 */

export const HIGHLIGHT_NAME = 'list-filter-match'

const entries = new Map<string, Range[]>()
let highlight: Highlight | null = null

function highlightsApi(): HighlightRegistry | null {
  if (typeof CSS === 'undefined' || !('highlights' in CSS)) return null
  if (typeof Highlight === 'undefined') return null
  return CSS.highlights
}

function sync() {
  const api = highlightsApi()
  if (!api) return
  const ranges: Range[] = []
  for (const group of entries.values()) ranges.push(...group)
  if (ranges.length === 0) {
    api.delete(HIGHLIGHT_NAME)
    return
  }
  if (!highlight) highlight = new Highlight()
  highlight.clear()
  for (const range of ranges) highlight.add(range)
  api.set(HIGHLIGHT_NAME, highlight)
}

/** Registra (o reemplaza) los rangos de una celda identificada por `key`. */
export function registerCellHighlight(key: string, ranges: Range[]) {
  if (ranges.length === 0) {
    entries.delete(key)
  } else {
    entries.set(key, ranges)
  }
  sync()
}

/** Elimina los rangos de una celda (llamar en unmount o al limpiar el filtro). */
export function unregisterCellHighlight(key: string) {
  if (entries.delete(key)) sync()
}
