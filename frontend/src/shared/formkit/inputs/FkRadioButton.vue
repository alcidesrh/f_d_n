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
import { optionField, useFormKitInput } from '@/shared/formkit/useFormKitInput'

defineOptions({ name: 'FkRadioButton' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

const options = computed(() => {
  const raw = props.context.attrs.options
  return Array.isArray(raw) ? raw : []
})

const labelOf = (option: unknown) => optionField(option, props.context.attrs.optionLabel)
const valueOf = (option: unknown) => optionField(option, props.context.attrs.optionValue)
</script>
