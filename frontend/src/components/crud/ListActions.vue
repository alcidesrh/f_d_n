<template>
  <div class="flex justify-between gap-5 px-3">
    <!-- <Button
      icon="pi pi-pencil"
      rounded
      text
      severity="primary"
      aria-label="Editar"
      @click="emit('edit', item)"
    /> -->
    <icon name="pencil" @click="emit('edit', item)" size="14" sw="1.8" color="text-teal-700" />
    <icon name="trash-2" @click="emit('delete', item)" size="14" sw="1.8" color="text-red-700" />
    <!-- <Button
      icon="pi pi-trash"
      rounded
      text
      severity="danger"
      aria-label="Eliminar"
      @click="emit('delete', item)"
    /> -->
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: "ListActions" });

defineProps<{ item: unknown }>();

const emit = defineEmits<{
  edit: [item: unknown];
  delete: [item: unknown];
}>();
</script>
