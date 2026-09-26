<!--
  Asiento visto desde arriba, mirando al frente del bus (arriba del glifo):
  cojín, apoyabrazos y respaldo atrás. Clase B = butaca reclinable (respaldo
  doble y reposapiés). El número queda siempre derecho aunque el bus se pinte
  en horizontal (`rotacion`).
-->
<template>
  <svg
    viewBox="0 0 40 40"
    class="seat"
    :class="[`seat--${clase}`, `seat--${estado}`]"
    aria-hidden="true"
    focusable="false"
  >
    <g :transform="rotacion ? `rotate(${rotacion} 20 20)` : undefined">
      <rect v-if="clase === 'B'" class="seat__rest" x="11" y="1.5" width="18" height="4" rx="2" />
      <rect class="seat__arm" x="2.5" y="11" width="5" height="21" rx="2.5" />
      <rect class="seat__arm" x="32.5" y="11" width="5" height="21" rx="2.5" />
      <rect class="seat__cushion" x="7" y="7" width="26" height="25" rx="6" />
      <rect class="seat__back" x="5.5" y="29" width="29" height="8.5" rx="3.5" />
      <line v-if="clase === 'B'" class="seat__recline" x1="10" y1="33.25" x2="30" y2="33.25" />
    </g>
    <text v-if="numero != null && conNumero" class="seat__num" x="20" y="20.5">{{ numero }}</text>
    <g v-if="estado === 'seleccionado'" class="seat__check">
      <circle cx="33" cy="7" r="6" />
      <path d="M30.2 7.1l2 2 3.6-4" />
    </g>
  </svg>
</template>

<script setup lang="ts">
import type { ClaseAsiento, EstadoAsiento } from '@/core/croquis/types'

withDefaults(
  defineProps<{
    clase: ClaseAsiento
    numero?: number | null
    estado?: EstadoAsiento
    /** Grados; `-90` con el frente del bus a la izquierda. */
    rotacion?: number
    /** Oculta el número (miniaturas). */
    conNumero?: boolean
  }>(),
  { numero: null, estado: 'disponible', rotacion: 0, conNumero: true },
)
</script>

<style scoped>
.seat {
  width: 100%;
  height: 100%;
  overflow: visible;
  --seat-fill: var(--bm-seat-a);
  --seat-stroke: var(--bm-seat-a-stroke);
  --seat-ink: var(--bm-seat-a-ink);
}
.seat--B {
  --seat-fill: var(--bm-seat-b);
  --seat-stroke: var(--bm-seat-b-stroke);
  --seat-ink: var(--bm-seat-b-ink);
}
.seat rect,
.seat line {
  fill: var(--seat-fill);
  stroke: var(--seat-stroke);
  stroke-width: 1.4;
  transition:
    fill 0.18s var(--ease, ease),
    stroke 0.18s var(--ease, ease);
}
.seat__back {
  fill: color-mix(in srgb, var(--seat-stroke) 38%, var(--seat-fill));
}
.seat__arm,
.seat__rest {
  fill: color-mix(in srgb, var(--seat-stroke) 22%, var(--seat-fill));
}
.seat__recline {
  stroke: var(--seat-fill);
  stroke-width: 1.6;
  stroke-linecap: round;
}
.seat__num {
  fill: var(--seat-ink);
  font-size: 13px;
  font-weight: 650;
  text-anchor: middle;
  dominant-baseline: middle;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  pointer-events: none;
  user-select: none;
}

/* Estados (uso del mapa: venta, ocupación, …) */
.seat--ocupado {
  --seat-fill: var(--bm-occupied);
  --seat-stroke: var(--bm-occupied-stroke);
  --seat-ink: var(--bm-occupied-ink);
}
.seat--seleccionado {
  --seat-fill: var(--p-primary-color);
  --seat-stroke: color-mix(in srgb, var(--p-primary-color) 70%, black);
  --seat-ink: var(--p-primary-contrast-color);
  filter: drop-shadow(0 2px 5px color-mix(in srgb, var(--p-primary-color) 45%, transparent));
}
.seat--reservado rect {
  stroke: var(--bm-reserved);
  stroke-dasharray: 3 2.2;
}
.seat--reservado {
  --seat-fill: var(--bm-reserved-soft);
}
.seat--bloqueado {
  opacity: 0.38;
}
.seat--bloqueado rect {
  stroke-dasharray: 2 2;
}
.seat__check circle {
  fill: var(--p-content-background);
  stroke: var(--p-primary-color);
  stroke-width: 1.4;
}
.seat__check path {
  fill: none;
  stroke: var(--p-primary-color);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>
