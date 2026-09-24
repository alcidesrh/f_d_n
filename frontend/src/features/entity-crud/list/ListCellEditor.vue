<template>
  <Select
    v-if="kind === 'relation'"
    :model-value="relationId"
    :options="options"
    option-label="label"
    option-value="value"
    :show-clear="true"
    placeholder="Sin valor"
    @update:model-value="setRelation"
    size="small"
  />
  <Select
    v-else-if="kind === 'boolean'"
    size="small"
    :model-value="data[column.field]"
    :options="booleanOptions"
    option-label="label"
    option-value="value"
    :show-clear="true"
    placeholder="Sin valor"
    @update:model-value="(value: unknown) => setValue(value)"
  />
  <DatePicker
    v-else-if="kind === 'date'"
    :model-value="current"
    :show-icon="true"
    @update:model-value="(value: unknown) => setValue(value)"
  />
  <InputNumber
    v-else-if="kind === 'number'"
    :model-value="current"
    @update:model-value="(value: unknown) => setValue(value)"
  />
  <InputText
    size="small"
    v-else
    :model-value="current"
    @update:model-value="(value: unknown) => setValue(value)"
  />
</template>
<script setup lang="ts">
/**
 * Editor de una celda del listado (edición en línea de PrimeVue). Escribe en
 * `data`, la copia de la fila que PrimeVue entrega en `#editor` y devuelve en
 * `cell-edit-complete` como `newData`.
 */
import { computed, ref } from 'vue'
import type { AgnosticOption } from '@/core/graphql/types'
import { getEntity } from '@/core/entities/registry'
import type { CollectionFieldConfig } from '@/core/entities/types'
import { fieldKind } from './listUtils'

const props = defineProps<{
  entity: string
  column: CollectionFieldConfig
  data: Record<string, unknown>
}>()

const metadata = getEntity(props.entity).metadata
const kind = fieldKind(metadata, props.column.field)
const options = ref<Array<{ label: string; value: string }>>([])

if (kind === 'relation') {
  const target = metadata.fields.find((field) => field.name === props.column.field)?.namedType
  if (target) {
    void getEntity(target)
      .loadFullList()
      .then((list: AgnosticOption[]) => {
        options.value = list.map((option) => ({
          label: option.label,
          value: option.value ?? option.id ?? '',
        }))
      })
  }
}

const booleanOptions = [
  { label: 'Sí', value: true },
  { label: 'No', value: false },
]

/** Valor actual de la celda; el tipo depende del editor (`kind`), de ahí `never`. */
const current = computed(() => props.data[props.column.field] as never)

const relationId = computed(() => {
  const value = props.data[props.column.field]
  return value && typeof value === 'object' ? ((value as { id?: unknown }).id ?? null) : null
})

function setValue(value: unknown) {
  // eslint-disable-next-line vue/no-mutating-props -- contrato del slot #editor de PrimeVue
  props.data[props.column.field] = value
}

function setRelation(iri: string | null) {
  const option = options.value.find((o) => o.value === iri)
  setValue(option ? { id: option.value, label: option.label } : null)
}
</script>
