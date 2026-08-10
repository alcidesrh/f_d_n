<template>
  <div class="space-y-3">
    <div v-if="errorMessage" class="p-3 rounded border border-red-200 dark:border-red-800 text-sm text-red-600 dark:text-red-400">
      {{ errorMessage }}
    </div>
    <FormKit
      type="form"
      :schema="formSchema"
      v-model="formData"
      :submit-label="mode === 'create' ? 'Crear' : 'Guardar'"
      :disabled="submitting"
      @submit="onSubmit"
    />
    <div class="flex justify-end gap-2">
      <Button label="Cancelar" severity="secondary" text @click="emit('cancel')" />
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed, ref, watch, watchEffect } from 'vue';
import type { GraphQLSchema } from 'graphql';
import type { FormKitSchemaNode } from '@formkit/core';
import { useEntityMutations } from '@/composables/use-entity-mutations';
import { ClientValidationError, GraphQLApiError, shortId } from '@graphql-orm/core';
import { buildFormSchema, isRelationNode, type FormMode } from '@/crud/form-schema';
import { buildColumns } from '@/crud/entity-meta';
import { useRelationOptions, type RelationOption } from '@/crud/use-relation-options';

const props = defineProps<{
  entity: string;
  schema: GraphQLSchema;
  mode: FormMode;
  item: Record<string, unknown> | null;
}>();

const emit = defineEmits<{
  (e: 'saved'): void;
  (e: 'cancel'): void;
}>();

const formSchema = computed<FormKitSchemaNode[]>(() => buildFormSchema(props.schema, props.entity, props.mode));
const mutations = useEntityMutations(props.entity);
const relationOptions = useRelationOptions();

const formData = ref<Record<string, unknown>>({});
const submitting = ref(false);
const errorMessage = ref<string | null>(null);

const relationNodes = new Map<string, Array<{ options: RelationOption[] }>>();

function isRecordNode(node: FormKitSchemaNode): node is FormKitSchemaNode & Record<string, unknown> {
  return typeof node === 'object' && node !== null;
}

function collect(nodes: FormKitSchemaNode[]) {
  for (const node of nodes) {
    if (isRelationNode(node)) {
      const list = relationNodes.get(node.typeName) ?? [];
      list.push(node as unknown as { options: RelationOption[] });
      relationNodes.set(node.typeName, list);
    }
    if (isRecordNode(node) && node.$formkit === 'group' && Array.isArray(node.children)) {
      collect(node.children as FormKitSchemaNode[]);
    }
  }
}

function applyOptions() {
  for (const [typeName, nodes] of relationNodes) {
    const opts = relationOptions.optionsFor(typeName).value;
    for (const node of nodes) node.options = opts;
  }
}

watch(formSchema, (nodes) => {
  relationNodes.clear();
  collect(nodes);
  applyOptions();
}, { immediate: true });

watchEffect(() => {
  for (const typeName of relationNodes.keys()) {
    void relationOptions.optionsFor(typeName).value;
  }
  applyOptions();
});

function mapItemToForm(item: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const col of buildColumns(props.schema, props.entity)) {
    const v = item[col.field];
    if (v === undefined || v === null) continue;
    if (col.kind === 'relation') {
      const id = typeof v === 'object' ? (v as Record<string, unknown>)?.id : v;
      if (id === undefined || id === null) continue;
      out[col.field] = typeof id === 'string' && id.includes('/') ? shortId(id) : id;
    } else {
      out[col.field] = v;
    }
  }
  return out;
}

watch(
  () => [props.mode, props.item] as const,
  () => {
    formData.value = props.item ? mapItemToForm(props.item) : {};
    errorMessage.value = null;
  },
  { immediate: true },
);

function zodIssuesToErrors(issues: unknown): Record<string, string> {
  const errs: Record<string, string> = {};
  for (const issue of (issues ?? []) as Array<{ path?: Array<string | number>; message: string }>) {
    const path = (issue.path ?? []).join('.');
    if (path) errs[path] = issue.message;
  }
  return errs;
}

async function onSubmit(data: Record<string, unknown>, node: { setErrors: (errors: Record<string, string>, children?: unknown) => void }) {
  submitting.value = true;
  errorMessage.value = null;
  node.setErrors({});
  try {
    if (props.mode === 'create') {
      await mutations.create(data);
    } else {
      const id = (props.item?.id as string) ?? data.id;
      await mutations.update(id, data);
    }
    emit('saved');
  } catch (e) {
    if (e instanceof ClientValidationError) {
      node.setErrors(zodIssuesToErrors(e.issues));
    } else if (e instanceof GraphQLApiError) {
      const errs: Record<string, string> = {};
      for (const v of e.violations) {
        if (v.propertyPath) errs[v.propertyPath] = v.message;
      }
      node.setErrors(errs);
      if (e.message) errorMessage.value = e.message;
    } else {
      errorMessage.value = (e as Error).message;
    }
  } finally {
    submitting.value = false;
  }
}
</script>
