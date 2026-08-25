/**
 * `schemaBuilder` — modelo puro del Form Builder y compilador a FormKit Schema
 * (sintaxis corta `$formkit` / `$el`), JSON-plano y compatible con
 * `<FormKitSchema :schema="...">`.
 *
 * El borrador (`BuilderGrid`) es un árbol de grids anidados con celdas
 * (fila × columna) que contienen un campo o nada. El compilador produce:
 *  - Modo preview: cada celda envuelta en un `div.fb-cell` (bordes sutiles,
 *    selección y onClick para el panel) — nunca se exporta.
 *  - Modo export: solo los nodos reales; los huecos intermedios se preservan
 *    con spacers `<div>` invisibles para mantener la posición elegida.
 */

import type { FormKitSchemaNode } from '@formkit/core'

export type BuilderInputType =
  | 'InputText'
  | 'InputMask'
  | 'InputNumber'
  | 'Checkbox'
  | 'RadioButton'
  | 'Select'
  | 'MultiSelect'
  | 'SelectButton'
  | 'CascadeSelect'
  | 'AutoComplete'
  | 'Password'
  | 'DatePicker'
  | 'TextArea'
  | 'TreeSelect'
  | 'ToggleSwitch'
  | 'Button'

export const BUILDER_INPUT_TYPES: BuilderInputType[] = [
  'InputText',
  'TextArea',
  'InputNumber',
  'InputMask',
  'Password',
  'DatePicker',
  'Select',
  'MultiSelect',
  'SelectButton',
  'RadioButton',
  'Checkbox',
  'ToggleSwitch',
  'AutoComplete',
  'CascadeSelect',
  'TreeSelect',
  'Button',
]

/** Tipos que consumen una lista de options (`label | value` por línea). */
export const OPTION_INPUT_TYPES: BuilderInputType[] = [
  'Select',
  'MultiSelect',
  'SelectButton',
  'RadioButton',
  'Checkbox',
  'AutoComplete',
  'CascadeSelect',
  'TreeSelect',
]

export interface BuilderField {
  id: string
  kind: 'field'
  inputType: BuilderInputType
  props: Record<string, unknown>
}

export type GridCols = 1 | 2 | 3 | 4 | 5 | 6

export interface BuilderGrid {
  id: string
  kind: 'grid'
  cols: GridCols
  rows: number
  /** Celdas en orden fila-major; longitud = rows * cols. */
  cells: BuilderCell[]
}

export type BuilderCell = null | BuilderField | BuilderGrid

export type BuilderNode = BuilderField | BuilderGrid

export interface CellKey {
  gridId: string
  index: number
}

// --- IDs -----------------------------------------------------------------

let seq = 0

export function newId(prefix = 'n'): string {
  seq += 1
  return `${prefix}_${Date.now().toString(36)}${seq.toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`
}

// --- Factories -----------------------------------------------------------

export const MIN_COLS = 1
export const MAX_COLS = 6
export const MIN_ROWS = 1
export const MAX_ROWS = 12

export function clampCols(value: number): GridCols {
  return Math.min(MAX_COLS, Math.max(MIN_COLS, Math.round(value))) as GridCols
}

export function clampRows(value: number): number {
  return Math.min(MAX_ROWS, Math.max(MIN_ROWS, Math.round(value)))
}

export function createGrid(cols: number, rows: number): BuilderGrid {
  const c = clampCols(cols)
  const r = clampRows(rows)
  return { id: newId('grid'), kind: 'grid', cols: c, rows: r, cells: Array.from({ length: c * r }, () => null) }
}

export function createField(inputType: BuilderInputType, props: Record<string, unknown> = {}): BuilderField {
  return { id: newId('field'), kind: 'field', inputType, props }
}

/**
 * Redimensiona un grid preservando el contenido dentro de la intersección
 * fila-major vieja/nueva.
 */
export function resizeGrid(grid: BuilderGrid, nextCols: number, nextRows: number): BuilderGrid {
  const cols = clampCols(nextCols)
  const rows = clampRows(nextRows)
  const cells: BuilderCell[] = Array.from({ length: cols * rows }, () => null)
  for (let row = 0; row < Math.min(rows, grid.rows); row += 1) {
    for (let col = 0; col < Math.min(cols, grid.cols); col += 1) {
      cells[row * cols + col] = grid.cells[row * grid.cols + col] ?? null
    }
  }
  return { ...grid, cols, rows, cells }
}

// --- Clases de grid (safelist Tailwind: literales completos) ---------------

export const GRID_COL_MD_CLASSES = [
  'md:grid-cols-1',
  'md:grid-cols-2',
  'md:grid-cols-3',
  'md:grid-cols-4',
  'md:grid-cols-5',
  'md:grid-cols-6',
] as const

/** Mobile-first: 1 columna en móvil, `cols` desde `md`. */
export function gridClass(cols: GridCols): string {
  return `grid grid-cols-1 ${GRID_COL_MD_CLASSES[cols - 1]} gap-x-6 gap-y-4`
}

// --- Compilador ------------------------------------------------------------

export interface CompileOptions {
  preview?: boolean
  selectedKey?: CellKey | null
  onSelectCell?: (key: CellKey) => void
}

function sameKey(a: CellKey | null | undefined, b: CellKey): boolean {
  return a != null && a.gridId === b.gridId && a.index === b.index
}

function compileField(field: BuilderField): FormKitSchemaNode {
  return { key: field.id, $formkit: field.inputType, fluid: true, ...structuredCloneSafe(field.props) } as FormKitSchemaNode
}

/** Clone JSON-safe (el árbol es serializable; evita fugas de reactividad). */
export function structuredCloneSafe<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

function compileGrid(grid: BuilderGrid, opts: CompileOptions): FormKitSchemaNode {
  const children: FormKitSchemaNode[] = []
  const preview = opts.preview === true

  if (preview) {
    grid.cells.forEach((cell, index) => {
      const key: CellKey = { gridId: grid.id, index }
      const selected = sameKey(opts.selectedKey, key)
      children.push({
        $el: 'div',
        attrs: {
          class: selected ? 'fb-cell fb-cell--selected' : cell ? 'fb-cell' : 'fb-cell fb-cell--empty',
          'data-cell': `${grid.id}:${index}`,
          onClick: () => opts.onSelectCell?.(key),
        },
        children: cell ? [compileCell(cell, opts)] : [],
      } as unknown as FormKitSchemaNode)
    })
    return { $el: 'div', attrs: { class: gridClass(grid.cols) }, children } as FormKitSchemaNode
  }

  let lastFilled = -1
  grid.cells.forEach((cell, index) => {
    if (cell !== null) lastFilled = index
  })
  grid.cells.forEach((cell, index) => {
    if (index > lastFilled) return
    children.push(cell ? compileCell(cell, opts) : ({ $el: 'div' } as FormKitSchemaNode))
  })
  return { $el: 'div', attrs: { class: gridClass(grid.cols) }, children } as FormKitSchemaNode
}

function compileCell(cell: BuilderCell, opts: CompileOptions): FormKitSchemaNode {
  if (cell === null) return { $el: 'div' } as FormKitSchemaNode
  if (cell.kind === 'grid') return compileGrid(cell, opts)
  return compileField(cell)
}

/**
 * Compila el borrador a FormKit Schema. Con `preview` agrega los wrappers
 * `.fb-cell` (bordes/selección/onClick); sin él produce el JSON final limpio.
 */
export function compileSchema(root: BuilderGrid, opts: CompileOptions = {}): FormKitSchemaNode[] {
  return [compileGrid(structuredCloneSafe(root), opts)]
}

// --- Options desde texto ----------------------------------------------------

/** `"Alta | A"\n"Baja"` → `[{label:'Alta',value:'A'},{label:'Baja',value:'Baja'}]`. */
export function parseOptionsText(text: string): Array<{ label: string; value: string }> {
  return text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0)
    .map((line) => {
      const idx = line.indexOf('|')
      if (idx === -1) return { label: line, value: line }
      const label = line.slice(0, idx).trim()
      const value = line.slice(idx + 1).trim() || label
      return { label, value }
    })
}

// --- Import / export del borrador -------------------------------------------

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function reviveField(raw: unknown, path: string): BuilderField {
  if (!isRecord(raw)) throw new Error(`Borrador inválido: campo mal formado en ${path}`)
  if (raw.kind !== 'field') throw new Error(`Borrador inválido: se esperaba kind "field" en ${path}`)
  const inputType = raw.inputType
  if (typeof inputType !== 'string' || !BUILDER_INPUT_TYPES.includes(inputType as BuilderInputType)) {
    throw new Error(`Borrador inválido: inputType desconocido "${String(inputType)}" en ${path}`)
  }
  if (!isRecord(raw.props)) throw new Error(`Borrador inválido: props no es objeto en ${path}`)
  return {
    id: typeof raw.id === 'string' ? raw.id : newId('field'),
    kind: 'field',
    inputType: inputType as BuilderInputType,
    props: structuredCloneSafe(raw.props),
  }
}

function reviveCell(raw: unknown, path: string): BuilderCell {
  if (raw === null || raw === undefined) return null
  if (!isRecord(raw)) throw new Error(`Borrador inválido: celda mal formada en ${path}`)
  if (raw.kind === 'grid') return reviveGrid(raw, path)
  return reviveField(raw, path)
}

function reviveGrid(raw: unknown, path = 'root'): BuilderGrid {
  if (!isRecord(raw)) throw new Error('Borrador inválido: la raíz debe ser un grid')
  if (raw.kind !== 'grid') throw new Error('Borrador inválido: la raíz debe tener kind "grid"')
  const cols = Number(raw.cols)
  const rows = Number(raw.rows)
  if (!Number.isInteger(cols) || cols < MIN_COLS || cols > MAX_COLS) {
    throw new Error(`Borrador inválido: cols fuera de rango (${MIN_COLS}-${MAX_COLS})`)
  }
  if (!Number.isInteger(rows) || rows < MIN_ROWS || rows > MAX_ROWS) {
    throw new Error(`Borrador inválido: rows fuera de rango (${MIN_ROWS}-${MAX_ROWS})`)
  }
  if (!Array.isArray(raw.cells) || raw.cells.length !== cols * rows) {
    throw new Error('Borrador inválido: cells no coincide con rows*cols')
  }
  const cells = raw.cells.map((cell, index) => reviveCell(cell, `${path}.cells[${index}]`))
  return {
    id: typeof raw.id === 'string' ? raw.id : newId('grid'),
    kind: 'grid',
    cols: cols as GridCols,
    rows,
    cells,
  }
}

/** Parsea y valida un borrador serializado; lanza `Error` descriptivo. */
export function importBuilderJson(raw: string): BuilderGrid {
  let data: unknown
  try {
    data = JSON.parse(raw)
  } catch {
    throw new Error('El texto no es JSON válido')
  }
  if (isRecord(data) && data.root !== undefined) data = data.root
  return reviveGrid(data)
}

/** Serializa el borrador (formato con versión, útil para compartir). */
export function exportDraftJson(root: BuilderGrid): string {
  return JSON.stringify({ version: 1, root: structuredCloneSafe(root) }, null, 2)
}
