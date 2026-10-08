<template>
  <div class="flex justify-center gap-3 px-3">
    <button v-for="action in actions" :key="action.key" v-tooltip.left="action.label" type="button" class="tap-target" :aria-label="action.label" @click="emit('action', action, item)">
      <icon :name="action.icon" size="1.3rem" />
    </button>
    <button type="button" class="tap-target" aria-label="Editar" @click="emit('edit', item)">
      <icon name="edit-outline" size="1.3rem" />
    </button>
    <button type="button" class="tap-target" aria-label="Eliminar" @click="emit('delete', item)">
      <icon name="delete-outline" size="1.3rem" />
    </button>
  </div>
</template>

<script setup lang="ts">
import type { EntityListAction } from "./listActions";

withDefaults(defineProps<{ item: unknown; actions?: EntityListAction[] }>(), { actions: () => [] });

const emit = defineEmits<{ edit: [item: unknown]; delete: [item: unknown]; action: [action: EntityListAction, item: unknown] }>();
</script>
