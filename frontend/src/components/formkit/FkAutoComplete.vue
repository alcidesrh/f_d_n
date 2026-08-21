<template>
  <AutoComplete
    v-bind="context.attrs"
    :model-value="context._value"
    :input-id="context.id"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    showClear
    :class="context.classes.input"
    @update:model-value="update"
    @blur="blur"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkAutoComplete' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)
</script>
