<template>
  <div class="card bg-surface-50 border border-surface-300 p-[4rem]">
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
          <template #dropdownicon><icon name="chevron-down" /></template>
        </SplitButton>
      </div>

      <FormKit
        type="form"
        v-model="formData"
        :disabled="submitting"
        :actions="false"
      >
        <Fluid>
          <FormKitSchema :schema="renderSchema" />
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
import { computed, watch } from "vue";
import { useEntityForm } from "@/composables/useEntityForm";
import type { EntityFormMode } from "@/composables/useEntityForm";
import type { FormKitSchemaNode } from "@formkit/core";
import type { EntityFormProps } from "@/components/crud/formOverrides";

defineOptions({ name: "MenuForm" });

const props = withDefaults(defineProps<EntityFormProps>(), {
  mode: "create",
  initialData: () => ({}),
  labels: () => ({}),
  submitLabel: "Guardar",
});

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

/**
 * True si el nodo es un FormKit sugar (`{ $formkit, name, options }`).
 */
function isFormKitNode(node: FormKitSchemaNode): node is Record<string, any> & { $formkit: string } {
  return node !== null && typeof node === "object" && "$formkit" in (node as Record<string, unknown>);
}

/** Devuelve los nodos hijos de un row/wrapper DOM (`{ $el, children }`). */
function childrenOf(node: FormKitSchemaNode): FormKitSchemaNode[] {
  if (node === null || typeof node !== "object") return [];
  const record = node as Record<string, unknown>;
  if (Array.isArray(record.children)) return record.children as FormKitSchemaNode[];
  return [];
}

/** Recorre el schema (rows → wrappers → nodos) y devuelve los nodos FormKit con su nombre. */
function collectNodes(nodes: FormKitSchemaNode[]): Array<Record<string, any> & { $formkit: string }> {
  const out: Array<Record<string, any> & { $formkit: string }> = [];
  const walk = (list: FormKitSchemaNode[]) => {
    for (const node of list) {
      if (isFormKitNode(node)) {
        out.push(node);
      } else {
        walk(childrenOf(node));
      }
    }
  };
  walk(nodes);
  return out;
}

function findOptions(nodes: FormKitSchemaNode[], name: string): unknown[] {
  const node = collectNodes(nodes).find((n) => n.name === name);
  return Array.isArray(node?.options) ? node.options : [];
}

function excludeIds(
  options: unknown[],
  excludeIds: unknown[],
): Array<{ label: string; value: unknown }> {
  const excludeSet = new Set(excludeIds.map(String));
  return (options as Array<{ label: string; value: unknown }>).filter(
    (opt) => !excludeSet.has(String(opt.value)),
  );
}

/**
 * Regla de negocio: `parents` y `children` son relaciones ManyToMany
 * self-referenciales mutuamente excluyentes (`parents ∩ children == ∅`).
 * Los options de cada MultiSelect se filtran dinámicamente para que un menú
 * ya elegido en `parents` no aparezca como opción en `children` y viceversa.
 */
const parentOptions = computed(() => findOptions(schema.value, "parents"));
const childOptions = computed(() => findOptions(schema.value, "children"));

const parentsSelected = computed(() => {
  const raw = formData.value?.parents;
  return Array.isArray(raw) ? raw : [];
});

const childrenSelected = computed(() => {
  const raw = formData.value?.children;
  return Array.isArray(raw) ? raw : [];
});

const filteredParents = computed(() => excludeIds(parentOptions.value, childrenSelected.value));
const filteredChildren = computed(() => excludeIds(childOptions.value, parentsSelected.value));

/**
 * Reconstruye el schema reactivo: los nodos `parents`/`children` obtienen
 * options filtradas según la selección cruzada. Los demás campos pasan sin
 * modificaciones.
 */
const renderSchema = computed<FormKitSchemaNode[]>(() => {
  if (!schema.value.length) return schema.value;

  const apply = (nodes: FormKitSchemaNode[]): FormKitSchemaNode[] =>
    nodes.map((node) => {
      if (isFormKitNode(node)) {
        if (node.name === "parents") return { ...node, options: filteredParents.value };
        if (node.name === "children") return { ...node, options: filteredChildren.value };
        return node;
      }
      if (node !== null && typeof node === "object" && "children" in (node as Record<string, unknown>)) {
        return { ...node, children: apply(childrenOf(node)) };
      }
      return node;
    });

  return apply(schema.value);
});

function save() {
  void onSubmit(formData.value);
}

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
        summary: "Actualizado",
        detail: "Datos guardados",
        life: 3000,
      });
    },
  },
  {
    label: "Eliminar",
    icon: "trash",
    command: () => {
      triggerToast({ severity: "warn", summary: "Eliminar", detail: "Datos eliminados", life: 3000 });
    },
  },
];
</script>
