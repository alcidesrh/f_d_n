<!--
  Indicador con su comparación contra el periodo anterior. El color de la
  variación dice si el cambio es bueno o malo para el negocio (no si sube):
  con `inverso` (anulaciones, no presentados) bajar es bueno. Flecha + texto,
  nunca solo color.
-->
<template>
  <div class="kpi" :title="ayuda">
    <div class="flex items-center gap-1.5 text-xs font-medium text-muted-color">
      <icon v-if="icono" :name="icono" :weight="300" />
      <span class="truncate">{{ etiqueta }}</span>
    </div>
    <div class="valor tabular-nums">{{ valor }}</div>
    <div class="flex min-h-5 flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
      <span v-if="variacion" class="variacion" :class="variacion.valoracion">
        <icon :name="variacion.tendencia === 'sube' ? 'trending-up' : variacion.tendencia === 'baja' ? 'trending-down' : 'trending-flat'" />
        {{ variacion.texto }}
      </span>
      <span v-else-if="anterior !== undefined" class="text-muted-color">sin base de comparación</span>
      <span v-if="anterior" class="text-muted-color">antes {{ anterior }}</span>
    </div>
    <div v-if="detalle" class="text-xs text-muted-color">{{ detalle }}</div>
  </div>
</template>

<script setup lang="ts">
import type { Variacion } from "@/core/tablero/formato";

defineProps<{
  etiqueta: string;
  valor: string;
  variacion?: Variacion | null;
  /** Valor del periodo anterior ya formateado. */
  anterior?: string;
  detalle?: string;
  icono?: string;
  ayuda?: string;
}>();
</script>

<style scoped>
.kpi {
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 0.75rem 0.875rem;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}
.valor {
  font-size: 1.2rem;
  line-height: 1.2;
  font-weight: 600;
  color: var(--p-text-color);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin: 5px 0;
}
.variacion {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 0 0.375rem;
  margin-top: 5px;
}
.variacion.buena {
  color: #0a7a0a;
  background: rgba(12, 163, 12, 0.12);
}
.variacion.mala {
  color: #b42828;
  background: rgba(208, 59, 59, 0.12);
}
.variacion.neutra {
  color: var(--p-text-muted-color);
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.1));
}
.dark .variacion.buena {
  color: #4ccf4c;
}
.dark .variacion.mala {
  color: #f08080;
}
</style>
