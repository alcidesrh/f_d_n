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

  <InputNumber
    v-else
    v-bind="context.attrs"
    :model-value="context._value"
    :input-id="context.id"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    :class="context.classes.input"
    @update:model-value="update"
    @blur="blur"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'

defineOptions({ name: 'FkInputNumber' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)

const clearable = computed(() => props.context.node.props.attrs.clearable === true)
</script>
