<!--
  Leyenda del calendario: el color de cada ruta con salidas en la ventana y
  cuántas tiene. Pasar sobre una (o enfocarla) resalta sus salidas; pulsarla
  filtra el calendario por esa ruta (y otra vez, la quita).
-->
<template>
  <div v-if="store.rutas.length" class="leyenda" role="list" aria-label="Rutas">
    <button
      v-for="e in visibles"
      :key="e.ruta.id"
      type="button"
      role="listitem"
      class="leyenda__item"
      :class="{ 'leyenda__item--activa': store.ruta === e.ruta.id }"
      :style="{ '--tono': e.tono }"
      :title="e.ruta.nombre"
      :aria-pressed="store.ruta === e.ruta.id"
      @mouseenter="store.resaltada = e.ruta.id"
      @mouseleave="store.resaltada = null"
      @focus="store.resaltada = e.ruta.id"
      @blur="store.resaltada = null"
      @click="store.ruta = store.ruta === e.ruta.id ? null : e.ruta.id"
    >
      <span class="leyenda__color" />
      <span class="truncate">{{ puntas(e.ruta) }}</span>
      <span class="tabular-nums text-muted-color">{{ e.salidas }}</span>
    </button>
    <button v-if="store.rutas.length > LIMITE" type="button" class="leyenda__mas" @click="todas = !todas">
      {{ todas ? 'Menos' : `+${store.rutas.length - LIMITE} rutas` }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { puntas } from '@/core/salida/calendario'
import { useCalendarioSalidasStore } from './store'

const LIMITE = 12
const store = useCalendarioSalidasStore()
const todas = ref(false)
const visibles = computed(() => (todas.value ? store.rutas : store.rutas.slice(0, LIMITE)))
</script>

<style scoped>
/* Angosto: una sola fila que se desliza; ancho: todas las que quepan. */
.leyenda {
  display: flex;
  gap: 0.35rem;
  overflow-x: auto;
  padding-bottom: 0.15rem;
  scrollbar-width: thin;
}
.leyenda > * {
  flex: none;
}
@container (min-width: 48rem) {
  .leyenda {
    flex-wrap: wrap;
    overflow-x: visible;
  }
}
.leyenda__item {
  display: inline-flex;
  max-width: 14rem;
  align-items: center;
  gap: 0.4rem;
  padding: 0.2rem 0.55rem 0.2rem 0.35rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-color);
  font-size: 0.75rem;
  cursor: pointer;
}
.leyenda__item:hover,
.leyenda__item:focus-visible {
  border-color: light-dark(hsl(calc(var(--tono) * 1deg) 60% 45%), hsl(calc(var(--tono) * 1deg) 60% 65%));
}
.leyenda__item--activa {
  background: light-dark(hsl(calc(var(--tono) * 1deg) 70% 93%), hsl(calc(var(--tono) * 1deg) 30% 22%));
  border-color: light-dark(hsl(calc(var(--tono) * 1deg) 60% 45%), hsl(calc(var(--tono) * 1deg) 60% 65%));
}
.leyenda__color {
  width: 0.75rem;
  height: 0.75rem;
  flex: none;
  border-radius: 0.2rem;
  background: light-dark(hsl(calc(var(--tono) * 1deg) 62% 48%), hsl(calc(var(--tono) * 1deg) 60% 62%));
}
.leyenda__mas {
  padding: 0.2rem 0.55rem;
  border: 0;
  background: transparent;
  color: var(--p-primary-color);
  font-size: 0.75rem;
  cursor: pointer;
}
</style>
