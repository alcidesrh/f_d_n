<!--
  Ingresos por hora (hoy) o por día, apilados por canal, con la línea del
  periodo anterior alineado (mismo día de la semana pasada para hoy). Pasar
  el puntero muestra el desglose del cubo. Tabla oculta para lectores de
  pantalla.
-->
<template>
  <div class="flex flex-col gap-2">
    <ul class="leyenda" aria-label="Leyenda">
      <li v-for="c in CANALES" :key="c.id"><span class="muestra" :style="{ background: `var(--serie-${c.id})` }" />{{ c.nombre }}</li>
      <li><span class="muestra-linea" />{{ rotuloAnterior }}</li>
    </ul>
    <div ref="contenedor" class="relative" @mouseleave="activo = null">
      <svg :width="ancho" :height="ALTO" role="img" :aria-label="`Ingresos ${porHora ? 'por hora' : 'por día'} por canal`" class="block">
        <g class="eje">
          <template v-for="t in escalaY.ticks" :key="t">
            <line :x1="M.izq" :x2="ancho - M.der" :y1="y(t)" :y2="y(t)" />
            <text :x="M.izq - 6" :y="y(t)" text-anchor="end" dominant-baseline="middle">{{ dinero(t, true) }}</text>
          </template>
        </g>
        <rect v-if="activo !== null" class="banda" :x="M.izq + activo * banda" :y="M.arr" :width="banda" :height="altoUtil" />
        <g v-for="(c, i) in serie" :key="c.t">
          <template v-if="c.actual">
            <path v-for="s in segmentos(c, i)" :key="s.canal" :d="s.d" :fill="`var(--serie-${s.canal})`" />
          </template>
        </g>
        <polyline class="linea-anterior" :points="puntosAnterior" fill="none" />
        <g class="eje">
          <text v-for="(c, i) in serie" v-show="i % saltoX === 0" :key="c.t" :x="M.izq + i * banda + banda / 2" :y="ALTO - 6" text-anchor="middle">
            {{ etiquetaCubo(c.t, porHora) }}
          </text>
        </g>
        <rect
          v-for="(c, i) in serie"
          :key="`hit-${c.t}`"
          :x="M.izq + i * banda"
          :y="M.arr"
          :width="banda"
          :height="altoUtil"
          fill="transparent"
          @mouseenter="activo = i"
          @touchstart.passive="activo = i"
        />
      </svg>
      <div v-if="cuboActivo" class="tooltip" :style="posTooltip" role="status">
        <div class="font-semibold">{{ tituloCubo(cuboActivo.t) }}</div>
        <template v-if="cuboActivo.actual">
          <div v-for="c in CANALES" :key="c.id" class="fila">
            <span><span class="muestra" :style="{ background: `var(--serie-${c.id})` }" />{{ c.nombre }}</span>
            <span class="tabular-nums">{{ dinero(cuboActivo.actual[c.id], true) }}</span>
          </div>
          <div class="fila border-t border-surface-200 pt-1 font-semibold">
            <span>Total · {{ numero(cuboActivo.actual.boletos) }} boletos</span>
            <span class="tabular-nums">{{ dinero(cuboActivo.actual.total, true) }}</span>
          </div>
        </template>
        <div v-else class="text-muted-color">Aún no llega</div>
        <div class="fila text-muted-color">
          <span>{{ rotuloAnterior }}</span>
          <span class="tabular-nums">{{ dinero(cuboActivo.anterior.total, true) }}</span>
        </div>
        <div v-if="varActiva" class="text-right font-semibold" :class="varActiva.valoracion === 'buena' ? 'text-green-700 dark:text-green-400' : varActiva.valoracion === 'mala' ? 'text-red-700 dark:text-red-400' : ''">
          {{ varActiva.texto }}
        </div>
      </div>
    </div>
    <table class="sr-only">
      <caption>Ingresos por {{ porHora ? 'hora' : 'día' }}</caption>
      <tr><th>Fecha</th><th v-for="c in CANALES" :key="c.id">{{ c.nombre }}</th><th>Total</th><th>{{ rotuloAnterior }}</th></tr>
      <tr v-for="c in serie" :key="c.t">
        <td>{{ tituloCubo(c.t) }}</td>
        <td v-for="k in CANALES" :key="k.id">{{ c.actual ? dinero(c.actual[k.id]) : '—' }}</td>
        <td>{{ c.actual ? dinero(c.actual.total) : '—' }}</td>
        <td>{{ dinero(c.anterior.total) }}</td>
      </tr>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { dinero, escala, etiquetaCubo, fechaCorta, numero, variacion } from '@/core/tablero/formato'
import type { Canal, CuboSerie } from '@/core/tablero/types'
import { useAncho } from './useAncho'

const props = defineProps<{ serie: CuboSerie[]; porHora: boolean; rotuloAnterior: string }>()

const CANALES: { id: Canal; nombre: string }[] = [
  { id: 'estacion', nombre: 'Taquilla' },
  { id: 'agencia', nombre: 'Agencias' },
  { id: 'web', nombre: 'Página web' },
]
const ALTO = 232
const M = { izq: 64, der: 8, arr: 8, aba: 26 }
const GAP = 2

const contenedor = ref<HTMLElement | null>(null)
const ancho = useAncho(contenedor)
const activo = ref<number | null>(null)

const altoUtil = ALTO - M.arr - M.aba
const banda = computed(() => (ancho.value - M.izq - M.der) / Math.max(1, props.serie.length))
const escalaY = computed(() => escala(Math.max(1, ...props.serie.map((c) => Math.max(c.actual?.total ?? 0, c.anterior.total)))))
const y = (v: number) => M.arr + altoUtil - (v / escalaY.value.max) * altoUtil
const saltoX = computed(() => Math.max(1, Math.ceil(props.serie.length / Math.max(1, (ancho.value - M.izq) / 52))))

/** Rectángulo con las esquinas de arriba redondeadas (el extremo del dato); la base queda recta sobre el eje. */
function barra(x: number, yTop: number, w: number, h: number, r: number): string {
  if (h <= 0) return ''
  const rr = Math.min(r, w / 2, h)
  return `M${x},${yTop + h}V${yTop + rr}Q${x},${yTop} ${x + rr},${yTop}H${x + w - rr}Q${x + w},${yTop} ${x + w},${yTop + rr}V${yTop + h}Z`
}

function segmentos(c: CuboSerie, i: number) {
  const a = c.actual!
  const w = Math.max(1, Math.min(32, banda.value - Math.max(GAP, banda.value * 0.28)))
  const x = M.izq + i * banda.value + (banda.value - w) / 2
  const visibles = CANALES.filter((k) => a[k.id] > 0)
  let acumulado = 0
  return visibles.map((k, j) => {
    const base = y(acumulado)
    acumulado += a[k.id]
    const tope = y(acumulado)
    const esUltimo = j === visibles.length - 1
    const alto = base - tope - (esUltimo ? 0 : GAP)
    return { canal: k.id, d: esUltimo ? barra(x, tope, w, alto, 4) : `M${x},${tope + GAP}h${w}v${Math.max(0, alto)}h${-w}Z` }
  })
}

const puntosAnterior = computed(() => props.serie.map((c, i) => `${M.izq + i * banda.value + banda.value / 2},${y(c.anterior.total)}`).join(' '))

const cuboActivo = computed(() => (activo.value === null ? null : (props.serie[activo.value] ?? null)))
const varActiva = computed(() => (cuboActivo.value?.actual ? variacion(cuboActivo.value.actual.total, cuboActivo.value.anterior.total) : null))
const posTooltip = computed(() => {
  const x = M.izq + (activo.value ?? 0) * banda.value + banda.value / 2
  const derecha = x > ancho.value / 2
  return derecha ? { right: `${ancho.value - x + 10}px`, top: '0px' } : { left: `${x + 10}px`, top: '0px' }
})

function tituloCubo(t: string) {
  return props.porHora ? `${fechaCorta(t)} · ${t.slice(11, 13)}:00–${t.slice(11, 13)}:59` : etiquetaCubo(t, false) + ' ' + fechaCorta(t).split(' ')[1]
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
  display: inline-block;
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  margin-right: 0.375rem;
  vertical-align: middle;
}
.leyenda .muestra {
  margin-right: 0;
}
.muestra-linea {
  display: inline-block;
  width: 1rem;
  height: 2px;
  background: var(--linea-referencia);
}
.eje line {
  stroke: var(--p-content-border-color);
  stroke-width: 1;
}
.eje text {
  fill: var(--p-text-muted-color);
  font-size: 11px;
  font-variant-numeric: tabular-nums;
}
.banda {
  fill: var(--p-content-hover-background, rgba(127, 127, 127, 0.08));
}
.linea-anterior {
  stroke: var(--linea-referencia);
  stroke-width: 2;
  stroke-linejoin: round;
  stroke-linecap: round;
}
.tooltip {
  position: absolute;
  z-index: 2;
  min-width: 12rem;
  pointer-events: none;
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  box-shadow: 0 4px 16px rgb(0 0 0 / 0.12);
  padding: 0.5rem 0.625rem;
  font-size: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.fila {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}
</style>
