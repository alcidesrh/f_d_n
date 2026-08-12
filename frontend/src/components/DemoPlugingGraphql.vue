<template>
  <div v-if="isLoading">Loading...</div>
  <ul v-else>
    <li v-for="item in statusList?.items" :key="item.id">{{ item.nombre }} ({{ item.id }})</li>
  </ul>
</template>
<script setup lang="ts">
import { useCollection } from "@/features/crud/composables/use-collection";
import { useItem } from "@/features/crud/composables/use-item";
import type { EntityMap } from "@/types/entities";

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
