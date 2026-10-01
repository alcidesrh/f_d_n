<!-- Banda de título de las páginas informativas. -->
<template>
  <section class="bg-gradient-to-br from-marca-950 to-marca-800 text-white">
    <div class="contenedor py-8 md:py-12">
      <h1 class="m-0 text-2xl font-bold tracking-tight md:text-4xl">{{ titulo }}</h1>
      <p v-if="entradilla" class="m-0 mt-2 max-w-2xl text-sm text-blue-100 md:text-base">{{ entradilla }}</p>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{ titulo: string; entradilla?: string }>()
</script>
