<template>
  <TransferList
    :options="options"
    :model-value="selected"
    :disabled="disabled"
    :class="context.classes.input"
    @update:model-value="update"
  />
</template>
<script setup lang="ts">
import type { FormKitFrameworkContext } from '@formkit/core'
import TransferList from '@/shared/transfer/TransferList.vue'
import {
  useFormKitInput,
  normalizeOptions,
  resolveOptions,
  toScalarArray,
} from '@/shared/formkit/useFormKitInput'

defineOptions({ name: 'FkTransferList' })

const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, disabled } = useFormKitInput(props)

const options = computed(() => normalizeOptions(resolveOptions(context.value.attrs.options)))
const selected = computed(() => {
  const value = context.value._value
  return Array.isArray(value) ? value : []
})
</script>
