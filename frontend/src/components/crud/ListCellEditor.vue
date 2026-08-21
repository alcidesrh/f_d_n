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
    :model-value="data[column.field]"
    :show-icon="true"
    @update:model-value="(value: unknown) => setValue(value)"
  />
  <InputNumber
    v-else-if="kind === 'number'"
    :model-value="data[column.field]"
    @update:model-value="(value: unknown) => setValue(value)"
  />
  <InputText
    size="small"
    v-else
    :model-value="data[column.field]"
    @update:model-value="(value: unknown) => setValue(value)"
  />
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { CollectionFieldConfig } from '@/stores/entities/types'
import { useEntityRegistry } from '@/composables/useEntityRegistry'
defineOptions({ name: 'ListCellEditor' })
const props = defineProps<{
  column: CollectionFieldConfig
  data: Record<string, unknown>
}>()
const registry = useEntityRegistry()
const store = registry.getEntity()
const options = ref([])
const kind = store.getFieldKind(props.column.field)
// computed<FilterFieldKind>(() => store.getFieldKind(props.column.field));
if (kind == 'relation') {
  const entry = store.metadata.fields.find((f) => f.name === props.column.field)
  // if (!entry) return [];

  const target = await registry.getEntity(entry.namedType)
  await target.loadFullList()

  options.value = target.fullList.map((option) => ({ label: option.label, value: option.id }))
}

const booleanOptions = [
  { label: 'Sí', value: true },
  { label: 'No', value: false },
]

const relationId = computed(() => {
  const value = props.data[props.column.field]
  if (value && typeof value === 'object') return (value as { id?: unknown }).id ?? null
  return null
})
function setRelation(value: string | null) {
  props.data[props.column.field] = value ? (options.find((o) => o.value === value) ?? null) : null
}

function setValue(value: unknown) {
  props.data[props.column.field] = value
}
</script>
