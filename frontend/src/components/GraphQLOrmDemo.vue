<template>
  <div class="p-6 max-w-2xl mx-auto bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-slate-200 dark:border-slate-800">
    <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
      <span class="i-heroicons-circle-stack text-indigo-500"></span>
      GraphQL-ORM Demo: Statuses
    </h2>

    <!-- Form for creating status -->
    <form @submit.prevent="handleAddStatus" class="space-y-4 mb-6 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
      <h3 class="font-semibold text-slate-700 dark:text-slate-200 text-sm">Nuevo Status</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Nombre *</label>
          <input
            v-model="statusName"
            type="text"
            placeholder="Ej. ACTIVO"
            class="w-full px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Label</label>
          <input
            v-model="statusLabel"
            type="text"
            placeholder="Ej. Estado activo"
            class="w-full px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          />
        </div>
      </div>
      <button
        type="submit"
        class="px-4 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow transition-colors"
      >
        Agregar Status
      </button>
    </form>

    <!-- Error notifications -->
    <div v-if="errorMessage" class="mb-4 p-3 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-xs rounded">
      <p class="font-bold">{{ errorMessage }}</p>
      <ul v-if="fieldViolations.length" class="mt-1 list-disc list-inside">
        <li v-for="v in fieldViolations" :key="v.propertyPath">
          <strong>{{ v.propertyPath }}:</strong> {{ v.message }}
        </li>
      </ul>
    </div>

    <!-- Data list -->
    <div v-if="isLoading" class="text-slate-500 text-sm py-4 text-center animate-pulse">
      Cargando desde GraphQL API Platform...
    </div>
    <div v-else-if="error" class="text-red-500 text-sm py-4">
      Error: {{ (error as Error).message }}
    </div>
    <ul v-else-if="data?.items.length" class="divide-y divide-slate-100 dark:divide-slate-800">
      <li v-for="status in data.items" :key="status.id" class="py-3 flex justify-between items-center">
        <div>
          <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm">{{ status.nombre }}</span>
          <span v-if="status.label" class="ml-2 text-xs text-slate-500 dark:text-slate-400">({{ status.label }})</span>
          <span class="block text-xs font-mono text-slate-400">{{ status.id }}</span>
        </div>
        <button
          @click="handleRemoveStatus(status.id)"
          class="px-2.5 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 rounded transition-colors"
        >
          Eliminar
        </button>
      </li>
    </ul>
    <div v-else class="text-slate-400 text-sm text-center py-6">
      No hay registros. Crea uno usando el formulario.
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { useCollection } from '@/composables/use-collection';
import { useEntityMutations } from '@/composables/use-entity-mutations';
import { ClientValidationError, GraphQLApiError } from '@graphql-orm/core';
import type { EntityMap } from '@/types/entities';

const statusName = ref('');
const statusLabel = ref('');
const errorMessage = ref<string | null>(null);
const fieldViolations = ref<Array<{ propertyPath: string; message: string }>>([]);

const { data, isLoading, error } = useCollection<EntityMap['Status']>('Status');
const { create, remove } = useEntityMutations<EntityMap['Status']>('Status');

async function handleAddStatus() {
  errorMessage.value = null;
  fieldViolations.value = [];

  if (!statusName.value.trim()) {
    errorMessage.value = 'El nombre es obligatorio.';
    return;
  }

  try {
    await create({
      nombre: statusName.value,
      label: statusLabel.value || undefined,
    });
    statusName.value = '';
    statusLabel.value = '';
  } catch (e) {
    if (e instanceof ClientValidationError) {
      errorMessage.value = `Validación cliente falló: ${e.message}`;
    } else if (e instanceof GraphQLApiError) {
      errorMessage.value = `El backend rechazó la mutación: ${e.message}`;
      fieldViolations.value = e.violations;
    } else {
      errorMessage.value = (e as Error).message;
    }
  }
}

async function handleRemoveStatus(id: string) {
  try {
    await remove(id);
  } catch (e) {
    errorMessage.value = `Error al eliminar: ${(e as Error).message}`;
  }
}
</script>
