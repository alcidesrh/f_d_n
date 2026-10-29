<!--
  Ocupación media por día de la semana y hora de salida de las últimas
  semanas (escala de un solo tono: más oscuro, más lleno). Celdas vacías:
  no hubo salidas a esa hora. Sirve para mover salidas de las franjas
  vacías a las llenas. Pasar el puntero (o enfocar) muestra el detalle.
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="overflow-x-auto">
      <div class="mapa" :style="{ gridTemplateColumns: `2.25rem repeat(${horas.length}, minmax(1.1rem, 1fr))` }" role="grid" aria-label="Ocupación por día y hora">
        <span />
        <span v-for="h in horas" :key="`h${h}`" class="hora" role="columnheader">{{ h % 3 === 0 ? h : '' }}</span>
        <template v-for="d in 7" :key="`d${d}`">
          <span class="dia" role="rowheader">{{ DIAS_SEMANA[d - 1] }}</span>
          <span
            v-for="h in horas"
            :key="`${d}-${h}`"
            v-tooltip.top="texto(d, h)"
            class="celda"
            :class="{ vacia: !celda(d, h) }"
            :style="estilo(d, h)"
            role="gridcell"
            :tabindex="celda(d, h) ? 0 : -1"
            :aria-label="texto(d, h)"
          />
        </template>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-color">
      <div class="flex items-center gap-2">
        <span>0%</span>
        <span class="gradiente" aria-hidden="true" />
        <span>100%</span>
        <span class="ml-2 inline-flex items-center gap-1"><span class="celda vacia muestra" />sin salidas</span>
      </div>
      <span>Desde el {{ fechaCorta(demanda.desde) }}</span>
    </div>
    <p v-if="extremos.llena" class="text-sm">
      Más llena: <strong>{{ franja(extremos.llena) }}</strong> ({{ porciento(extremos.llena.ocupacion) }}, {{ extremos.llena.salidas }} salidas)<template v-if="extremos.vacia"
        >. Más vacía: <strong>{{ franja(extremos.vacia) }}</strong> ({{ porciento(extremos.vacia.ocupacion) }}, {{ extremos.vacia.salidas }} salidas)</template
      >.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { extremosDemanda, horasDemanda } from '@/core/tablero/analisis'
import { DIAS_SEMANA, fechaCorta, numero, porciento } from '@/core/tablero/formato'
import type { CeldaDemanda } from '@/core/tablero/types'

const props = defineProps<{ demanda: { desde: string; celdas: CeldaDemanda[] } }>()

const indice = computed(() => new Map(props.demanda.celdas.map((c) => [`${c.dia}-${c.hora}`, c])))
const horas = computed(() => horasDemanda(props.demanda.celdas))
const extremos = computed(() => extremosDemanda(props.demanda.celdas))

const celda = (d: number, h: number) => indice.value.get(`${d}-${h}`)
const franja = (c: CeldaDemanda) => `${DIAS_SEMANA[c.dia - 1]} ${String(c.hora).padStart(2, '0')}:00`

function estilo(d: number, h: number) {
  const c = celda(d, h)
  if (!c || c.ocupacion === null) return {}
  return { background: `color-mix(in oklab, var(--secuencial-max) ${Math.round(c.ocupacion * 100)}%, var(--secuencial-min))` }
}

function texto(d: number, h: number): string {
  const c = celda(d, h)
  const nombre = `${DIAS_SEMANA[d - 1]} ${String(h).padStart(2, '0')}:00`
  if (!c) return `${nombre}: sin salidas`
  return `${nombre}: ${porciento(c.ocupacion)} de ocupación · ${c.salidas} salidas · ${numero(c.boletos)} boletos`
}
</script>

<style scoped>
.mapa {
  display: grid;
  gap: 2px;
  min-width: 28rem;
}
.hora,
.dia {
  font-size: 0.65rem;
  color: var(--p-text-muted-color);
  font-variant-numeric: tabular-nums;
}
.hora {
  text-align: start;
}
.dia {
  display: flex;
  align-items: center;
}
.celda {
  aspect-ratio: 1;
  min-height: 1rem;
  max-height: 2rem;
  border-radius: 3px;
  outline: none;
}
.celda:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
.celda.vacia {
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.06));
  border: 1px solid var(--p-content-border-color);
}
.muestra {
  display: inline-block;
  width: 0.75rem;
  height: 0.75rem;
  min-height: 0;
}
.gradiente {
  width: 6rem;
  height: 0.5rem;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--secuencial-min), var(--secuencial-max));
}
</style>
