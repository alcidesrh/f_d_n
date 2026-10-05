<template>
  <div class="flex justify-center gap-3 px-3">
    <button type="button" class="tap-target" aria-label="Editar" @click="emit('edit', item)">
      <icon name="edit-outline" size="1.3rem" />
    </button>
    <button type="button" class="tap-target" aria-label="Eliminar" @click="emit('delete', item)">
      <icon name="delete-outline" size="1.3rem" />
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{ item: unknown }>();

const emit = defineEmits<{ edit: [item: unknown]; delete: [item: unknown] }>();
</script>
