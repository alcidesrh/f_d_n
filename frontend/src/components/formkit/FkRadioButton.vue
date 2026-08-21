<template>
  <div :class="context.classes.input" class="flex flex-wrap items-center gap-4">
    <div v-for="(option, i) in options" :key="i" class="flex items-center gap-2">
      <RadioButton
        :model-value="context._value"
        :value="valueOf(option)"
        :input-id="`${context.id}-${i}`"
        :name="context.node.name"
        :disabled="disabled"
        :invalid="invalid"
        @update:model-value="update"
        @blur="blur"
      />
      <label :for="`${context.id}-${i}`">{{ labelOf(option) }}</label>
    </div>
  </div>
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkRadioButton' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

const options = computed(() => {
  const raw = props.context.attrs.options
  return Array.isArray(raw) ? raw : []
})

function labelOf(option: unknown): unknown {
  if (typeof option !== 'object' || option === null) return option
  const key = props.context.attrs.optionLabel
  return typeof key === 'string' ? (option as Record<string, unknown>)[key] : option
}

function valueOf(option: unknown): unknown {
  if (typeof option !== 'object' || option === null) return option
  const key = props.context.attrs.optionValue
  return typeof key === 'string' ? (option as Record<string, unknown>)[key] : option
}
</script>
