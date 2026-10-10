<template>
  <ListDateRangeFilter v-if="kind === 'date'" :model-value="modelValue" :label="label" :input-id="inputId" @update:model-value="emit('update:modelValue', $event)" />
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
/** Input del filtro de una columna según su tipo (texto, número, sí/no, relación, rango de fechas). */
import ListDateRangeFilter from './ListDateRangeFilter.vue'
import type { FilterFieldKind } from './listUtils'
import type { FilterOption } from './useListFilters'

withDefaults(
  defineProps<{
    kind: FilterFieldKind
    label: string
    modelValue: unknown
    options?: FilterOption[]
    /** Relación a muchos: varios valores. */
    multiple?: boolean
    inputId?: string
  }>(),
  { options: () => [], multiple: false, inputId: undefined },
)

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()
</script>
