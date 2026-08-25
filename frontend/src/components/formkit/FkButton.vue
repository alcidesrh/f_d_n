<template>
  <Button
    v-bind="context.attrs"
    :label="buttonLabel"
    :disabled="disabled || context.attrs.disabled === true"
    @click="onToggle"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkButton' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, disabled } = useFormKitInput(props)

/** El label vive en el botón PrimeVue, no como sección FormKit (se suprime en el registro). */
const buttonLabel = computed(() =>
  typeof context.value.label === 'string' ? context.value.label : undefined,
)

/**
 * Semántica toggle: cada click invierte el valor del nodo
 * (`true` tras el primer click, `false` tras el siguiente).
 */
function onToggle() {
  update(context.value._value !== true)
}
</script>