<!--
  Leyenda del mapa del bus: cada uso muestra solo lo que pinta (clases,
  estados de venta/ocupación, chofer, puerta).
-->
<template>
  <div class="bm-legend">
    <div v-for="item in items" :key="item" class="bm-legend__item">
      <span class="bm-legend__glyph">
        <SignalGlyph v-if="item === 'chofer' || item === 'puerta'" :tipo="item" />
        <SeatGlyph v-else-if="item === 'A' || item === 'B'" :clase="item" :con-numero="false" />
        <SeatGlyph v-else clase="A" :estado="item" :con-numero="false" />
      </span>
      <span>{{ ETIQUETAS[item] }}</span>
      <span v-if="conteos && item !== 'A' && item !== 'B' && item !== 'chofer' && item !== 'puerta'" class="font-bold">{{ conteos[item] ?? 0 }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ClaseAsiento, EstadoAsiento, TipoSenal } from "@/core/croquis/types";
import SeatGlyph from "./SeatGlyph.vue";
import SignalGlyph from "./SignalGlyph.vue";
import "./busMap.css";

export type ItemLeyenda = ClaseAsiento | EstadoAsiento | TipoSenal;

defineProps<{
  items: readonly ItemLeyenda[];
  /** Asientos por estado (`conteosPorEstado`); sin él solo se muestran los nombres. */
  conteos?: Partial<Record<EstadoAsiento, number>>;
}>();

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
