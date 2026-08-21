<template>
  <div>
    <PageHead
      title="Formulario dinámico"
      subtitle="EntitySchema → FormKit Schema serializado on demand"
    />

    <div class="card mb-4">
      <label class="block text-sm font-medium mb-1.5">Entidad</label>
      <Select
        v-model="entityName"
        :options="entityOptions"
        option-label="label"
        option-value="value"
        show-clear
        filter
        class="w-full"
        placeholder="Elige una entidad"
      />
      <div class="mt-4 flex items-center gap-4">
        <span class="text-sm font-medium">Modo</span>
        <SelectButton
          v-model="mode"
          :options="modeOptions"
          option-label="label"
          option-value="value"
        />
      </div>
    </div>
    <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card">
        <h3 class="font-semibold mb-2">Datos del formulario</h3>
        <pre class="p-4 rounded-lg bg-surface-100 text-sm overflow-auto">{{
          pretty(formData)
        }}</pre>
      </div>
      <div class="card">
        <h3 class="font-semibold mb-2">Schema serializado (JSON)</h3>
        <pre class="p-4 rounded-lg bg-surface-100 text-sm overflow-auto max-h-96">{{
          schemaJson
        }}</pre>
      </div>
      <div class="card">
        <h3 class="font-semibold mb-2">Último submit</h3>
        <pre class="p-4 rounded-lg bg-surface-100 text-sm overflow-auto">{{
          pretty(submitted)
        }}</pre>
      </div>
    </div>
    <FkEntityForm
      v-if="entityName"
      :key="`${entityName}-${mode}`"
      ref="formRef"
      :entity="entityName"
      v-model:form-data="formData"
      :mode="mode"
      @submitted="onSubmitted"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSchemaRepositoryStore } from '@/stores/schemaRepository'
import FkEntityForm from '@/components/formkit/FkEntityForm.vue'
import type { EntityFormMode } from '@/composables/useEntityForm'

const route = useRoute()
const schemaRepo = useSchemaRepositoryStore()

const entityName = ref<string | undefined>(
  typeof route.params.entity === 'string' ? route.params.entity : undefined,
)
const mode = ref<EntityFormMode>('create')
const formData = ref<Record<string, unknown>>({})
const submitted = ref<Record<string, unknown> | null>(null)

const entityOptions = computed(() =>
  Object.keys(schemaRepo.entities)
    .sort()
    .map((name) => ({ label: name, value: name })),
)

const modeOptions = [
  { label: 'Crear', value: 'create' },
  { label: 'Editar', value: 'update' },
] satisfies Array<{ label: string; value: EntityFormMode }>

const formRef = ref<InstanceType<typeof FkEntityForm> | null>(null)

const schemaJson = computed(() => {
  const schema = formRef.value?.schema
  return schema ? JSON.stringify(schema, null, 2) : ''
})

function pretty(value: unknown): string {
  return JSON.stringify(value ?? {}, null, 2)
}

function onSubmitted(item: Record<string, unknown>) {
  submitted.value = item
}
</script>
