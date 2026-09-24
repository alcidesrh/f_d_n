/**
 * Filtros por columna del listado.
 *
 * - Cada columna filtrable tiene un input FormKit en su cabecera (`filterNodes`).
 *   Los nodos llevan siempre el valor vigente y solo se reconstruyen en puntos
 *   concretos (carga, ocultar/restaurar columna, reset): PrimeVue remonta la
 *   cabecera en cada fetch y reconstruir mientras se teclea robaba el foco.
 * - Texto y número esperan 500 ms sin teclear; el resto se aplica al instante.
 * - Los filtros con argumento en el backend van a `store.filters` (refetch);
 *   los que no, filtran en cliente la página cargada (`visibleItems`).
 * - El resaltado de coincidencias usa los filtros ya aplicados al store, no
 *   el tecleo en vivo, y se actualiza al renderizar el resultado del fetch.
 */
import { computed, reactive, ref, useId, watch, type ComputedRef } from 'vue'
import type { FormKitSchemaNode } from '@formkit/core'
import { getEntity } from '@/core/entities/registry'
import type { CollectionFieldConfig, EntityStore } from '@/core/entities/types'
import {
  fieldKind,
  fromServerFilters,
  isEmptyFilterValue,
  isLocalFilter,
  matchesFilters,
  resolveFilterArgs,
  toServerFilters,
  type FilterFieldKind,
} from './listUtils'

const DEBOUNCE_MS = 500
const BOOLEAN_OPTIONS = [
  { label: 'Sí', value: true },
  { label: 'No', value: false },
]

/** Input FormKit por tipo de columna. */
const FILTER_INPUTS: Record<FilterFieldKind, Record<string, unknown>> = {
  text: { type: 'InputText', clearable: true },
  number: { type: 'InputNumber', placeholder: 'Todos', showClear: true },
  boolean: { type: 'Select', placeholder: 'Todos', showClear: true, options: BOOLEAN_OPTIONS },
  relation: { type: 'Select', placeholder: 'Todos', showClear: true },
  date: {
    type: 'DatePicker',
    placeholder: 'Rango',
    selectionMode: 'range',
    showIcon: true,
    showClear: true,
  },
}

export function useListFilters(
  store: ComputedRef<EntityStore | null>,
  columns: ComputedRef<CollectionFieldConfig[]>,
) {
  /** Valor de cada input (campo → valor), antes de traducirse a args del backend. */
  const filters = reactive<Record<string, unknown>>({})
  const filterNodes = ref(new Map<string, FormKitSchemaNode>())
  const highlightFilters = ref<Record<string, unknown>>({})
  /** Cambia la `key` de los nodos para remontar los inputs (reset). */
  const generation = ref(0)
  const uid = useId()
  let timer: ReturnType<typeof setTimeout> | undefined

  const entity = () => store.value?.metadata ?? null
  const kindOf = (field: string): FilterFieldKind => {
    const metadata = entity()
    return metadata ? fieldKind(metadata, field) : 'text'
  }

  function relationOptions(field: string) {
    const target = entity()?.fields.find((f) => f.name === field)?.namedType
    if (!target) return []
    return getEntity(target).fullList.map((option) => ({
      label: option.label,
      value: option.value ?? option.id,
    }))
  }

  function buildNode(column: CollectionFieldConfig): FormKitSchemaNode | null {
    if (column.filterable === false || !entity()) return null
    const { field } = column
    const kind = kindOf(field)
    const name = `filter_${field}`
    return {
      key: `${name}_${uid}_${generation.value}`,
      $cmp: 'FormKit',
      props: {
        ...FILTER_INPUTS[kind],
        ...(kind === 'relation' ? { options: relationOptions(field) } : {}),
        name,
        value: filters[field],
        size: 'small',
        outerClass: 'mb-0! w-full',
        class: 'w-full',
        onInput: (value: unknown) => apply(field, kind, value),
      },
    } as FormKitSchemaNode
  }

  /** Reconstruye los inputs con los valores vigentes de `filters`. */
  function rebuild() {
    const next = new Map<string, FormKitSchemaNode>()
    for (const column of columns.value) {
      const node = buildNode(column)
      if (node) next.set(column.field, node)
    }
    filterNodes.value = next
  }

  function apply(field: string, kind: FilterFieldKind, value: unknown) {
    filters[field] = value
    clearTimeout(timer)
    if (!isEmptyFilterValue(value) && (kind === 'text' || kind === 'number'))
      timer = setTimeout(commit, DEBOUNCE_MS)
    else commit()
  }

  /** Traduce los filtros a args del backend, vuelve a la página 1 y refetcha. */
  function commit() {
    const current = store.value
    const metadata = entity()
    if (!current || !metadata) return
    current.filters = toServerFilters(metadata, filters)
    if (current.pagination) current.pagination.currentPage = 1
    void current.fetchItems()
  }

  function clear() {
    clearTimeout(timer)
    for (const key of Object.keys(filters)) delete filters[key]
    generation.value += 1
  }

  /** Muestra en los inputs los filtros persistidos en el store. */
  function hydrate() {
    const current = store.value
    const metadata = entity()
    clear()
    if (current && metadata) Object.assign(filters, fromServerFilters(metadata, current.filters))
  }

  const hasLocalFilter = computed(() => {
    const metadata = entity()
    return Object.entries(filters).some(
      ([field, value]) =>
        !isEmptyFilterValue(value) && (!metadata || isLocalFilter(metadata, field)),
    )
  })

  /** Items de la página, con los filtros locales aplicados. */
  const visibleItems = computed<unknown[]>(() => {
    const items = store.value?.items ?? []
    const metadata = entity()
    return hasLocalFilter.value && metadata
      ? items.filter((item) => matchesFilters(item, filters, metadata))
      : items
  })

  /** Texto a resaltar en una columna (solo texto/número). */
  function highlightFor(field: string): unknown {
    const metadata = entity()
    const kind = kindOf(field)
    if (!metadata || (kind !== 'text' && kind !== 'number')) return undefined
    return isLocalFilter(metadata, field) ? filters[field] : highlightFilters.value[field]
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
      highlightFilters.value = next
    },
    { flush: 'post' },
  )

  return {
    filters,
    filterNodes,
    hasLocalFilter,
    visibleItems,
    rebuild,
    clear,
    hydrate,
    highlightFor,
  }
}
