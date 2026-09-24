<template>
  <div class="flex justify-center gap-3 px-3">
    <button type="button" aria-label="Editar" @click="emit('edit', item)">
      <icon name="pencil" size="1.2rem" sw="1.3" />
    </button>
    <button type="button" aria-label="Eliminar" @click="emit('delete', item)">
      <icon name="trash" size="1.2rem" sw="1.3" />
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{ item: unknown }>();

const emit = defineEmits<{ edit: [item: unknown]; delete: [item: unknown] }>();
</script>
