<template>
  <Suspense>
    <List />

    <template #fallback>Loading...</template>
  </Suspense>
</template>

<script setup lang="ts">
import List from 'components/role/RoleList.vue';
</script>
