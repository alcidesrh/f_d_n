<template>
  <div class="card bg-surface-50 p-[4rem]">
    <div v-if="loading" class="flex flex-col gap-4">
      <Skeleton v-for="i in 6" :key="i" height="3.5rem" />
    </div>

    <Message v-else-if="error && schema.length === 0" severity="error" :closable="false">{{ error }}</Message>

    <template v-else-if="schema.length > 0">
      <Message v-if="error" severity="error" :closable="false" class="mb-4">{{ error }}</Message>
      <div class="mb-[1rem] flex justify-end">
        <SplitButton label="Guardar" :model="actions" :disabled="submitting" outlined severity="secondary" @click="submitForm(formId)">
          <template #dropdownicon><icon name="chevron-down" /></template>
          <template #menuitemicon="{ item }"><icon :name="String(item.icon)" /></template>
        </SplitButton>
      </div>
      <FormKit :id="formId" type="form" :disabled="submitting" :actions="false" @submit="onSubmit">
        <Fluid>
          <FormKitSchema :schema="schema" />
        </Fluid>
      </FormKit>
      <div class="mt-4 flex justify-end">
        <Button label="Restablecer" severity="secondary" variant="text" size="small" :disabled="submitting" @click="reset" />
      </div>
    </template>

    <Message v-else severity="warn" :closable="false">Sin campos serializables para {{ entity }}</Message>
  </div>
</template>

<script setup lang="ts">
/**
 * Formulario genérico de una entidad (alta sin `id`, edición con `id`). Emite
 * los resultados; la navegación la decide quien lo usa (`FormPage`).
 */
import { computed, useId } from "vue";
import { submitForm } from "@formkit/core";
import { useConfirm } from "primevue/useconfirm";
import { useEntityForm } from "./useEntityForm";

const props = defineProps<{ entity: string; id?: string | number | null }>();

const emit = defineEmits<{
  submitted: [item: Record<string, unknown>];
  deleted: [];
  cancel: [];
  error: [message: string];
}>();

const { schema, loading, submitting, error, mode, submit, remove, reset } = useEntityForm(
  () => props.entity,
  { id: () => props.id },
);

const formId = `entity-form-${useId()}`;
const confirm = useConfirm();
const message = (cause: unknown) => (cause instanceof Error ? cause.message : String(cause));

async function onSubmit(data: Record<string, unknown>) {
  try {
    emit("submitted", await submit(data));
  } catch (cause) {
    emit("error", message(cause));
  }
}

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
        emit("error", message(cause));
      }
    },
  });
}

const actions = computed(() => [
  { label: "Cancelar", icon: "arrow-back-up", command: () => emit("cancel") },
  ...(mode.value === "update" ? [{ label: "Eliminar", icon: "trash", command: askDelete }] : []),
]);
</script>
