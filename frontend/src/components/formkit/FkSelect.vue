<script setup lang="ts">
import type { FormKitFrameworkContext } from "@formkit/core";
import { useFormKitInput } from "./useFormKitInput";

defineOptions({ name: "FkSelect" });

const props = defineProps<{ context: FormKitFrameworkContext }>();
const { context, update, blur, invalid, disabled } = useFormKitInput(props);
</script>

<template>
  <Select
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
