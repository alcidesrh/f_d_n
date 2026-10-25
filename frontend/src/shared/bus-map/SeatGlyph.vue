<!--
  Asiento visto desde arriba, mirando al frente del bus (arriba del glifo):
  cojín, apoyabrazos y respaldo atrás. Clase B = butaca reclinable (respaldo
  doble y reposapiés). El número queda siempre derecho aunque el bus se pinte
  en horizontal (`rotacion`).
-->
<template>
  <svg viewBox="0 0 40 40" class="seat" :class="[`seat--${clase}`, `seat--${estado}`, { 'seat--recomendado': estado.startsWith('recomendado-') }]" :style="{ animationDelay: `-${fase}ms` }" aria-hidden="true" focusable="false">
    <g :transform="rotacion ? `rotate(${rotacion} 20 20)` : undefined">
      <rect v-if="clase === 'B'" class="seat__rest" x="11" y="1.5" width="18" height="4" rx="2" />
      <rect class="seat__arm" x="2.5" y="11" width="5" height="21" rx="2.5" />
      <rect class="seat__arm" x="32.5" y="11" width="5" height="21" rx="2.5" />
      <rect class="seat__cushion" x="7" y="7" width="26" height="25" rx="6" />
      <rect class="seat__back" x="5.5" y="29" width="29" height="8.5" rx="3.5" />
      <line v-if="clase === 'B'" class="seat__recline" x1="10" y1="33.25" x2="30" y2="33.25" />
    </g>
    <text v-if="numero != null && conNumero" class="seat__num" x="20" y="20.5">{{ numero }}</text>
    <!-- Sin cobro: una letra además del color (no depender solo del color). -->
    <g v-if="estado === 'cortesia' || estado === 'voucher'" class="seat__marca" :class="[estado]">
      <circle cx="33" cy="9" r="4" class="opascity-70" />
      <!-- <text x="33" y="7.3">{{ estado === "cortesia" ? "C" : "V" }}</text> -->
    </g>
    <g v-if="estado === 'seleccionado'" class="seat__check">
      <circle cx="33" cy="7" r="6" />
      <path d="M30.2 7.1l2 2 3.6-4" />
    </g>
  </svg>
</template>

<script lang="ts">
/**
 * Reloj común de los recomendados: todos parpadean en el mismo compás aunque
 * uno empiece más tarde (al volver de seleccionado). Debe coincidir con la
 * duración de `.seat--recomendado` (2 s).
 */
const INICIO_RELOJ = performance.now();
const CICLO_RECOMENDADO_MS = 2000;
</script>

<script setup lang="ts">
import { ref, watch } from "vue";
import type { ClaseAsiento, EstadoAsiento } from "@/core/croquis/types";

const props = withDefaults(
  defineProps<{
    clase: ClaseAsiento;
    numero?: number | null;
    estado?: EstadoAsiento;
    /** Grados; `-90` con el frente del bus a la izquierda. */
    rotacion?: number;
    /** Oculta el número (miniaturas). */
    conNumero?: boolean;
  }>(),
  { numero: null, estado: "disponible", rotacion: 0, conNumero: true },
);

/**
 * Desfase del parpadeo: al empezar a ser recomendado, el asiento arranca su
 * animación en la fase que lleva el reloj común.
 */
const fase = ref(0);
watch(
  () => props.estado.startsWith("recomendado-"),
  (recomendado) => {
    if (recomendado) fase.value = (performance.now() - INICIO_RELOJ) % CICLO_RECOMENDADO_MS;
  },
  { immediate: true },
);
</script>

<style scoped>
.seat {
  width: 100%;
  height: 100%;
  overflow: visible;
  --seat-fill: var(--bm-seat-a);
  --seat-stroke: var(--bm-seat-a-stroke);
  --seat-ink: var(--bm-seat-a-ink);
  /* Los colores del libre de su clase: a ellos vuelve el recomendado al parpadear. */
  --seat-free-fill: var(--bm-seat-a);
  --seat-free-stroke: var(--bm-seat-a-stroke);
  --seat-free-ink: var(--bm-seat-a-ink);
}
.seat--B {
  --seat-fill: var(--bm-seat-b);
  --seat-stroke: var(--bm-seat-b-stroke);
  --seat-ink: var(--bm-seat-b-ink);
  --seat-free-fill: var(--bm-seat-b);
  --seat-free-stroke: var(--bm-seat-b-stroke);
  --seat-free-ink: var(--bm-seat-b-ink);
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
.seat--ocupado-web {
  --seat-fill: var(--bm-sold-web);
  --seat-stroke: var(--bm-sold-web-stroke);
  --seat-ink: var(--bm-sold-web-ink);
}
.seat--ocupado-agencia {
  --seat-fill: var(--bm-sold-agency);
  --seat-stroke: var(--bm-sold-agency-stroke);
  --seat-ink: var(--bm-sold-agency-ink);
}
.seat--cortesia,
.seat--voucher {
  /*--seat-fill: var(--p-surface-100);
  --seat-stroke: var(--p-surface-400);
  --seat-ink: var(--p-surface-500);*/
  /*&:first-child {*/
  /*& > *:not(.seat__marca) {
    opacity: 0.7;
  }*/
  /*}*/
}
.seat__marca {
  & circle {
    fill: var(--p-red-400);
    stroke-width: 0;
  }
  &.cortesia circle {
    fill: var(--p-sky-500);
    stroke-width: 0;
  }
}

.seat--seleccionado {
  --seat-fill: var(--p-blue-700);
  --seat-stroke: color-mix(in srgb, var(--p-blue-700) 70%, black);
  --seat-ink: var(--p-blue-50);
  filter: drop-shadow(0 2px 5px color-mix(in srgb, var(--p-blue-700) 45%, transparent));
}
/*
 * Libre que conviene vender (encaja con el tramo, ADR-030): cada poco se ve como la venta
 * del asiento vecino que lo recomienda (`seat--recomendado-<estado>`, la del tramo anterior
 * más cercano, o la del posterior) y vuelve a "seleccionado" (el azul del asiento elegido en la
 * venta, antes de facturar) con un fundido de ida y vuelta. Se anima
 * el color del asiento entero (relleno, borde y número; `@property` en busMap.css), así que
 * conserva el sombreado de cojín y brazos. No se apaga con `prefers-reduced-motion`: es una
 * señal que se pidió a propósito.
 */
.seat--recomendado {
  /* El otro color del parpadeo: el de "seleccionado" (igual que `.seat--seleccionado`). */
  --seat-pick-fill: var(--p-blue-700);
  --seat-pick-stroke: color-mix(in srgb, var(--p-blue-700) 70%, black);
  --seat-pick-ink: var(--p-blue-50);
  animation: seat-recomendado 2s ease-in-out infinite;
}
/* Color de la venta del vecino: a él fluye el recomendado desde "seleccionado". */
.seat--recomendado-ocupado {
  --seat-sold-fill: var(--bm-occupied);
  --seat-sold-stroke: var(--bm-occupied-stroke);
  --seat-sold-ink: var(--bm-occupied-ink);
}
.seat--recomendado-ocupado-web {
  --seat-sold-fill: var(--bm-sold-web);
  --seat-sold-stroke: var(--bm-sold-web-stroke);
  --seat-sold-ink: var(--bm-sold-web-ink);
}
.seat--recomendado-ocupado-agencia {
  --seat-sold-fill: var(--bm-sold-agency);
  --seat-sold-stroke: var(--bm-sold-agency-stroke);
  --seat-sold-ink: var(--bm-sold-agency-ink);
}
.seat--recomendado-cortesia,
.seat--recomendado-voucher {
  --seat-sold-fill: var(--p-neutral-100);
  --seat-sold-stroke: var(--p-neutral-400);
  --seat-sold-ink: var(--p-neutral-500);
}
.seat--recomendado-reservado {
  --seat-sold-fill: var(--bm-reserved-soft);
  --seat-sold-stroke: var(--bm-reserved);
  --seat-sold-ink: var(--bm-reserved);
}
/* El color ya lo mueve la animación: sin la transición de `.seat rect`, que lo retrasaría. */
.seat--recomendado rect,
.seat--recomendado line,
.seat--recomendado text {
  transition: none;
}
/* Ciclo de 2 s: 0,7 s en color de la venta del vecino, fundido de 0,5 s a "seleccionado", 0,3 s en él y fundido de vuelta. */
@keyframes seat-recomendado {
  0%,
  35%,
  100% {
    --seat-fill: var(--seat-sold-fill);
    --seat-stroke: var(--seat-sold-stroke);
    --seat-ink: var(--seat-sold-ink);
  }
  60%,
  75% {
    --seat-fill: var(--seat-pick-fill);
    --seat-stroke: var(--seat-pick-stroke);
    --seat-ink: var(--seat-pick-ink);
  }
}
.seat--reservado rect {
  stroke: var(--bm-reserved);
  stroke-dasharray: 3 2.2;
}
.seat--reservado {
  --seat-fill: var(--bm-reserved-soft);
}
.seat--cortesia,
.seat--voucher,
.seat--bloqueado {
  /*opacity: 0.38;*/
  & > *:not(.seat__marca) {
    opacity: 0.38;
  }
}
.seat--cortesia,
.seat--voucher,
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
