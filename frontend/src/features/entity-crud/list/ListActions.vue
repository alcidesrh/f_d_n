<template>
  <div class="flex justify-center gap-3 px-3">
    <icon @click="emit('edit', item)" name="pencil" size="1.2rem" sw="1.3" />
    <icon name="trash" @click="emit('delete', item)" size="1.2rem" sw="1.3" />
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
