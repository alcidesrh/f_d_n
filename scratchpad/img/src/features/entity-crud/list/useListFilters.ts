/**
 * Filtros por columna del listado.
 *
 * - Solo columnas con `filterable: true` (se habilita en la configuración).
 * - Siempre se filtra en la base de datos: los valores se traducen a los
 *   argumentos de la colección (`toServerFilters`) y van a `store.filters`
 *   (refetch, vuelta a la página 1). Una columna sin argumento de filtro (p.
 *   ej. un campo calculado) no ofrece filtro.
 * - Texto y número esperan 400 ms sin teclear; un rango de fechas se aplica
 *   con su botón Buscar; lo demás, al instante.
 * - Varias columnas filtradas se combinan según `store.view.filterMode`:
 *   `or` (basta con una, por defecto) o `and` (todas).
 * - Relaciones: a uno, un valor (select); a muchos, varios (multiselect,
 *   cualquiera de ellos). Las columnas con `autocomplete` (entidades con
 *   muchos registros) no cargan la lista completa: buscan en el servidor al
 *   teclear (`search`) y solo piden la etiqueta de lo ya elegido.
 * - El resaltado de coincidencias usa los filtros ya aplicados al store, no
 *   el tecleo en vivo, y cambia cuando llega el resultado.
 * - Son excluyentes con el filtro a la medida (`store.customFilter`):
 *   aplicar uno quita el otro.
 */
import { computed, reactive, ref, watch, type ComputedRef } from 'vue'
import { esBusquedaRemota } from '@/core/entities/busquedaRemota'
import { getEntity } from '@/core/entities/registry'
import { graphql } from '@/core/graphql/client'
import type { CollectionFieldConfig, EntityStore, FilterMode } from '@/core/entities/types'
import {
  fieldKind,
  fromServerFilters,
  hasServerFilter,
  isEmptyFilterValue,
  isToMany,
  dateRangeLabel,
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

export function useListFilters(store: ComputedRef<EntityStore | null>) {
  /** Valor de cada input (campo → valor), antes de traducirse a args del backend. */
  const values = reactive<Record<string, unknown>>({})
  const highlights = ref<Record<string, unknown>>({})
  /** Options conocidas de cada columna con autocompletado: lo elegido y lo último encontrado. */
  const remote = reactive<Record<string, FilterOption[]>>({})
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
    return (
      column.filterable === true && Boolean(metadata && hasServerFilter(metadata, column.field))
    )
  }

  function relationTarget(field: string) {
    return entity()?.fields.find((f) => f.name === field)?.namedType ?? null
  }

  /**
   * Relación que se busca en el servidor: marcada `autocomplete` en la
   * configuración, o de una entidad con muchos registros (usuarios, trayectos,
   * rutas, enclaves), que nunca se carga entera.
   */
  function isRemote(field: string) {
    if (kindOf(field) !== 'relation') return false
    return (
      store.value?.columns.find((c) => c.field === field)?.autocomplete === true ||
      esBusquedaRemota(relationTarget(field))
    )
  }

  function optionsFor(field: string): FilterOption[] {
    const kind = kindOf(field)
    if (kind === 'boolean') return BOOLEAN_OPTIONS
    if (kind !== 'relation') return []
    if (isRemote(field)) return remote[field] ?? []
    const target = relationTarget(field)
    if (!target) return []
    return getEntity(target).fullList.map((option) => ({
      label: option.label,
      value: option.value ?? option.id,
    }))
  }

  function remember(field: string, found: FilterOption[]) {
    const known = new Map((remote[field] ?? []).map((option) => [option.value, option]))
    for (const option of found) known.set(option.value, option)
    remote[field] = [...known.values()]
  }

  async function fetchRemote(field: string, args: { search?: string; ids?: string[] }) {
    const target = relationTarget(field)
    if (!target) return []
    const found = (await graphql.agnosticSearch(target, args)).map((option) => ({
      label: option.label,
      value: option.value ?? option.id,
    }))
    remember(field, found)
    return found
  }

  /** Autocompletado de una columna `autocomplete`: los primeros registros que coinciden con `query`. */
  function search(field: string, query: string): Promise<FilterOption[]> {
    return fetchRemote(field, { search: query })
  }

  /**
   * Carga las opciones de los filtros de relación de estas columnas: la lista
   * completa, o en las de autocompletado solo la etiqueta de lo ya elegido.
   */
  function preloadOptions(columns: CollectionFieldConfig[]) {
    const relations = columns.filter(
      (column) => isFilterable(column) && kindOf(column.field) === 'relation',
    )
    const full = relations
      .filter((column) => !isRemote(column.field))
      .map((column) => relationTarget(column.field))
      .filter((target): target is string => Boolean(target))
      .map((target) => getEntity(target).loadFullList())
    const chosen = relations
      .filter((column) => isRemote(column.field))
      .map((column) => {
        const value = values[column.field]
        const ids = (Array.isArray(value) ? value : [value]).filter(
          (v): v is string => typeof v === 'string' && v !== '',
        )
        const unknown = ids.filter(
          (id) => !remote[column.field]?.some((option) => option.value === id),
        )
        return unknown.length ? fetchRemote(column.field, { ids: unknown }) : null
      })
    return Promise.all([...full, ...chosen])
  }

  function set(field: string, value: unknown) {
    values[field] = value
    clearTimeout(timer)
    const kind = kindOf(field)
    if (!isEmptyFilterValue(value) && (kind === 'text' || kind === 'number'))
      timer = setTimeout(commit, DEBOUNCE_MS)
    else commit()
  }

  /** Traduce los filtros a args del backend, vuelve a la página 1 y refetcha. */
  function commit() {
    clearTimeout(timer)
    const current = store.value
    const metadata = entity()
    if (!current || !metadata) return
    current.filters = toServerFilters(metadata, values, current.view.filterMode)
    current.customFilter = null
    if (current.pagination) current.pagination.currentPage = 1
    void current.fetchItems()
  }

  /** Quita el filtro de una columna (o todos); refetcha solo si había uno aplicado. */
  function clear(field?: string) {
    clearTimeout(timer)
    const applied = field ? active.value.includes(field) : active.value.length > 0
    if (field) delete values[field]
    else for (const key of Object.keys(values)) delete values[key]
    if (applied) commit()
  }

  /** Quita todos los filtros sin volver a leer (lo hace quien aplica otro filtro). */
  function reset() {
    clearTimeout(timer)
    for (const key of Object.keys(values)) delete values[key]
    if (store.value) store.value.filters = {}
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
    for (const key of Object.keys(remote)) delete remote[key]
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
    if (kind === 'date') return dateRangeLabel(value)
    if (kind === 'boolean') return value === true || value === 'true' ? 'Sí' : 'No'
    if (kind === 'relation') {
      const options = optionsFor(field)
      const chosen = (Array.isArray(value) ? value : [value]).map(
        (v) => options.find((o) => o.value === v)?.label ?? String(v),
      )
      return chosen.length > 2
        ? `${chosen.slice(0, 2).join(', ')} +${chosen.length - 2}`
        : chosen.join(', ')
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
    isRemote,
    optionsFor,
    search,
    preloadOptions,
    set,
    commit,
    clear,
    reset,
    setMode,
    hydrate,
    describe,
    highlightFor,
  }
}
