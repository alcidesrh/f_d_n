<template>
  <IconifyInstance :icon="iconName" :stroke-width="sw" :style="{ width: size_, height: size_, minWidth: size_, minHeight: size_ }" :class="classes" />
</template>

<script setup lang="ts">
/**
 * Ícono de Tabler vía Iconify: `<icon name="bus" lg />`. Acepta el nombre
 * con o sin prefijo (`bus` = `tabler:bus`) y un tamaño por atajo (`xs`…`xl`)
 * o explícito (`size="1.2rem"`).
 */
import { computed, useAttrs } from "vue";
import { Icon as IconifyInstance } from "@iconify/vue";

const SIZES = { xs: ".80rem", sm: ".95rem", md: "1rem", lg: "1.5rem", xl: "2rem" } as const;

const props = withDefaults(
  defineProps<{
    name: string;
    /** Grosor del trazo. */
    sw?: string | number;
    size?: string;
    /** Clase de color (por defecto `text-surface-600`). */
    color?: string;
    xs?: boolean;
    sm?: boolean;
    md?: boolean;
    lg?: boolean;
    xl?: boolean;
  }>(),
  { sw: 1.8, size: "1rem", color: "" },
);

const attrs = useAttrs();

const iconName = computed(() => (props.name.includes(":") ? props.name : `tabler:${props.name}`));

const size_ = computed(() => {
  const shortcut = (Object.keys(SIZES) as Array<keyof typeof SIZES>).find((key) => props[key]);
  return shortcut ? SIZES[shortcut] : props.size;
});

/** Color por defecto solo si no llega ni `color` ni una `class` propia. */
const classes = computed(() => ["cursor-pointer", props.color || (attrs.class ? "" : "text-surface-600")]);
</script>

<style scoped>
/* Solución definitiva de herencia para Tabler dentro de Iconify dinámico */
:deep(g),
:deep(path) {
  stroke-width: inherit !important;
}
</style>
