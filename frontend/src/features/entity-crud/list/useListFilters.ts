/**
 * Filtros por columna del listado.
 *
 * - Siempre se filtra en la base de datos: los valores se traducen a los
 *   argumentos de la colección (`toServerFilters`) y van a `store.filters`
 *   (refetch, vuelta a la página 1). Una columna sin argumento de filtro (p.
 *   ej. un campo calculado) no ofrece filtro.
 * - Texto y número esperan 400 ms sin teclear; un rango de fechas, a que
 *   esté completo; lo demás se aplica al instante.
 * - Varias columnas filtradas se combinan según `store.view.filterMode`:
 *   `or` (basta con una, por defecto) o `and` (todas).
 * - Relaciones: a uno, un valor (select); a muchos, varios (multiselect,
 *   cualquiera de ellos).
 * - El resaltado de coincidencias usa los filtros ya aplicados al store, no
 *   el tecleo en vivo, y cambia cuando llega el resultado.
 */
import { computed, reactive, ref, watch, type ComputedRef } from 'vue'
import { getEntity } from '@/core/entities/registry'
import type { CollectionFieldConfig, EntityStore, FilterMode } from '@/core/entities/types'
import {
  fieldKind,
  fromServerFilters,
  hasServerFilter,
  isEmptyFilterValue,
  isToMany,
  rangeToIso,
  resolveFilterArgs,
  toServerFilters,
  type FilterFieldKind,
} from './listUtils'

const DEBOUNCE_MS = 400

export const BOOLEAN_OPTIONS = [
  { label: 'Sí', value: true },
  { label: 'No', value: false },
]

export interface FilterOption {
  label: string
  value: unknown
}

/** `dd/mm/yyyy` de un `yyyy-mm-dd`. */
const dayLabel = (iso?: string) => (iso ? iso.split('-').reverse().join('/') : '…')

/** Rango a medio elegir en el DatePicker (`[inicio, null]`): todavía no filtra. */
const isPartialRange = (value: unknown) => Array.isArray(value) && value.length === 2 && value[0] && !value[1]

export function useListFilters(store: ComputedRef<EntityStore | null>) {
  /** Valor de cada input (campo → valor), antes de traducirse a args del backend. */
  const values = reactive<Record<string, unknown>>({})
  const highlights = ref<Record<string, unknown>>({})
  let timer: ReturnType<typeof setTimeout> | undefined

  const entity = () => store.value?.metadata ?? null
  const kindOf = (field: string): FilterFieldKind => {
    const metadata = entity()
    return metadata ? fieldKind(metadata, field) : 'text'
  }
  const isMulti = (field: string) => {
    const metadata = entity()
    return Boolean(metadata && isToMany(metadata, field))
  }

  function isFilterable(column: CollectionFieldConfig) {
    const metadata = entity()
    return column.filterable !== false && Boolean(metadata && hasServerFilter(metadata, column.field))
  }

  function relationTarget(field: string) {
    return entity()?.fields.find((f) => f.name === field)?.namedType ?? null
  }

  function optionsFor(field: string): FilterOption[] {
    const kind = kindOf(field)
    if (kind === 'boolean') return BOOLEAN_OPTIONS
    if (kind !== 'relation') return []
    const target = relationTarget(field)
    if (!target) return []
    return getEntity(target).fullList.map((option) => ({ label: option.label, value: option.value ?? option.id }))
  }

  /** Carga las opciones de los filtros de relación de estas columnas. */
  function preloadOptions(columns: CollectionFieldConfig[]) {
    return Promise.all(
      columns
        .filter((column) => isFilterable(column) && kindOf(column.field) === 'relation')
        .map((column) => relationTarget(column.field))
        .filter((target): target is string => Boolean(target))
        .map((target) => getEntity(target).loadFullList()),
    )
  }

  function set(field: string, value: unknown) {
    values[field] = value
    clearTimeout(timer)
    const kind = kindOf(field)
    if (kind === 'date' && isPartialRange(value)) return
    if (!isEmptyFilterValue(value) && (kind === 'text' || kind === 'number')) timer = setTimeout(commit, DEBOUNCE_MS)
    else commit()
  }

  /** Traduce los filtros a args del backend, vuelve a la página 1 y refetcha. */
  function commit() {
    clearTimeout(timer)
    const current = store.value
    const metadata = entity()
    if (!current || !metadata) return
    current.filters = toServerFilters(metadata, values, current.view.filterMode)
    if (current.pagination) current.pagination.currentPage = 1
    void current.fetchItems()
  }

  /** Quita el filtro de una columna (o todos) y refetcha. */
  function clear(field?: string) {
    clearTimeout(timer)
    if (field) delete values[field]
    else for (const key of Object.keys(values)) delete values[key]
    commit()
  }

  function setMode(mode: FilterMode) {
    const current = store.value
    if (!current || current.view.filterMode === mode) return
    current.view.filterMode = mode
    if (active.value.length > 1) commit()
  }

  /** Muestra en los inputs los filtros persistidos en el store. */
  function hydrate() {
    clearTimeout(timer)
    for (const key of Object.keys(values)) delete values[key]
    const current = store.value
    const metadata = entity()
    if (current && metadata) Object.assign(values, fromServerFilters(metadata, current.filters))
  }

  /** Campos con un filtro aplicado (en el store, no lo que se está tecleando). */
  const active = computed(() => {
    const current = store.value
    const metadata = entity()
    if (!current || !metadata) return []
    return Object.entries(fromServerFilters(metadata, current.filters))
      .filter(([, value]) => !isEmptyFilterValue(value))
      .map(([field]) => field)
  })

  /** Texto corto del filtro aplicado de una columna (para los chips). */
  function describe(field: string): string {
    const current = store.value
    const metadata = entity()
    if (!current || !metadata) return ''
    const value = fromServerFilters(metadata, current.filters)[field]
    const kind = kindOf(field)
    if (kind === 'date') {
      const { after, before } = rangeToIso(value)
      return `${dayLabel(after)} – ${dayLabel(before)}`
    }
    if (kind === 'boolean') return value === true || value === 'true' ? 'Sí' : 'No'
    if (kind === 'relation') {
      const options = optionsFor(field)
      const chosen = (Array.isArray(value) ? value : [value]).map((v) => options.find((o) => o.value === v)?.label ?? String(v))
      return chosen.length > 2 ? `${chosen.slice(0, 2).join(', ')} +${chosen.length - 2}` : chosen.join(', ')
    }
    return `“${String(value)}”`
  }

  /** Texto a resaltar en una columna (solo texto/número). */
  function highlightFor(field: string): unknown {
    const kind = kindOf(field)
    if (kind !== 'text' && kind !== 'number') return undefined
    return highlights.value[field]
  }

  watch(
    () => store.value?.items,
    () => {
      const current = store.value
      const metadata = entity()
      if (!current || !metadata) return
      const next: Record<string, unknown> = {}
      for (const { name: field } of metadata.fields) {
        const kind = kindOf(field)
        if (kind !== 'text' && kind !== 'number') continue
        const arg = resolveFilterArgs(metadata, field).single
        if (arg && current.filters[arg] !== undefined) next[field] = current.filters[arg]
      }
      highlights.value = next
    },
    { flush: 'post' },
  )

  return {
    values,
    active,
    kindOf,
    isMulti,
    isFilterable,
    optionsFor,
    preloadOptions,
    set,
    commit,
    clear,
    setMode,
    hydrate,
    describe,
    highlightFor,
  }
}
