<template>
  <div class="flex justify-center gap-1">
    <Button
      icon="pi pi-pencil"
      rounded
      text
      severity="primary"
      aria-label="Editar"
      @click="emit('edit', item)"
    />
    <Button
      icon="pi pi-trash"
      rounded
      text
      severity="danger"
      aria-label="Eliminar"
      @click="emit('delete', item)"
    />
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ListActions' })

defineProps<{ item: unknown }>()

const emit = defineEmits<{
  edit: [item: unknown]
  delete: [item: unknown]
}>()
</script>
