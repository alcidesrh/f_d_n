<!-- src/components/Icon.vue -->
<template>
  <!-- Renderiza dinámicamente el ícono de Iconify aplicando tus atributos -->
  <IconifyInstance
    :icon="iconName"
    :stroke-width="props.sw"
    :style="{ width: `${size}px`, height: `${size}px` }"
    :class="clases"
    v-bind="$attrs"
  />
</template>

<script setup lang="ts">
import { computed } from "vue";
import { Icon as IconifyInstance } from "@iconify/vue";

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  sw: {
    type: [String, Number],
    default: 1.5, // Permite usar el de defecto del ícono si no se pasa ninguno
  },
  size: {
    type: String,
    default: '14',
  },
  color: {
    type: String,
    default: "",
  },
  class: {
    type: String,
    default: "",
  },
});

// Normaliza el nombre: transforma "tabler_database-cog" o "tabler-database-cog"
// al formato estándar de Iconify "tabler:database-cog"
const iconName = computed(() => {
  const temp = props.name.replace(/^([a-zA-Oq-z0-9]+)[_]/, "$1:");
  if (props.name == temp) {
    return `lucide:${temp}`;
  }
  return temp;
});
const clases = computed(() => {
  if (!props.color && !props.class) {
    return ["cursor-pointer text-surface-600"];
  }
  return ['cursor-pointer ' + props.class + " " + props.color];
});
</script>

<style scoped>
/* Solución definitiva de herencia para Tabler dentro de Iconify dinámico */
:deep(g),
:deep(path) {
  stroke-width: inherit !important;
}
</style>
