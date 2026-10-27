<!--
  Las acciones sobre el registro elegido en un solo desplegable, para el
  teléfono (en pantallas anchas cada pantalla las muestra como botones).
  Elegir una la ejecuta (`ejecutar`) y el desplegable vuelve a quedar vacío;
  una acción deshabilitada se ve con el motivo debajo.
-->
<template>
  <Select :model-value="null" :options="acciones" option-label="etiqueta" option-value="valor" :option-disabled="(a: Accion) => !!a.deshabilitada" :placeholder="placeholder" :disabled="disabled" size="small" fluid scroll-height="20rem" :aria-label="placeholder" @update:model-value="(v: string | null) => v && emit('ejecutar', v)">
    <template #option="{ option }">
      <span class="flex min-w-0 items-start gap-2">
        <icon :name="(option as Accion).icono" class="mt-0.5 shrink-0" :color="(option as Accion).peligro ? 'text-red-600' : undefined" />
        <span class="flex min-w-0 flex-col">
          <span class="flex items-center gap-1" :class="{ 'text-red-600': (option as Accion).peligro }">
            {{ (option as Accion).etiqueta }}
            <icon v-if="(option as Accion).bloqueada" name="lock-outline" size=".85rem" class="opacity-70" />
          </span>
          <small v-if="(option as Accion).deshabilitada && (option as Accion).motivo" class="whitespace-normal text-xs text-muted-color">{{ (option as Accion).motivo }}</small>
        </span>
      </span>
    </template>
    <template #dropdownicon><icon name="bolt-outline" /></template>
  </Select>
</template>

<script setup lang="ts">
export interface Accion {
  valor: string;
  etiqueta: string;
  icono: string;
  deshabilitada?: boolean;
  /** Por qué no se puede (se muestra si está deshabilitada). */
  motivo?: string | null;
  /** Se intenta y, sin una autorización aprobada, se ofrece pedirla (ADR-040). */
  bloqueada?: boolean;
  peligro?: boolean;
}

withDefaults(defineProps<{ acciones: Accion[]; placeholder?: string; disabled?: boolean }>(), { placeholder: "Acciones", disabled: false });
const emit = defineEmits<{ ejecutar: [valor: string] }>();
</script>
