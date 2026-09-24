<!-- #region Template -->
<template>
  <div>
    <component
      v-if="entityName"
      :is="formComponent"
      :key="`${entityName}:${recordId ?? 'new'}`"
      ref="formRef"
      :entity="entityName"
      :id="recordId"
      v-model:form-data="formData"
      @submitted="onSubmitted"
      @deleted="onDeleted"
      @cancel="toList"
    />
  </div>
</template>
<!-- #endregion -->

<script setup lang="ts">
import { shallowRef } from "vue";
import type { Component } from "vue";
import FkEntityForm from "@/features/entity-crud/form/EntityForm.vue";
import { router } from "@/app/router";
import { notify } from "@/core/notify";
import { entityNameFromSlug } from "@/core/entities/slug";
import { getFormOverride, type EntityFormOverride } from "@/features/entity-crud/form/formOverrides";

defineOptions({ name: "EntityFormPage" });

const props = withDefaults(defineProps<{ entity: string | string[]; id?: string | string[] }>(), {
  entity: "",
  id: undefined,
});

const entityName = computed(() => {
  const raw = Array.isArray(props.entity) ? props.entity[0] : props.entity;
  if (!raw) return "";
  return entityNameFromSlug(raw);
});
/** Id del registro a editar (`/form/:entity/:id?`); `null` = alta. */
const recordId = computed<string | null>(() => {
  const raw = Array.isArray(props.id) ? props.id[0] : props.id;
  return raw ? raw : null;
});
const formData = ref<Record<string, unknown>>({});

function toList() {
  void router.push({ name: "entity-list", params: { entity: props.entity } });
}

function onDeleted() {
  notify.success(`${entityName.value} eliminado`);
  toList();
}

/** Tras crear, pasa a la URL de edición del registro nuevo (los siguientes guardados son `update`). */
function onSubmitted(item: Record<string, unknown>) {
  notify.success(`${entityName.value} guardado`);
  if (recordId.value) return;
  const id = String(item.id ?? "").match(/\/(\d+)$/)?.[1];
  if (!id) return;
  void router.replace({ name: "entity-form", params: { entity: props.entity, id } });
}

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
