/**
 * Contrato del `DataGrid`: columnas ya resueltas (qué se puede hacer con
 * cada una lo decide quien lo usa) y estado visual. El grid no sabe de
 * entidades, filtros ni APIs: pinta, avisa y deja los slots.
 */

export type GridDensity = 'compact' | 'normal' | 'comfortable'

/** `auto`: tabla o tarjetas según el ancho del contenedor. */
export type GridLayout = 'auto' | 'table' | 'cards'

export type SortState = 'asc' | 'desc' | null

export interface GridColumn {
  key: string
  label: string
  /** Preset (`xs`…`xl`), longitud (`12rem`, `160px`) o fracción (`2fr`). Vacío: flexible. */
  width?: string | null
  sortable?: boolean
  sort?: SortState
  /** Ofrece el botón de filtro en la cabecera. */
  filterable?: boolean
  /** Hay un filtro aplicado en esta columna. */
  filtered?: boolean
  /** La celda se edita en línea (lo decide el padre al recibir `cell-click`). */
  editable?: boolean
  align?: 'start' | 'center' | 'end'
}

/** Celda en edición: fila (por su clave) y columna. */
export interface GridEditing {
  row: string
  column: string
}
