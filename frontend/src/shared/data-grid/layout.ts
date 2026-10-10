/**
 * Geometría pura del `DataGrid`: de los anchos de las columnas a la plantilla
 * de CSS Grid que comparten todas las filas.
 *
 * Todas las filas usan la misma `grid-template-columns` y el mismo ancho (el
 * del cuerpo), así que las columnas quedan alineadas sin depender del
 * contenido de ninguna celda. El ancho mínimo del cuerpo es la suma de los
 * mínimos de cada pista: si el contenedor es más angosto, aparece el scroll
 * horizontal; si es más ancho, las pistas flexibles (`fr`) se reparten el
 * sobrante.
 */
import type { GridColumn, GridDensity } from './types'

/** Ancho de los presets (`width: 'md'`). */
export const WIDTH_PRESETS = {
  xs: '6rem',
  sm: '9rem',
  md: '13rem',
  lg: '18rem',
  xl: '26rem',
} as const

/** Mínimo de una columna flexible (sin ancho o con `fr`). */
export const FLEX_MIN = '10rem'

/** Ancho mínimo al redimensionar con el puntero (px). */
export const RESIZE_MIN_PX = 64

/** Alto de fila por densidad. */
export const ROW_HEIGHT: Record<GridDensity, string> = {
  compact: '2.25rem',
  normal: '2.875rem',
  comfortable: '3.5rem',
}

const LENGTH = /^\d+(\.\d+)?(px|rem|em|ch|%)$/
const FRACTION = /^(\d+(\.\d+)?)fr$/

export interface Track {
  /** Pista de `grid-template-columns`. */
  track: string
  /** Lo mínimo que ocupa (para el ancho mínimo del cuerpo). */
  min: string
  flexible: boolean
}

/**
 * Pista de una columna según su `width`: preset (`md`), longitud (`12rem`,
 * `160px`), fracción (`2fr`, flexible con mínimo `FLEX_MIN`) o nada
 * (flexible `1fr`). Lo que no se entiende se trata como nada.
 */
export function trackFor(width: string | null | undefined): Track {
  const value = width?.trim() ?? ''
  if (value in WIDTH_PRESETS) {
    const length = WIDTH_PRESETS[value as keyof typeof WIDTH_PRESETS]
    return { track: length, min: length, flexible: false }
  }
  if (LENGTH.test(value) && !value.endsWith('%')) return { track: value, min: value, flexible: false }
  const fraction = FRACTION.exec(value)
  const fr = fraction ? Number(fraction[1]) || 1 : 1
  return { track: `minmax(${FLEX_MIN}, ${fr}fr)`, min: FLEX_MIN, flexible: true }
}

/** ¿`width` es un valor que el grid entiende? (vacío también vale: automático). */
export function isValidWidth(width: string | null | undefined): boolean {
  const value = width?.trim() ?? ''
  return value === '' || value in WIDTH_PRESETS || (LENGTH.test(value) && !value.endsWith('%')) || FRACTION.test(value)
}

export interface GridTemplateOptions {
  /** Ancho de la columna de selección (al principio), si se muestra. */
  selection?: string | null
  /** Ancho de la columna de acciones (al final), si se muestra. */
  actions?: string | null
  /** Anchos en vivo mientras se redimensiona (key → px), ganan a `width`. */
  live?: Record<string, number>
}

export interface GridTemplate {
  columns: string
  minWidth: string
  /** Se agregó la pista de relleno: cada fila necesita una celda vacía ahí. */
  filler: boolean
}

/**
 * Plantilla y ancho mínimo del cuerpo. Si ninguna columna es flexible se
 * agrega un relleno antes de las acciones, para que queden pegadas a la
 * derecha en un contenedor ancho.
 */
export function gridTemplate(columns: readonly Pick<GridColumn, 'key' | 'width'>[], options: GridTemplateOptions = {}): GridTemplate {
  const tracks = columns.map((column) => {
    const live = options.live?.[column.key]
    return live ? trackFor(`${Math.round(live)}px`) : trackFor(column.width)
  })
  const parts = tracks.map((t) => t.track)
  const mins = tracks.map((t) => t.min)
  if (options.selection) {
    parts.unshift(options.selection)
    mins.unshift(options.selection)
  }
  const filler = !tracks.some((t) => t.flexible)
  if (filler) parts.push('minmax(0, 1fr)')
  if (options.actions) {
    parts.push(options.actions)
    mins.push(options.actions)
  }
  return {
    columns: parts.join(' '),
    minWidth: mins.length ? `calc(${mins.join(' + ')})` : '0px',
    filler,
  }
}

/** Copia de `list` con el elemento de `from` movido a `to`. */
export function moveItem<T>(list: readonly T[], from: number, to: number): T[] {
  const next = [...list]
  if (from < 0 || from >= next.length || to < 0 || to >= next.length || from === to) return next
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item as T)
  return next
}

/**
 * Índice destino de una columna arrastrada: cuántas de las otras columnas
 * tienen su centro antes del centro de la arrastrada. `centers` son los
 * centros originales (en orden), `from` la arrastrada y `center` su centro
 * actual.
 */
export function dropIndex(centers: readonly number[], from: number, center: number): number {
  let index = 0
  centers.forEach((c, i) => {
    if (i !== from && c < center) index += 1
  })
  return index
}

/**
 * Desplazamiento de cada columna mientras otra se arrastra de `from` a `to`:
 * las que quedan en medio se corren el ancho de la arrastrada.
 */
export function shiftFor(index: number, from: number, to: number, draggedWidth: number): number {
  if (index === from) return 0
  if (from < to && index > from && index <= to) return -draggedWidth
  if (from > to && index >= to && index < from) return draggedWidth
  return 0
}
