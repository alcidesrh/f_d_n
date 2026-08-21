<template>
  <span v-if="clearable" class="p-input-icon-right">
    <IconField>
      <InputText
        v-bind="cleanAttrs"
        :model-value="context._value"
        :id="context.id"
        :name="context.node.name"
        :disabled="disabled"
        :invalid="invalid"
        :class="context.classes.input"
        @update:model-value="update"
        @blur="blur"
      />
      <InputIcon
        v-if="hasValue"
        @click="clearValue"
        class="pi pi-times cursor-pointer text-surface-500"
      />
    </IconField>
  </span>
  <InputText
    v-else
    v-bind="context.attrs"
    :model-value="context._value"
    :id="context.id"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    :class="context.classes.input"
    @update:model-value="update"
    @blur="blur"
  />
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkInputText' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)
/** Opt-in para listados: muestra un ✕ dentro del input para limpiar el valor. */
const clearable = computed(() => props.context.node.props.attrs.clearable === true)
const hasValue = computed(() => {
  const value = props.context._value
  return (
    value !== '' &&
    value !== null &&
    value !== undefined &&
    !(Array.isArray(value) && value.length === 0)
  )
})
const cleanAttrs = computed(() => {
  const { clearable: _omit, ...rest } = props.context.attrs
  return rest
})
function clearValue() {
  props.context.node.input('')
}
</script>
