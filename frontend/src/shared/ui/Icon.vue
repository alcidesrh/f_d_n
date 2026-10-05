<template>
  <IconifyInstance :icon="iconName" :style="{ width: size_, height: size_, minWidth: size_, minHeight: size_ }" :class="classes" />
</template>

<script setup lang="ts">
/**
 * Ícono de Google Material Symbols vía Iconify: `<icon name="directions-bus-outline" lg />`.
 * Acepta el nombre con o sin prefijo (`home` = `material-symbols:home`) y un
 * tamaño por atajo (`xs`…`xl`) o explícito (`size="1.2rem"`).
 */
import { computed, useAttrs } from "vue";
import { Icon as IconifyInstance } from "@iconify/vue";

const SIZES = { xs: ".80rem", sm: ".95rem", md: "1rem", lg: "1.5rem", xl: "2rem" } as const;

const props = withDefaults(
  defineProps<{
    name: string;
    size?: string;
    /** Clase de color (por defecto `text-surface-600`). */
    color?: string;
    xs?: boolean;
    sm?: boolean;
    md?: boolean;
    lg?: boolean;
    xl?: boolean;
  }>(),
  { size: "1.3rem", color: "" },
);

const attrs = useAttrs();

const iconName = computed(() => (props.name.includes(":") ? props.name : `material-symbols:${props.name}`));

const size_ = computed(() => {
  const shortcut = (Object.keys(SIZES) as Array<keyof typeof SIZES>).find((key) => props[key]);
  return shortcut ? SIZES[shortcut] : props.size;
});

/** Color por defecto solo si no llega ni `color` ni una `class` propia. */
const classes = computed(() => ["cursor-pointer", props.color || (attrs.class ? "" : "text-surface-600")]);
</script>
