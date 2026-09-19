<template>
  <IconifyInstance
    :icon="iconName"
    :stroke-width="props.sw"
    :style="sizeComputed"
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
    default: 1.8, // Permite usar el de defecto del ícono si no se pasa ninguno
  },
  size: {
    type: String,
    default: "1.2rem",
  },
  color: {
    type: String,
    default: "",
  },
  xs: {
    type: Boolean,
    default: false,
  },
  sm: {
    type: Boolean,
    default: false,
  },
  md: {
    type: Boolean,
    default: false,
  },
  lg: {
    type: Boolean,
    default: false,
  },
  xl: {
    type: Boolean,
    default: false,
  },
});

// Normaliza el nombre: transforma "tabler_database-cog" o "tabler-database-cog"
// al formato estándar de Iconify "tabler:database-cog"
const iconName = computed(() => {
  if (props.name.includes(":")) {
    return props.name;
  }
  return `tabler:${props.name}`;
});
const sizeComputed = computed(() => {
  let size;
  if (props.xs) {
    size = ".80rem";
  } else if (props.sm) {
    size = ".95rem";
  } else if (props.md) {
    size = "1rem";
  } else if (props.lg) {
    size = "1.5rem";
  } else if (props.xl) {
    size = "2rem";
  } else {
    size = props.size;
  }
  return {
    width: `${size}`,
    height: `${size}`,
    minWidth: `${size}`,
    minHeight: `${size}`,
  };
});
const clases = computed(() => {
  if (!props.color && !props.class) {
    return ["cursor-pointer text-surface-600"];
  }
  return ["cursor-pointer " + props.class + " " + props.color];
});
</script>

<style scoped>
/* Solución definitiva de herencia para Tabler dentro de Iconify dinámico */
:deep(g),
:deep(path) {
  stroke-width: inherit !important;
}
</style>
