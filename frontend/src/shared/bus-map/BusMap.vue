<!--
  Mapa del bus visto desde arriba (ADR-019): una carrocería por planta con la
  rejilla del croquis. Es solo presentación y sirve a todos los usos:

  - edición del bus: `celdas-vacias` + slot `celda` (el editor pinta cada celda);
  - ocupación de un recorrido: `estado` (ocupado/disponible por asiento);
  - selección de asientos (taquilla, venta en línea): `interactivo` + `@asiento`.

  El croquis no sabe de ventas: el estado de cada asiento lo da quien lo usa.
-->
<template>
  <div class="bm" :class="[`bm--${orientacion}`, `bm--${tamano}`]" :data-orientacion="orientacion">
    <section
      v-for="planta in plantasVisibles"
      :key="planta"
      class="bm-planta"
      :aria-label="nombrePlanta(planta)"
    >
      <header v-if="conEtiquetas" class="bm-planta__head">
        <slot name="planta-cabecera" :planta="planta" :nombre="nombrePlanta(planta)">
          <span class="bm-planta__name">{{ nombrePlanta(planta) }}</span>
          <span class="bm-planta__count">{{ asientosEn(planta) }} asientos</span>
        </slot>
      </header>

      <div class="bm-bus">
        <span class="bm-bus__glass" aria-hidden="true" />
        <span class="bm-bus__mirror bm-bus__mirror--a" aria-hidden="true" />
        <span class="bm-bus__mirror bm-bus__mirror--b" aria-hidden="true" />
        <div class="bm-grid" :style="estiloRejilla(planta)">
          <span
            v-for="columna in pasillos[planta]"
            :key="`pasillo-${columna}`"
            class="bm-aisle"
            :style="estiloPasillo(planta, columna)"
            aria-hidden="true"
          />
          <component
            :is="esBoton(celda.elemento) ? 'button' : 'div'"
            v-for="celda in celdasDe(planta)"
            :key="celda.clave"
            class="bm-cell"
            :class="claseCelda(celda.elemento)"
            :style="estiloCelda(planta, celda)"
            :data-celda="celda.clave"
            :data-planta="celda.planta"
            :data-fila="celda.fila"
            :data-columna="celda.columna"
            v-bind="atributosCelda(celda.elemento)"
            @click="onClick(celda)"
          >
            <slot name="celda" v-bind="celda">
              <SeatGlyph
                v-if="celda.elemento && celda.elemento.tipo === 'asiento'"
                :clase="celda.elemento.clase"
                :numero="celda.elemento.numero"
                :estado="estadoDe(celda.elemento)"
                :rotacion="rotacion"
                :con-numero="tamano !== 'xs'"
              />
              <SignalGlyph
                v-else-if="celda.elemento"
                :tipo="celda.elemento.tipo"
                :rotacion="rotacion"
                :espejo="esIzquierda(planta, celda.elemento)"
              />
            </slot>
          </component>
        </div>
      </div>

      <slot name="planta-pie" :planta="planta" />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  celda as claveCelda,
  columnasPasillo,
  esAsiento,
  indexar,
  plantasDe,
  rejillas,
} from '@/core/croquis/model'
import type {
  AsientoCroquis,
  ElementoCroquis,
  EstadoAsiento,
  Posicion,
  Rejilla,
} from '@/core/croquis/types'
import SeatGlyph from './SeatGlyph.vue'
import SignalGlyph from './SignalGlyph.vue'
import './busMap.css'

export interface CeldaMapa extends Posicion {
  clave: string
  elemento: ElementoCroquis | null
}

const props = withDefaults(
  defineProps<{
    elementos: readonly ElementoCroquis[]
    /** Plantas a pintar (por defecto, las que tienen elementos). */
    plantas?: readonly number[]
    /** Rejilla por planta; por defecto la mínima que contiene los elementos. */
    rejilla?: Record<number, Rejilla>
    /** `vertical`: frente arriba. `horizontal`: frente a la izquierda. */
    orientacion?: 'vertical' | 'horizontal'
    tamano?: 'xs' | 'sm' | 'md' | 'lg'
    /** Estado con el que se pinta cada asiento (venta, ocupación, …). */
    estado?: (asiento: AsientoCroquis) => EstadoAsiento | undefined
    /** Asientos como botones (`@asiento`); los ocupados/bloqueados quedan deshabilitados. */
    interactivo?: boolean
    /** Pinta también las celdas vacías (editor). */
    celdasVacias?: boolean
    /** Cabecera por planta; por defecto solo si hay más de una. */
    etiquetas?: boolean
  }>(),
  {
    plantas: undefined,
    rejilla: undefined,
    orientacion: 'vertical',
    tamano: 'md',
    estado: undefined,
    interactivo: false,
    celdasVacias: false,
    etiquetas: undefined,
  },
)

const emit = defineEmits<{
  asiento: [asiento: AsientoCroquis]
  celda: [celda: CeldaMapa]
}>()

const plantasVisibles = computed(() => props.plantas ?? plantasDe(props.elementos))
const rejillasPorPlanta = computed(
  () => props.rejilla ?? rejillas(props.elementos, {}, plantasVisibles.value),
)
const porCelda = computed(() => indexar(props.elementos))
const conEtiquetas = computed(() => props.etiquetas ?? plantasVisibles.value.length > 1)
const rotacion = computed(() => (props.orientacion === 'horizontal' ? -90 : 0))

const pasillos = computed(() =>
  Object.fromEntries(
    plantasVisibles.value.map((planta) => [
      planta,
      columnasPasillo(props.elementos, planta, rejillaDe(planta)),
    ]),
  ),
)

function rejillaDe(planta: number): Rejilla {
  return rejillasPorPlanta.value[planta] ?? { filas: 1, columnas: 5 }
}

function nombrePlanta(planta: number): string {
  if (plantasVisibles.value.length === 1 && planta === 1) return 'Planta única'
  return planta === 1 ? 'Planta baja' : 'Planta alta'
}

const asientosEn = (planta: number) =>
  props.elementos.filter((e) => e.planta === planta && esAsiento(e)).length

function celdasDe(planta: number): CeldaMapa[] {
  if (!props.celdasVacias) {
    return props.elementos
      .filter((e) => e.planta === planta)
      .map((e) => ({ clave: claveCelda(e), planta, fila: e.fila, columna: e.columna, elemento: e }))
  }
  const { filas, columnas } = rejillaDe(planta)
  const celdas: CeldaMapa[] = []
  for (let fila = 1; fila <= filas; fila++) {
    for (let columna = 1; columna <= columnas; columna++) {
      const clave = claveCelda({ planta, fila, columna })
      celdas.push({ clave, planta, fila, columna, elemento: porCelda.value.get(clave) ?? null })
    }
  }
  return celdas
}

// Geometría: (fila, columna) del croquis → (fila, columna) de la rejilla CSS.
function estiloRejilla(planta: number) {
  const { filas, columnas } = rejillaDe(planta)
  const [cols, rows] = props.orientacion === 'horizontal' ? [filas, columnas] : [columnas, filas]
  return {
    gridTemplateColumns: `repeat(${cols}, var(--bm-cell))`,
    gridTemplateRows: `repeat(${rows}, var(--bm-cell))`,
  }
}

function posicionCss(planta: number, p: Posicion) {
  const { columnas } = rejillaDe(planta)
  return props.orientacion === 'horizontal'
    ? { gridColumn: p.fila, gridRow: columnas + 1 - p.columna }
    : { gridColumn: p.columna, gridRow: p.fila }
}

const estiloCelda = (planta: number, p: Posicion) => posicionCss(planta, p)

function estiloPasillo(planta: number, columna: number) {
  const { filas } = rejillaDe(planta)
  const base = posicionCss(planta, { planta, fila: 1, columna })
  return props.orientacion === 'horizontal'
    ? { gridRow: base.gridRow, gridColumn: `1 / span ${filas}` }
    : { gridColumn: base.gridColumn, gridRow: `1 / span ${filas}` }
}

/** La señal está en la mitad izquierda del bus (una puerta se dibuja en ese costado). */
const esIzquierda = (planta: number, e: ElementoCroquis) =>
  e.columna <= rejillaDe(planta).columnas / 2

// Estado e interacción ------------------------------------------------------
const estadoDe = (a: AsientoCroquis): EstadoAsiento => props.estado?.(a) ?? 'disponible'

const noSeleccionable = (a: AsientoCroquis) => ['ocupado', 'bloqueado'].includes(estadoDe(a))

const esBoton = (e: ElementoCroquis | null): e is AsientoCroquis =>
  props.interactivo && !!e && esAsiento(e)

const ETIQUETA_ESTADO: Record<EstadoAsiento, string> = {
  disponible: 'disponible',
  ocupado: 'ocupado',
  seleccionado: 'seleccionado',
  reservado: 'reservado',
  bloqueado: 'bloqueado',
}

function etiqueta(e: ElementoCroquis): string {
  if (!esAsiento(e)) return e.tipo === 'chofer' ? 'Chofer' : 'Puerta'
  const estado = props.estado ? `, ${ETIQUETA_ESTADO[estadoDe(e)]}` : ''
  return `Asiento ${e.numero}, clase ${e.clase}${estado}`
}

function atributosCelda(e: ElementoCroquis | null) {
  if (!e) return {}
  if (esBoton(e)) {
    return {
      type: 'button',
      disabled: noSeleccionable(e),
      'aria-pressed': estadoDe(e) === 'seleccionado',
      'aria-label': etiqueta(e),
      title: etiqueta(e),
    }
  }
  return { role: 'img', 'aria-label': etiqueta(e), title: etiqueta(e) }
}

function claseCelda(e: ElementoCroquis | null) {
  if (!e) return 'bm-cell--vacia'
  return [`bm-cell--${e.tipo}`, esBoton(e) ? 'bm-cell--boton' : '']
}

function onClick(celda: CeldaMapa) {
  emit('celda', celda)
  if (esBoton(celda.elemento) && !noSeleccionable(celda.elemento)) emit('asiento', celda.elemento)
}
</script>

<style scoped>
.bm {
  --bm-cell: 2.45rem;
  --bm-gap: 0.3rem;
  display: flex;
  flex-wrap: wrap;
  gap: calc(var(--bm-cell) * 0.8);
  align-items: flex-start;
}
.bm--xs {
  --bm-cell: 0.72rem;
  --bm-gap: 0.1rem;
}
.bm--sm {
  --bm-cell: 1.3rem;
  --bm-gap: 0.16rem;
}
.bm--lg {
  --bm-cell: 3rem;
  --bm-gap: 0.38rem;
}
.bm--horizontal {
  flex-direction: column;
}

.bm-planta {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
}
.bm-planta__head {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  font-size: 0.8rem;
  padding-inline: 0.25rem;
}
.bm-planta__name {
  font-weight: 600;
  letter-spacing: 0.01em;
  color: var(--p-text-color);
}
.bm-planta__count {
  color: var(--p-text-muted-color);
}

/* Carrocería --------------------------------------------------------------- */
.bm-bus {
  --r-front: calc(var(--bm-cell) * 0.95);
  --r-rear: calc(var(--bm-cell) * 0.32);
  --pad: calc(var(--bm-cell) * 0.42);
  position: relative;
  width: max-content;
  padding: calc(var(--bm-cell) * 0.95) var(--pad) var(--pad);
  border-radius: var(--r-front) var(--r-front) var(--r-rear) var(--r-rear);
  background: linear-gradient(180deg, var(--bm-hull), var(--bm-floor) 30%);
  border: 1px solid var(--bm-hull-border);
  box-shadow:
    0 1px 0 color-mix(in srgb, white 40%, transparent) inset,
    0 10px 28px -18px color-mix(in srgb, var(--p-text-color) 55%, transparent);
}
.bm--horizontal .bm-bus {
  padding: var(--pad) var(--pad) var(--pad) calc(var(--bm-cell) * 0.95);
  border-radius: var(--r-front) var(--r-rear) var(--r-rear) var(--r-front);
  background: linear-gradient(90deg, var(--bm-hull), var(--bm-floor) 30%);
}
.bm-bus__glass {
  position: absolute;
  left: 14%;
  right: 14%;
  top: calc(var(--bm-cell) * 0.2);
  height: calc(var(--bm-cell) * 0.3);
  border-radius: calc(var(--bm-cell) * 0.5) calc(var(--bm-cell) * 0.5) calc(var(--bm-cell) * 0.12)
    calc(var(--bm-cell) * 0.12);
  background: linear-gradient(180deg, var(--bm-glass), transparent 140%);
  border: 1px solid color-mix(in srgb, var(--bm-glass) 70%, var(--bm-hull-border));
}
.bm--horizontal .bm-bus__glass {
  top: 14%;
  bottom: 14%;
  left: calc(var(--bm-cell) * 0.2);
  right: auto;
  width: calc(var(--bm-cell) * 0.3);
  height: auto;
  border-radius: calc(var(--bm-cell) * 0.5) calc(var(--bm-cell) * 0.12) calc(var(--bm-cell) * 0.12)
    calc(var(--bm-cell) * 0.5);
  background: linear-gradient(90deg, var(--bm-glass), transparent 140%);
}
.bm-bus__mirror {
  position: absolute;
  top: calc(var(--bm-cell) * 0.38);
  width: calc(var(--bm-cell) * 0.16);
  height: calc(var(--bm-cell) * 0.46);
  border-radius: 999px;
  background: var(--bm-hull-border);
}
.bm-bus__mirror--a {
  left: calc(var(--bm-cell) * -0.2);
}
.bm-bus__mirror--b {
  right: calc(var(--bm-cell) * -0.2);
}
.bm--horizontal .bm-bus__mirror {
  top: auto;
  left: calc(var(--bm-cell) * 0.38);
  width: calc(var(--bm-cell) * 0.46);
  height: calc(var(--bm-cell) * 0.16);
}
.bm--horizontal .bm-bus__mirror--a {
  top: calc(var(--bm-cell) * -0.2);
  right: auto;
}
.bm--horizontal .bm-bus__mirror--b {
  bottom: calc(var(--bm-cell) * -0.2);
  right: auto;
}

/* Rejilla ------------------------------------------------------------------ */
.bm-grid {
  position: relative;
  display: grid;
  gap: var(--bm-gap);
}
.bm-aisle {
  justify-self: center;
  width: 0;
  border-left: 1.5px dashed var(--bm-aisle);
  pointer-events: none;
}
.bm--horizontal .bm-aisle {
  justify-self: stretch;
  align-self: center;
  width: auto;
  height: 0;
  border-left: 0;
  border-top: 1.5px dashed var(--bm-aisle);
}
.bm-cell {
  position: relative;
  display: grid;
  place-items: stretch;
  width: var(--bm-cell);
  height: var(--bm-cell);
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
}
.bm-cell--boton {
  cursor: pointer;
  border-radius: calc(var(--bm-cell) * 0.2);
  transition: transform 0.15s var(--ease, ease);
}
.bm-cell--boton:not(:disabled):hover {
  transform: translateY(-1px) scale(1.05);
}
.bm-cell--boton:disabled {
  cursor: not-allowed;
}
</style>
