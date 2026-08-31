<template>
  <ProgressBar
    :showValue="false"
    :value="progreso"
    class="z-[999] w-full fixed! top-0"
    :class="{ 'hidden!': progreso == 0 }"
  ></ProgressBar>
</template>
<script setup lang="ts">
const loadingStore = useLoadingStore();

const progreso = ref(0);
const localLoading = ref(loadingStore.globalCount);
let intervalo = null,
  ratio = 20,
  started = false;

function start() {
  started = true;
  intervalo = setInterval(() => {
    if (progreso.value < 90) {
      progreso.value += Math.floor(Math.random() * 5) + ratio;
    } else if (progreso.value < 98) {
      progreso.value += Math.floor(Math.random() * 1) + ratio;
    } else {
      progreso.value = 70;
    }
  }, 300);
}

// Escuchamos los cambios en el indicador loading
watch(
  () => loadingStore.globalCount,
  (nuevoLoading) => {
    if (nuevoLoading == 0) {
      clearInterval(intervalo);
      progreso.value = 0;
      localLoading.value = 0;
      started = false;
      ratio = 10;
    } else if (nuevoLoading > localLoading.value) {
      ratio = localLoading.value / nuevoLoading || 10;
      localLoading.value = nuevoLoading;
      if (!started) {
        progreso.value = 0;
        start();
      }
    } else {
      ratio = localLoading.value / nuevoLoading;
    }
  },
  { immediate: true },
);

// Limpieza para evitar fugas de memoria
onUnmounted(() => {
  clearInterval(intervalo);
});
</script>
