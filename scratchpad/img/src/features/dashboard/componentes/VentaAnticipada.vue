<!--
  Venta anticipada: ocupación ya vendida de hoy y los próximos 7 días; la
  raya marca cómo terminó el mismo día de la semana anterior. Si la barra
  queda muy por debajo de su raya a pocas horas de salir, hay que mover la
  venta (página, agencias) o ajustar la oferta.
-->
<template>
  <div class="flex flex-col gap-3">
    <ul class="leyenda" aria-label="Leyenda">
      <li><span class="muestra" />Vendido a hoy</li>
      <li><span class="muestra-raya" />Cómo terminó la semana pasada</li>
    </ul>
    <div class="columnas" role="list">
      <div v-for="(d, i) in dias" :key="d.dia" v-tooltip.top="detalle(d)" class="columna" role="listitem" tabindex="0" :aria-label="detalle(d)">
        <div class="pista">
          <div v-if="d.ocupacion !== null" class="relleno" :style="{ height: `${Math.max(1, Math.round(d.ocupacion * 100))}%` }" />
          <div v-if="d.semanaAnterior !== null" class="raya" :style="{ bottom: `${Math.round(d.semanaAnterior * 100)}%` }" />
          <span v-if="d.ocupacion !== null" class="valor tabular-nums">{{ porciento(d.ocupacion) }}</span>
          <span v-else class="sin text-muted-color" aria-hidden="true">—</span>
        </div>
        <div class="flex flex-col text-center text-xs leading-tight" :class="i === 0 ? 'font-semibold' : 'text-muted-color'">
          <span>{{ i === 0 ? "Hoy" : etiquetaCubo(d.dia, false).split(" ")[0] }}</span>
          <span class="tabular-nums">{{ d.dia.slice(8) }}</span>
        </div>
      </div>
    </div>
    <p v-if="ritmo" class="text-sm">
      <icon :name="ritmo.vendida >= ritmo.semanaAnterior ? 'check-circle-outline' : 'pending-outline'" class="mr-1 align-middle text-muted-color" />
      Ya vendido el <strong>{{ porciento(ritmo.vendida) }}</strong> de los asientos de los próximos {{ ritmo.dias }} días con salidas; la semana pasada esos días terminaron en <strong>{{ porciento(ritmo.semanaAnterior) }}</strong
      >.
    </p>
    <p v-if="sinProgramar.length" class="text-sm text-amber-700">
      <icon name="event-busy-outline" class="mr-1 align-middle" />
      Sin salidas programadas: {{ sinProgramar.join(", ") }}.
      <RouterLink to="/salidas/programar" class="font-medium underline">Programar</RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { ritmoAnticipada } from "@/core/tablero/analisis";
import { etiquetaCubo, fechaCorta, numero, porciento } from "@/core/tablero/formato";
import type { DiaAnticipado } from "@/core/tablero/types";

const props = defineProps<{ dias: DiaAnticipado[] }>();

const ritmo = computed(() => ritmoAnticipada(props.dias));
const sinProgramar = computed(() => props.dias.filter((d) => d.salidas === 0).map((d) => etiquetaCubo(d.dia, false)));

function detalle(d: DiaAnticipado): string {
  const base = `${fechaCorta(d.dia)}: ${d.salidas} salidas, ${numero(d.boletos)} de ${numero(d.asientos)} asientos vendidos`;
  return d.semanaAnterior !== null ? `${base}. Semana pasada terminó en ${porciento(d.semanaAnterior)}.` : base;
}
</script>

<style scoped>
.leyenda {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.leyenda li {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}
.muestra {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  background: var(--estado-generico);
}
.muestra-raya {
  width: 1rem;
  height: 2px;
  background: var(--linea-referencia);
}
.columnas {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 0.375rem;
}
.columna {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  outline: none;
}
.pista {
  position: relative;
  height: 9rem;
  border-bottom: 1px solid var(--p-content-border-color);
}
.relleno {
  position: absolute;
  inset-inline: 18%;
  bottom: 0;
  border-radius: 4px 4px 0 0;
  background: var(--estado-generico);
}
.raya {
  position: absolute;
  inset-inline: 4%;
  height: 2px;
  background: var(--linea-referencia);
  border-radius: 1px;
}
.valor {
  position: absolute;
  top: 0;
  inset-inline: 0;
  text-align: center;
  font-size: 0.7rem;
  font-weight: 600;
}
.sin {
  position: absolute;
  bottom: 0.25rem;
  inset-inline: 0;
  text-align: center;
  font-size: 0.65rem;
  line-height: 1.1;
}
.columna:focus-visible .pista {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
</style>
