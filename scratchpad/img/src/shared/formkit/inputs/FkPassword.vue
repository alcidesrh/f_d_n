<template>
  <Password
    v-bind="rootAttrs"
    :model-value="context._value"
    :input-id="context.id"
    :input-props="{ autocomplete: context.attrs.autocomplete }"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    :feedback="false"
    :class="context.classes.input"
    @update:model-value="update"
    @blur="blur"
    toggleMask
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { omit, useFormKitInput } from '@/shared/formkit/useFormKitInput'

defineOptions({ name: 'FkPassword' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)
// PrimeVue pone los attrs en el contenedor: `autocomplete` va al <input> (`input-props`).
const rootAttrs = computed(() => omit(context.value.attrs, 'autocomplete'))
</script>
