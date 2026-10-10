<!-- Una cifra de la vista previa de un reporte: etiqueta pequeña y valor grande. -->
<template>
  <div class="rounded-lg border border-surface-200 p-3" :class="{ 'border-l-4 !border-l-primary': destacada }">
    <div class="text-xs font-medium uppercase tracking-wide text-muted-color">{{ etiqueta }}</div>
    <div class="mt-0.5 text-lg font-semibold tabular-nums" :class="{ 'text-red-600 dark:text-red-400': alerta }">{{ valor }}</div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ etiqueta: string; valor: string | number; destacada?: boolean; alerta?: boolean }>()
</script>
