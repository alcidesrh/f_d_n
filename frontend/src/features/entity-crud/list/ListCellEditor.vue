<template>
  <div ref="root" class="cell-editor" @keydown.enter.prevent.stop="emit('commit')" @keydown.esc.prevent.stop="emit('cancel')">
    <Select
      v-if="kind === 'relation'"
      ref="input"
      :model-value="relationId"
      :options="options"
      option-label="label"
      option-value="value"
      :filter="options.length > 8"
      show-clear
      placeholder="Sin valor"
      size="small"
      fluid
      @update:model-value="setRelation"
    />
    <Select
      v-else-if="kind === 'boolean'"
      ref="input"
      :model-value="data[column.field]"
      :options="BOOLEAN_OPTIONS"
      option-label="label"
      option-value="value"
      show-clear
      placeholder="Sin valor"
      size="small"
      fluid
      @update:model-value="(value: unknown) => set(value, true)"
    />
    <DatePicker
      v-else-if="kind === 'date'"
      ref="input"
      :model-value="dateValue"
      date-format="dd/mm/yy"
      size="small"
      fluid
      @update:model-value="(value: unknown) => set(value, true)"
    />
    <InputNumber v-else-if="kind === 'number'" ref="input" :model-value="current" size="small" fluid @update:model-value="(value: unknown) => set(value)" />
    <InputText v-else ref="input" :model-value="current" size="small" fluid @update:model-value="(value: unknown) => set(value)" />
  </div>
</template>

<script setup lang="ts">
/**
 * Editor de una celda del listado. Escribe en `data` (una copia de la fila
 * que es del padre) y avisa:
 * - `commit`: Enter, elegir una opción (select, sí/no, fecha) o tocar fuera;
 * - `cancel`: Escape.
 * Los overlays de PrimeVue (listas, calendario) viven fuera del editor: tocar
 * en ellos no cuenta como "fuera".
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { AgnosticOption } from '@/core/graphql/types'
import { getEntity } from '@/core/entities/registry'
import type { CollectionFieldConfig } from '@/core/entities/types'
import { fieldKind } from './listUtils'
import { BOOLEAN_OPTIONS } from './useListFilters'

const OVERLAYS = '.p-select-overlay, .p-datepicker-panel, .p-multiselect-overlay, .p-popover, .p-overlay-mask'

const props = defineProps<{
  entity: string
  column: CollectionFieldConfig
  data: Record<string, unknown>
}>()

const emit = defineEmits<{ commit: []; cancel: [] }>()

const root = ref<HTMLElement | null>(null)
const input = ref<{ $el?: HTMLElement; show?: () => void } | null>(null)
const metadata = getEntity(props.entity).metadata
const kind = fieldKind(metadata, props.column.field)
const options = ref<Array<{ label: string; value: string }>>([])

if (kind === 'relation') {
  const target = metadata.fields.find((field) => field.name === props.column.field)?.namedType
  if (target) {
    void getEntity(target)
      .loadFullList()
      .then((list: AgnosticOption[]) => {
        options.value = list.map((option) => ({ label: option.label, value: option.value ?? option.id ?? '' }))
      })
  }
}

/** Valor actual de la celda; el tipo depende del editor (`kind`), de ahí `never`. */
const current = computed(() => props.data[props.column.field] as never)

const dateValue = computed(() => {
  const value = props.data[props.column.field]
  if (value instanceof Date || value == null) return (value ?? null) as Date | null
  const date = new Date(String(value))
  return Number.isNaN(date.getTime()) ? null : date
})

const relationId = computed(() => {
  const value = props.data[props.column.field]
  return value && typeof value === 'object' ? ((value as { id?: unknown }).id ?? null) : null
})

function set(value: unknown, commit = false) {
  // eslint-disable-next-line vue/no-mutating-props -- `data` es la copia de la fila en edición, del padre
  props.data[props.column.field] = value
  if (commit) void nextTick(() => emit('commit'))
}

function setRelation(iri: string | null) {
  const option = options.value.find((o) => o.value === iri)
  set(option ? { id: option.value, label: option.label } : null, true)
}

function onPointerDown(event: PointerEvent) {
  const target = event.target as Element | null
  if (!target || root.value?.contains(target) || target.closest(OVERLAYS)) return
  emit('commit')
}

onMounted(async () => {
  await nextTick()
  const el = input.value?.$el
  const focusable = el?.matches('input') ? el : el?.querySelector<HTMLElement>('input, [tabindex]')
  focusable?.focus()
  if (focusable instanceof HTMLInputElement) focusable.select()
  document.addEventListener('pointerdown', onPointerDown, true)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown, true))
</script>

<style scoped>
.cell-editor {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
}
</style>
