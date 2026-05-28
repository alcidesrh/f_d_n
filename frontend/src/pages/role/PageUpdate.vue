<template>
  <Suspense>
    <Update />

    <template #fallback>Loading...</template>
  </Suspense>
</template>

<script setup lang="ts">
import Update from 'components/role/RoleUpdate.vue';
</script>
