<template>
  <ProgressBar v-show="progress > 0" :show-value="false" :value="progress" class="fixed! top-0 z-[999] w-full" />
</template>

<script setup lang="ts">
/** Barra de carga global: avanza (sin llegar al final) mientras haya peticiones en curso. */
import { onUnmounted, ref, watch } from "vue";
import { useLoadingStore } from "@/core/loading";

const loading = useLoadingStore();
const progress = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

watch(
  () => loading.count > 0,
  (active) => {
    clearInterval(timer);
    if (!active) {
      progress.value = 0;
      return;
    }
    progress.value = 10;
    // Cada paso recorre el 15 % de lo que falta hasta el 95 %.
    timer = setInterval(() => (progress.value += (95 - progress.value) * 0.15), 300);
  },
  { immediate: true },
);

onUnmounted(() => clearInterval(timer));
</script>
