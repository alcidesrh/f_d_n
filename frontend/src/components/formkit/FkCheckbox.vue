<script setup lang="ts">
import type { FormKitFrameworkContext } from "@formkit/core";
import { useFormKitInput } from "./useFormKitInput";

defineOptions({ name: "FkCheckbox" });

const props = defineProps<{ context: FormKitFrameworkContext }>();
const { context, update, blur, invalid, disabled } = useFormKitInput(props);

const options = computed(() => {
  const raw = props.context.attrs.options;
  return Array.isArray(raw) ? raw : [];
});
const multiple = computed(() => options.value.length > 0);

function labelOf(option: unknown): unknown {
  if (typeof option !== "object" || option === null) return option;
  const key = props.context.attrs.optionLabel;
  return typeof key === "string" ? (option as Record<string, unknown>)[key] : option;
}

function valueOf(option: unknown): unknown {
  if (typeof option !== "object" || option === null) return option;
  const key = props.context.attrs.optionValue;
  return typeof key === "string" ? (option as Record<string, unknown>)[key] : option;
}
</script>

<template>
  <Checkbox
    v-if="!multiple"
    v-bind="context.attrs"
    :model-value="context._value"
    :input-id="context.id"
    :name="context.node.name"
    :binary="true"
    :disabled="disabled"
    :invalid="invalid"
    :class="context.classes.input"
    @update:model-value="update"
    @blur="blur"
  />
  <div v-else :class="context.classes.input" class="flex flex-wrap items-center gap-4">
    <label v-for="(option, i) in options" :key="i" class="flex items-center gap-2">
      <Checkbox
        :model-value="context._value"
        :input-id="`${context.id}-${i}`"
        :name="context.node.name"
        :value="valueOf(option)"
        :disabled="disabled"
        :invalid="invalid"
        @update:model-value="update"
        @blur="blur"
      />
      <span>{{ labelOf(option) }}</span>
    </label>
  </div>
</template>
