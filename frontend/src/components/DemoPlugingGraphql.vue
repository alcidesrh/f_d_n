<template>
  <div v-if="isLoading">Loading...</div>
  <ul v-else>
    <li v-for="item in statusList?.items" :key="item.id">{{ item.nombre }} ({{ item.id }})</li>
  </ul>
</template>
<script setup lang="ts">
import { useCollection, useItem } from "@graphql-orm/vue";
import type { EntityMap } from "@/entities";

// 1. Fetch collection (automatically reactive, cached via TanStack Query)
const {
  data: statusList,
  isLoading,
  error,
} = useCollection<EntityMap["Usuario"]>("Usuario", {
  first: 10,
  order: [{ nombre: "ASC" }], // Preserves ordering
});

// 2. Fetch single item by IRI
const selectedIri = ref("/api/usuarios/1");
const { data: statusItem } = useItem<EntityMap["Usuario"]>("Usuario", selectedIri);
</script>
