<template>
  <h1 class="page-title">{{ titulo }}</h1>
</template>

<script setup lang="ts">
import { router } from '@/app/router'

/** Título de la ruta, con la entidad en las pantallas genéricas y `title` si la página suma algo. */
const props = defineProps<{ title?: string }>()

const titulo = computed(() => {
  const { meta, params } = router.currentRoute.value
  return [meta.title, params.entity, props.title].filter(Boolean).join(' ')
})
</script>
