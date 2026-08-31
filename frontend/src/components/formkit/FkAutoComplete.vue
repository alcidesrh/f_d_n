<template>
  <AutoComplete
    v-bind="cleanAttrs"
    :model-value="context._value"
    :input-id="context.id"
    :name="context.node.name"
    :disabled="disabled"
    :invalid="invalid"
    showClear
    :class="context.classes.input"
    class="w-full"
    :suggestions="suggestions"
    :option-label="optionLabel"
    :multiple="multiple"
    @complete="onComplete"
    @update:model-value="update"
    @blur="blur"
  />
</template>

<script setup lang="ts">
<<<<<<< Updated upstream
import type { FormKitFrameworkContext } from '@formkit/core'
import { useFormKitInput } from './useFormKitInput'
=======
import { computed, ref } from "vue";
import type { FormKitFrameworkContext } from "@formkit/core";
import { useFormKitInput } from "./useFormKitInput";
import { getEntity } from "@/composables/useEntityRegistry";
import type { AgnosticOption } from "@/lib/apollo/types";
>>>>>>> Stashed changes

defineOptions({ name: 'FkAutoComplete' })

<<<<<<< Updated upstream
const props = defineProps<{ context: FormKitFrameworkContext }>()
const { context, update, blur, invalid, disabled } = useFormKitInput(props)
=======
const props = defineProps<{ context: FormKitFrameworkContext }>();
const { context, update, blur, invalid, disabled } = useFormKitInput(props);

/** Si se pasa `entityName` en attrs, este componente gestiona la carga internamente. */
const entityName = computed<string | undefined>(
  () => props.context.attrs.entityName as string | undefined,
);

const multiple = computed<boolean>(
  () => props.context.attrs.multiple === true,
);

/** Etiqueta para mostrar en las sugerencias (por defecto "label" de AgnosticOption). */
const optionLabel = computed<string>(
  () => (props.context.attrs.optionLabel as string | undefined) ?? "label",
);

const fullList = ref<AgnosticOption[]>([]);
const suggestions = ref<AgnosticOption[]>([]);

/** Carga lazy de la lista completa (solo cuando entityName está definido). */
async function ensureLoaded() {
  if (!entityName.value || fullList.value.length > 0) return;
  try {
    const store = getEntity(entityName.value);
    fullList.value = await store.loadFullList();
  } catch (e) {
    console.warn("[FkAutoComplete] No se pudo cargar la lista:", e);
  }
}

/** Handler del evento `@complete` de PrimeVue AutoComplete. */
async function onComplete(event: { query: string }) {
  await ensureLoaded();
  const q = event.query.toLowerCase().trim();
  suggestions.value = q
    ? fullList.value.filter((opt) => opt.label.toLowerCase().includes(q))
    : [...fullList.value];
}

/** Eliminar attrs que ya se pasan explícitamente para evitar conflictos con PrimeVue. */
const cleanAttrs = computed(() => {
  const {
    entityName: _en,
    optionLabel: _ol,
    multiple: _m,
    suggestions: _s,
    completeMethod: _cm,
    ...rest
  } = props.context.attrs;
  return rest;
});
>>>>>>> Stashed changes
</script>
