<template>
  <div class="card bg-surface-50 bordser bsorder-surface-300 p-[4rem]">
    <template v-if="loading">
      <div class="flex flex-col gap-4">
        <Skeleton v-for="i in 6" :key="i" height="3.5rem" />
      </div>
    </template>

    <Message v-else-if="error && schema.length === 0" severity="error" :closable="false">
      {{ error }}
    </Message>

    <template v-else-if="schema.length > 0">
      <Message v-if="error" severity="error" :closable="false" class="mb-4">
        {{ error }}
      </Message>
      <div class="flex justify-end mb-[1rem]">
        <SplitButton label="Guardar" @click="save" :model="items" outlined severity="secondary">
          <template #dropdownicon> <icon name="chevron-down" /></template>
          <template #menuitemicon="{ item }">
            <icon :name="item.icon" />
          </template>
        </SplitButton>
      </div>
      <FormKit
        type="form"
        v-model="formData"
        :submit-label="submitLabel"
        :disabled="submitting"
        @submit="onSubmit"
        :actions="false"
      >
        <Fluid>
          <FormKitSchema :schema="schema" />
        </Fluid>
      </FormKit>

      <div class="mt-4 flex justify-end">
        <Button
          label="Restablecer"
          severity="secondary"
          variant="text"
          size="small"
          :disabled="submitting"
          @click="onReset"
        />
      </div>
    </template>

    <Message v-else severity="warn" :closable="false">
      Sin campos serializables para {{ entity }}
    </Message>
  </div>
</template>

<script setup lang="ts">
import { watch } from "vue";
import { useEntityForm } from "@/composables/useEntityForm";
import type { EntityFormMode } from "@/composables/useEntityForm";

defineOptions({ name: "FkEntityForm" });

const props = withDefaults(
  defineProps<{
    entity: string;
    mode?: EntityFormMode;
    initialData?: Record<string, unknown>;
    labels?: Record<string, string>;
    submitLabel?: string;
  }>(),
  {
    mode: "create",
    initialData: () => ({}),
    labels: () => ({}),
    submitLabel: "Guardar",
  },
);

const emit = defineEmits<{
  submitted: [item: Record<string, unknown>];
  error: [message: string];
}>();

const formData = defineModel<Record<string, unknown>>("formData", { default: () => ({}) });

const { schema, loading, submitting, error, submit, reset, setMode, setInitialData, setLabels } =
  useEntityForm(() => props.entity, {
    mode: props.mode,
    initialData: props.initialData,
    labels: props.labels,
  });

watch(() => props.mode, setMode);
watch(() => props.initialData, setInitialData);
watch(() => props.labels, setLabels);

async function onSubmit(data: Record<string, unknown>) {
  try {
    const item = await submit(data);
    emit("submitted", item);
  } catch (cause) {
    emit("error", cause instanceof Error ? cause.message : String(cause));
  }
}

function onReset() {
  formData.value = {};
  reset();
}

defineExpose({ schema, loading, submitting, error });

const items = [
  {
    label: "Cancelar",
    icon: "cancel",
    command: () => {
      triggerToast({
        severity: "success",
        summary: "Updated",
        detail: "Data Updated",
        life: 3000,
      });
    },
  },
  {
    label: "Eliminar",
    icon: "trash",
    command: () => {
      triggerToast({ severity: "warn", summary: "Delete", detail: "Data Deleted", life: 3000 });
    },
  },
  {
    separator: true,
  },
  {
    label: "Quit",
    icon: "pi pi-power-off",
    command: () => {
      window.location.href = "https://vuejs.org/";
    },
  },
];
</script>
