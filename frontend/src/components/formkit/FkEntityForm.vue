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
      <FormKit :id="formId" type="form" v-model="formData" :submit-label="submitLabel" :disabled="submitting" @submit="onSubmit" :actions="false">
        <Fluid>
          <FormKitSchema :schema="schema" />
        </Fluid>
      </FormKit>

      <div class="mt-4 flex justify-end">
        <Button label="Restablecer" severity="secondary" variant="text" size="small" :disabled="submitting" @click="onReset" />
      </div>
    </template>

    <Message v-else severity="warn" :closable="false"> Sin campos serializables para {{ entity }} </Message>
  </div>
</template>

<script setup lang="ts">
import { computed, useId, watch } from "vue";
import { useConfirm } from "primevue/useconfirm";
import { submitForm } from "@formkit/core";
import { useEntityForm } from "@/composables/useEntityForm";
import type { EntityFormMode } from "@/composables/useEntityForm";

defineOptions({ name: "FkEntityForm" });

const props = withDefaults(
  defineProps<{
    entity: string;
    /** Id (número o IRI) del registro a editar; sin id el formulario es de alta. */
    id?: string | number | null;
    mode?: EntityFormMode;
    initialData?: Record<string, unknown>;
    labels?: Record<string, string>;
    submitLabel?: string;
  }>(),
  {
    id: null,
    mode: "create",
    initialData: () => ({}),
    labels: () => ({}),
    submitLabel: "Guardar",
  },
);

const emit = defineEmits<{
  submitted: [item: Record<string, unknown>];
  deleted: [];
  cancel: [];
  error: [message: string];
}>();

const formData = defineModel<Record<string, unknown>>("formData", { default: () => ({}) });

const { schema, loading, submitting, error, mode, submit, remove, reset, setMode, setInitialData, setLabels } = useEntityForm(() => props.entity, {
  id: () => props.id,
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

const formId = `entity-form-${useId()}`;

/** "Guardar" del SplitButton: dispara el submit de FormKit (valida y llama a `onSubmit`). */
function save() {
  submitForm(formId);
}

function onReset() {
  formData.value = {};
  reset();
}

defineExpose({ schema, loading, submitting, error });

const confirm = useConfirm();

function askDelete() {
  confirm.require({
    header: "Eliminar registro",
    message: `¿Eliminar este registro de ${props.entity}? No se puede deshacer.`,
    acceptProps: { label: "Eliminar", severity: "danger" },
    rejectProps: { label: "Cancelar", severity: "secondary", outlined: true },
    accept: async () => {
      try {
        await remove();
        emit("deleted");
      } catch (cause) {
        emit("error", cause instanceof Error ? cause.message : String(cause));
      }
    },
  });
}

const items = computed(() => [
  { label: "Cancelar", icon: "arrow-back-up", command: () => emit("cancel") },
  ...(mode.value === "update" ? [{ label: "Eliminar", icon: "trash", command: askDelete }] : []),
]);
</script>
