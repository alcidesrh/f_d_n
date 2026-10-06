<template>
  <span aria-hidden="true" :class="classes" :style="style">{{ glyph }}</span>
</template>

<script setup lang="ts">
/**
 * Ícono de Google Material Symbols (fuente variable): `<icon name="directions-bus-outline" lg />`.
 * Acepta el nombre con o sin prefijo (`home` = `material-symbols:home`) y un
 * tamaño por atajo (`xs`…`xl`) o explícito (`size="1.2rem"`).
 *
 * El nombre sigue el formato de Iconify (`home`, `home-outline`): sin sufijo
 * es el símbolo relleno (`FILL` 1) y `-outline` el de contorno (`FILL` 0).
 * `-rounded`/`-sharp` se ignoran (solo se carga la familia Outlined).
 * El grosor sale del eje `wght` (100–700): `weight` por ícono o, para todos,
 * la variable CSS `--icon-weight` (por defecto 400).
 */
import { computed, useAttrs } from "vue";
import "@fontsource-variable/material-symbols-outlined/fill.css";

const SIZES = { xs: ".80rem", sm: ".95rem", md: "1rem", lg: "1.5rem", xl: "2rem" } as const;

const props = withDefaults(
  defineProps<{
    name: string;
    size?: string;
    /** Grosor del trazo, 100 (fino) a 700 (grueso). Por defecto `--icon-weight` o 400. */
    weight?: number;
    /** Clase de color (por defecto `text-surface-600`). */
    color?: string;
    xs?: boolean;
    sm?: boolean;
    md?: boolean;
    lg?: boolean;
    xl?: boolean;
  }>(),
  { size: "1.rem", weight: undefined, color: "" },
);

const attrs = useAttrs();

const OUTLINE = /-outline(-rounded|-sharp)?$/;
const STYLE = /-(rounded|sharp)$/;

/** `home-outline` → `{ glyph: 'home', fill: 0 }`; `material-symbols:home` → `{ glyph: 'home', fill: 1 }`. */
const parsed = computed(() => {
  const bare = props.name.replace(/^material-symbols:/, "");
  const outline = OUTLINE.test(bare);
  const base = bare.replace(OUTLINE, "").replace(STYLE, "");
  return { glyph: base.replace(/-/g, "_"), fill: outline ? 0 : 1 };
});

const glyph = computed(() => parsed.value.glyph);

const size_ = computed(() => {
  const shortcut = (Object.keys(SIZES) as Array<keyof typeof SIZES>).find((key) => props[key]);
  const value = shortcut ? SIZES[shortcut] : props.size;
  return /^\d+(\.\d+)?$/.test(value) ? `${value}px` : value;
});

const style = computed(() => ({
  fontSize: size_.value,
  width: size_.value,
  height: size_.value,
  minWidth: size_.value,
  minHeight: size_.value,
  fontVariationSettings: `'FILL' ${parsed.value.fill}, 'wght' ${props.weight ?? "var(--icon-weight, 400)"}`,
}));

/** Color por defecto solo si no llega ni `color` ni una `class` propia. */
const classes = computed(() => ["app-icon", "cursor-pointer", props.color || (attrs.class ? "" : "text-surface-600")]);
</script>

<style>
.app-icon {
  display: inline-block;
  flex: none;
  overflow: hidden;
  font-family: "Material Symbols Outlined Variable";
  font-style: normal;
  font-weight: normal;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
  user-select: none;
  -webkit-font-smoothing: antialiased;
  font-feature-settings: "liga";
}
</style>
