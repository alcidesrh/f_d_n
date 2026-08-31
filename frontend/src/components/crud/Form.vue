<!-- #region Template -->
<template>
  <div>
    <component
      v-if="entityName"
      :is="formComponent"
      :key="`${entityName}`"
      ref="formRef"
      :entity="entityName"
      v-model:form-data="formData"
    />
  </div>
</template>
<!-- #endregion -->

<script setup lang="ts">
import { shallowRef } from "vue";
import type { Component } from "vue";
import FkEntityForm from "@/components/formkit/FkEntityForm.vue";
import { entityNameFromSlug } from "@/utils/entitySlug";
import { getFormOverride, type EntityFormOverride } from "./formOverrides";

defineOptions({ name: "EntityFormPage" });

const emit = defineEmits<{
  edit: [item: unknown];
  create: [];
}>();
const props = withDefaults(defineProps<{ entity: string | string[] }>(), { entity: "" });

const entityName = computed(() => {
  const raw = Array.isArray(props.entity) ? props.entity[0] : props.entity;
  if (!raw) return "";
  return entityNameFromSlug(raw);
});
const formData = ref<Record<string, unknown>>({});

/**
 * Resolución dinámica del componente de formulario: si existe un override
 * registrado para la entidad (ej. Menu → MenuForm), se usa; en caso contrario
 * cae al formulario agnóstico FkEntityForm. La carga es lazy (import())
 * y se cachea para no reimportar en re-renders.
 */
const formComponent = shallowRef<Component>(FkEntityForm);

watch(
  entityName,
  async (name) => {
    if (!name) return;
    const override: EntityFormOverride | undefined = getFormOverride(name);
    if (override) {
      const loaded = await override();
      formComponent.value = typeof loaded === "object" && "default" in loaded ? loaded.default : loaded;
    } else {
      formComponent.value = FkEntityForm;
    }
  },
  { immediate: true },
);
</script>
