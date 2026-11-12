<template>
  <SelectButton
    v-bind="attrs"
    :model-value="context._value"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    :class="context.classes.input"
    @update:model-value="onUpdate"
    @blur="blur"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import {
  useFormKitInput,
  normalizeOptions,
  resolveOptions,
  toScalarArray,
} from '@/shared/formkit/useFormKitInput'

defineOptions({ name: 'FkSelectButton' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

/** Como en `FkSelect`: opciones `{ label, value }` y el valor escalar, no el objeto. */
const attrs = computed(() => {
  const result = { ...context.value.attrs }
  result.options = normalizeOptions(resolveOptions(result.options))
  if (result.optionLabel == null) result.optionLabel = 'label'
  if (result.optionValue == null) result.optionValue = 'value'
  return result
})

const onUpdate = (value: unknown) => update(toScalarArray(value))
</script>
