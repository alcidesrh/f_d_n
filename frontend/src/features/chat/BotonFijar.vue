<!--
  Fija o quita a una persona en los primeros lugares del chat (bandeja y listas).
  Junto a la fila, oculto hasta que se pasa el puntero (siempre visible si está
  fijada o con dedo, que no tiene puntero): el contenedor de la fila lo muestra.
-->
<template>
  <button
    type="button"
    class="fijar"
    :class="{ 'fijar--on': fijado }"
    :aria-pressed="fijado"
    :aria-label="`${fijado ? 'Quitar de fijados a' : 'Fijar a'} ${nombre}`"
    v-tooltip.left="fijado ? 'Quitar de fijados' : 'Fijar arriba'"
    @click="emit('alternar')"
  >
    <icon :name="fijado ? 'pin' : 'pin-outline'" size="1.05rem" color="text-current" />
  </button>
</template>

<script setup lang="ts">
defineProps<{ fijado: boolean; nombre: string }>()
const emit = defineEmits<{ alternar: [] }>()
</script>

<style scoped>
.fijar {
  position: absolute;
  top: 50%;
  right: 0.5rem;
  translate: 0 -50%;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  color: var(--p-surface-500);
  opacity: 0;
  transition: opacity var(--transition), background var(--transition);
  &:hover {
    color: var(--p-primary-color);
    background: var(--p-surface-200);
  }
  &:focus-visible {
    opacity: 1;
    outline: 2px solid color-mix(in srgb, var(--p-primary-color) 60%, transparent);
  }
  @media (pointer: coarse) {
    width: 2.5rem;
    height: 2.5rem;
  }
}
.fijar--on {
  opacity: 1;
  color: var(--p-primary-color);
}
@media (hover: none) {
  .fijar {
    opacity: 1;
  }
}
</style>
