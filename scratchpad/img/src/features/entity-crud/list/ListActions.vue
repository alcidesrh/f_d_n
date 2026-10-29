<template>
  <div class="list-actions">
    <button v-for="action in actions" :key="action.key" v-tooltip.left="action.label" type="button" class="list-actions__btn tap-target" :aria-label="action.label" @click="emit('action', action, item)">
      <icon :name="action.icon" size="1.15rem" />
    </button>
    <button v-if="canEdit" v-tooltip.left="'Editar'" type="button" class="list-actions__btn tap-target" aria-label="Editar" @click="emit('edit', item)">
      <icon name="edit-outline" size="1.15rem" />
    </button>
    <button v-if="canDelete" v-tooltip.left="'Eliminar'" type="button" class="list-actions__btn list-actions__btn--danger tap-target" aria-label="Eliminar" @click="emit('delete', item)">
      <icon name="delete-outline" size="1.15rem" />
    </button>
  </div>
</template>

<script setup lang="ts">
import type { EntityListAction } from "./listActions";

/** `canEdit`/`canDelete`: las entidades de solo lectura (boletos, ventas…) no ofrecen editar ni eliminar. */
withDefaults(defineProps<{ item: unknown; actions?: EntityListAction[]; canEdit?: boolean; canDelete?: boolean }>(), { actions: () => [], canEdit: true, canDelete: true });

const emit = defineEmits<{ edit: [item: unknown]; delete: [item: unknown]; action: [action: EntityListAction, item: unknown] }>();
</script>

<style scoped>
.list-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.125rem;
}
.list-actions__btn {
  display: inline-grid;
  width: 1.875rem;
  height: 1.875rem;
  place-items: center;
  border-radius: 0.5rem;
  color: var(--p-text-muted-color);
  cursor: pointer;
  transition:
    background-color 0.15s,
    color 0.15s;
}
.list-actions__btn:hover {
  background: color-mix(in srgb, var(--p-surface-500) 13%, transparent);
  color: var(--p-text-color);
}
.list-actions__btn--danger:hover {
  background: var(--c-danger-soft);
  color: var(--c-danger);
}
.list-actions__btn:hover :deep(span),
.list-actions__btn--danger:hover :deep(span) {
  color: inherit;
}
</style>
