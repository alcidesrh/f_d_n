<template>
  <ListDateRangeFilter v-if="kind === 'date'" :model-value="modelValue" :label="label" :input-id="inputId" @update:model-value="emit('update:modelValue', $event)" />
  <AutoComplete
    v-else-if="kind === 'relation' && search"
    v-model="draft"
    :suggestions="suggestions"
    option-label="label"
    :multiple="multiple"
    dropdown
    force-selection
    :show-clear="!multiple"
    :delay="300"
    :placeholder="multiple && hasValue ? '' : 'Escriba para buscar…'"
    empty-search-message="Sin coincidencias"
    size="small"
    fluid
    :input-id="inputId"
    :aria-label="`Filtrar ${label}`"
    @complete="onComplete"
    @update:model-value="onPick"
  />
  <MultiSelect
    v-else-if="kind === 'relation' && multiple"
    :model-value="(modelValue as unknown[] | null) ?? []"
    :options="options"
    option-label="label"
    option-value="value"
    placeholder="Cualquiera"
    display="chip"
    :max-selected-labels="1"
    selected-items-label="{0} elegidos"
    filter
    show-clear
    size="small"
    fluid
    :input-id="inputId"
    :aria-label="`Filtrar ${label}`"
    @update:model-value="emit('update:modelValue', $event)"
  />
  <Select
    v-else-if="kind === 'relation' || kind === 'boolean'"
    :model-value="modelValue ?? null"
    :options="options"
    option-label="label"
    option-value="value"
    placeholder="Todos"
    :filter="kind === 'relation' && options.length > 8"
    show-clear
    size="small"
    fluid
    :input-id="inputId"
    :aria-label="`Filtrar ${label}`"
    @update:model-value="emit('update:modelValue', $event)"
  />
  <InputNumber
    v-else-if="kind === 'number'"
    :model-value="(modelValue as number | null) ?? null"
    placeholder="="
    :use-grouping="false"
    size="small"
    fluid
    :input-id="inputId"
    :aria-label="`Filtrar ${label}`"
    @update:model-value="emit('update:modelValue', $event)"
  />
  <IconField v-else class="w-full">
    <InputText
      :id="inputId"
      :model-value="(modelValue as string | null) ?? ''"
      placeholder="Contiene…"
      size="small"
      fluid
      :aria-label="`Filtrar ${label}`"
      @update:model-value="emit('update:modelValue', $event)"
      @keydown.esc="emit('update:modelValue', '')"
    />
    <InputIcon v-if="modelValue" class="cursor-pointer" role="button" :aria-label="`Limpiar filtro ${label}`" @click="emit('update:modelValue', '')">
      <icon name="close" size="0.9rem" />
    </InputIcon>
  </IconField>
</template>

<script setup lang="ts">
/**
 * Input del filtro de una columna según su tipo (texto, número, sí/no, relación, rango de fechas).
 * Una relación con `search` (columna `autocomplete`) busca en el servidor al teclear en lugar
 * de mostrar todas las `options`; estas solo traducen los valores elegidos a su etiqueta.
 */
import { computed, ref, watch } from 'vue'
import ListDateRangeFilter from './ListDateRangeFilter.vue'
import type { FilterFieldKind } from './listUtils'
import type { FilterOption } from './useListFilters'

const props = withDefaults(
  defineProps<{
    kind: FilterFieldKind
    label: string
    modelValue: unknown
    options?: FilterOption[]
    /** Relación a muchos: varios valores. */
    multiple?: boolean
    inputId?: string
    /** Autocompletado: options que coinciden con lo tecleado (las busca el servidor). */
    search?: (query: string) => Promise<FilterOption[]>
  }>(),
  { options: () => [], multiple: false, inputId: undefined, search: undefined },
)

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

// Autocompletado ------------------------------------------------------------
const suggestions = ref<FilterOption[]>([])
const hasValue = computed(() => (Array.isArray(props.modelValue) ? props.modelValue.length > 0 : props.modelValue != null && props.modelValue !== ''))

/** Lo elegido como options (el valor es el IRI; la etiqueta, de `options`). */
const selected = computed(() => {
  const toOption = (value: unknown) => props.options.find((option) => option.value === value) ?? { label: String(value), value }
  if (props.multiple) return ((props.modelValue as unknown[] | null) ?? []).map(toOption)
  return hasValue.value ? toOption(props.modelValue) : null
})

/** Lo que muestra el input: lo elegido, o el texto a medio teclear (aún no es un filtro). */
const draft = ref<unknown>(selected.value)
watch(selected, (value) => (draft.value = value))

let lastQuery = ''
async function onComplete(event: { query: string }) {
  lastQuery = event.query
  const found = (await props.search?.(event.query.trim())) ?? []
  if (lastQuery === event.query) suggestions.value = found
}

/** Solo una option elegida (o quitar la elección) cambia el filtro; el tecleo no. */
function onPick(value: unknown) {
  if (props.multiple) emit('update:modelValue', ((value as FilterOption[] | null) ?? []).map((option) => option.value))
  else if (value == null || value === '') emit('update:modelValue', null)
  else if (typeof value === 'object') emit('update:modelValue', (value as FilterOption).value)
}
</script>
