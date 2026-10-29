<!--
  Leyenda del mapa del bus: cada uso muestra solo lo que pinta (clases,
  estados de venta/ocupación, chofer, puerta).

  `vertical`: una sola columna con el ícono y la cantidad (el nombre en el
  title) y un "?" que abre la leyenda con los nombres; va al lado del croquis.
-->
<template>
  <div v-if="vertical" class="bm-legend-v">
    <button type="button" class="bm-legend-v__ayuda" aria-label="Qué significa cada color" title="Qué significa cada color" @click="ayuda?.toggle($event)">
      <icon name="help-outline" size="1.1rem" />
    </button>
    <div v-for="item in items" :key="item" class="bm-legend-v__item" :title="ETIQUETAS[item]">
      <span class="bm-legend__glyph">
        <SignalGlyph v-if="item === 'chofer' || item === 'puerta'" :tipo="item" />
        <SeatGlyph v-else-if="item === 'A' || item === 'B'" :clase="item" :con-numero="false" />
        <SeatGlyph v-else clase="A" :estado="item" :con-numero="false" />
      </span>
      <span v-if="conCantidad(item)" class="font-bold tabular-nums">{{ conteos?.[item as EstadoAsiento] ?? 0 }}</span>
    </div>
    <Popover ref="ayuda">
      <div class="bm-legend bm-legend--ayuda">
        <div v-for="item in items" :key="item" class="bm-legend__item">
          <span class="bm-legend__glyph">
            <SignalGlyph v-if="item === 'chofer' || item === 'puerta'" :tipo="item" />
            <SeatGlyph v-else-if="item === 'A' || item === 'B'" :clase="item" :con-numero="false" />
            <SeatGlyph v-else clase="A" :estado="item" :con-numero="false" />
          </span>
          <span>{{ ETIQUETAS[item] }}</span>
        </div>
      </div>
    </Popover>
  </div>
  <div v-else class="bm-legend">
    <div v-for="item in items" :key="item" class="bm-legend__item">
      <span class="bm-legend__glyph">
        <SignalGlyph v-if="item === 'chofer' || item === 'puerta'" :tipo="item" />
        <SeatGlyph v-else-if="item === 'A' || item === 'B'" :clase="item" :con-numero="false" />
        <SeatGlyph v-else clase="A" :estado="item" :con-numero="false" />
      </span>
      <span>{{ ETIQUETAS[item] }}</span>
      <span v-if="conCantidad(item)" class="font-bold">{{ conteos?.[item as EstadoAsiento] ?? 0 }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import Popover from "primevue/popover";
import type { ClaseAsiento, EstadoAsiento, TipoSenal } from "@/core/croquis/types";
import SeatGlyph from "./SeatGlyph.vue";
import SignalGlyph from "./SignalGlyph.vue";
import "./busMap.css";

/** Los recomendados no tienen renglón: se cuentan como disponibles (VentaPage). */
export type ItemLeyenda = ClaseAsiento | Exclude<EstadoAsiento, `recomendado-${string}`> | TipoSenal;

const props = defineProps<{
  items: readonly ItemLeyenda[];
  /** Asientos por estado (`conteosPorEstado`); sin él solo se muestran los nombres. */
  conteos?: Partial<Record<EstadoAsiento, number>>;
  /** Una columna de íconos con su cantidad y un "?" con los nombres. */
  vertical?: boolean;
}>();

const ayuda = ref<InstanceType<typeof Popover> | null>(null);
const conCantidad = (item: ItemLeyenda) => !!props.conteos && item !== "A" && item !== "B" && item !== "chofer" && item !== "puerta";

const ETIQUETAS: Record<ItemLeyenda, string> = {
  A: "Clase A",
  B: "Clase B",
  disponible: "Disponible",
  ocupado: "Estación",
  "ocupado-web": "Página",
  "ocupado-agencia": "Agencia",
  cortesia: "Cortesía",
  voucher: "Voucher",
  seleccionado: "Seleccionado",
  reservado: "Preventa",
  bloqueado: "No disponible",
  chofer: "Chofer",
  puerta: "Puerta",
};
</script>

<style scoped>
.bm-legend {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  flex-wrap: wrap;
  gap: 0.35rem 1rem;
  font-size: 0.78rem;
  color: var(--p-text-muted-color);
  width: calc(2.45rem * 5);
  margin: auto;
}
.bm-legend--ayuda {
  width: auto;
  grid-template-columns: 1fr;
  color: var(--p-text-color);
}
.bm-legend-v {
  display: flex;
  flex: none;
  flex-direction: column;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.78rem;
  color: var(--p-text-muted-color);
}
.bm-legend-v__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.1rem;
  cursor: default;
}
.bm-legend-v__ayuda {
  display: inline-grid;
  place-items: center;
  width: 1.6rem;
  height: 1.6rem;
  border: 0;
  border-radius: 9999px;
  background: transparent;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.bm-legend-v__ayuda:hover,
.bm-legend-v__ayuda:focus-visible {
  background: var(--p-surface-100);
  color: var(--p-text-color);
}
.bm-legend__item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}
.bm-legend__glyph {
  display: inline-grid;
  width: 1.1rem;
  height: 1.1rem;
}
</style>
