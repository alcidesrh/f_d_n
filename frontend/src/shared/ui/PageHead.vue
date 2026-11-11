<template>
  <h1 class="page-title">{{ titulo }}</h1>
</template>

<script setup lang="ts">
/** Título de la ruta, con la entidad en las pantallas genéricas y `title` si la página suma algo. */
const props = defineProps<{ title?: string }>()

const route = useRoute()

const titulo = computed(() => [route.meta.title, route.params.entity, props.title].filter(Boolean).join(' '))
</script>
