<!--
  Dona de porciones, de mayor a menor desde las 12 en el sentido del reloj,
  junto a su lista ordenada; cada puesto con su color fijo (la mayor siempre
  el primero de la paleta) y, en la cuña donde cabe, su porcentaje del total.
  Con `agrupa`, la cola de cuñas angostas va a
  "Otros" (gris) y abrir "Otros" la vuelve a repartir en la misma dona como
  otro nivel, con su camino de vuelta: ninguna cuña queda tan delgada que no
  se lea ni se acierte, sin lupas que deformen los ángulos. La lista trae todo
  el nivel (también lo que agrupa "Otros") en una sola escala: el porcentaje
  es siempre del total y la barra, contra la mayor. Puntero o dedo sobre una
  cuña o una fila: se resaltan juntas y el centro dice su valor; las cuñas
  angostas se tocan en un ángulo mínimo. Tabla completa para lectores de
  pantalla.
-->
<template>
  <div class="dona @container">
    <nav v-if="niveles.length > 1" class="camino" aria-label="Niveles de la gráfica">
      <Button text size="small" severity="secondary" class="tap-target" aria-label="Volver al nivel anterior" @click="volver(niveles.length - 2)">
        <template #icon><icon name="arrow-back" /></template>
      </Button>
      <template v-for="(n, i) in niveles" :key="i">
        <span v-if="i > 0" class="text-muted-color" aria-hidden="true">›</span>
        <button v-if="i < niveles.length - 1" type="button" class="paso" @click="volver(i)">{{ nombreNivel(i) }}</button>
        <span v-else class="font-medium" aria-current="true">{{ nombreNivel(i) }} · {{ porciento(n.total / total, 1) }} del total</span>
      </template>
    </nav>

    <p v-if="lecturaTexto && niveles.length === 1" class="text-xs text-muted-color">{{ lecturaTexto }}</p>

    <p v-if="actual.total <= 0" class="py-8 text-center text-sm text-muted-color">Sin ventas en el periodo.</p>

    <div v-else class="cuerpo">
      <div
        ref="lienzo"
        class="lienzo"
        tabindex="0"
        role="group"
        :aria-label="`${titulo}. Flechas: recorrer las porciones${resto.length ? '; Enter en Otros: abrirlo' : ''}${niveles.length > 1 ? '; Escape: volver' : ''}.`"
        @pointerleave="salir"
        @keydown="alTeclado"
        @blur="activa = null"
      >
        <svg :viewBox="`${-R} ${-R} ${2 * R} ${2 * R}`" role="img" :aria-label="resumen" class="block h-full w-full" @pointermove="alMover" @pointerdown="alMover" @click="alTocar">
          <path
            v-for="c in lista"
            :key="c.clave"
            :d="arco(c.inicio, c.fin, c.clave === cunaActiva ? R : R - REALCE, INTERIOR)"
            :fill="color(c.clave)"
            class="cuna"
            :class="{ apagada: cunaActiva !== null && c.clave !== cunaActiva, otros: c.clave === OTROS }"
          />
          <text v-for="r in rotulos" :key="`r${r.clave}`" :x="r.x" :y="r.y" :fill="tinta(r.clave)" :font-size="LETRA" class="rotulo" :class="{ apagada: cunaActiva !== null && r.clave !== cunaActiva }">{{ r.texto }}</text>
        </svg>
        <div class="centro" aria-hidden="true">
          <template v-if="enfocada">
            <span class="w-full truncate text-xs text-muted-color">{{ enfocada.etiqueta }}</span>
            <span class="cifra">{{ formato(enfocada.valor) }}</span>
            <span class="text-xs text-muted-color">{{ parte(enfocada.valor) }}{{ niveles.length > 1 ? ' del total' : '' }}</span>
          </template>
          <template v-else>
            <span class="text-xs text-muted-color">{{ niveles.length > 1 ? nombreNivel(niveles.length - 1) : 'Total' }}</span>
            <span class="cifra">{{ formato(actual.total) }}</span>
            <span class="text-xs text-muted-color">{{ niveles.length > 1 ? `${actual.porciones.length + actual.resto.length} ${nombreResto}` : totalRotulo }}</span>
          </template>
        </div>
        <p class="sr-only" aria-live="polite">{{ enfocada ? `${enfocada.etiqueta}: ${formato(enfocada.valor)}, ${parte(enfocada.valor)} del total` : '' }}</p>
      </div>

      <div ref="listaEl" class="lista" :class="{ larga: propias.length + resto.length + ceros.length > 8 }">
        <ol>
          <li v-for="(f, i) in propias" :key="f.clave">
            <FilaPorcion :fila="f" :puesto="fijo ? null : puestoBase + i + 1" :color="color(f.clave)" :valor="formato(f.valor)" :parte="parte(f.valor)" :barra="f.valor / mayor" :activa="activa === f.clave" @pointerenter="activa = f.clave" @pointerleave="salir" />
          </li>
        </ol>
        <template v-if="resto.length">
          <button
            type="button"
            class="otros-boton"
            :class="{ activa: cunaActiva === OTROS }"
            :aria-label="`Abrir Otros en la gráfica: ${resto.length} ${nombreResto}, ${formato(valorOtros)}, ${parte(valorOtros)} del total`"
            @pointerenter="activa = OTROS"
            @pointerleave="salir"
            @focus="activa = OTROS"
            @blur="activa = null"
            @click="abrir()"
          >
            <span class="muestra" :style="{ background: color(OTROS) }" />
            <span class="min-w-0 text-left">
              <span class="block font-medium">Otros · {{ resto.length }} {{ nombreResto }}</span>
              <span class="block text-xs text-muted-color">Ver en la gráfica</span>
            </span>
            <span class="text-right">
              <span class="block whitespace-nowrap font-semibold tabular-nums">{{ formato(valorOtros) }}</span>
              <span class="block text-xs tabular-nums text-muted-color">{{ parte(valorOtros) }}</span>
            </span>
            <icon name="chevron-right" class="self-center text-muted-color" />
          </button>
          <ol :start="puestoBase + propias.length + 1">
            <li v-for="(f, i) in resto" :key="f.clave">
              <FilaPorcion :fila="f" :puesto="fijo ? null : puestoBase + propias.length + i + 1" :color="color(OTROS)" :valor="formato(f.valor)" :parte="parte(f.valor)" :barra="f.valor / mayor" :activa="activa === f.clave || activa === OTROS" @pointerenter="activa = f.clave" @pointerleave="salir" />
            </li>
          </ol>
        </template>
        <ul v-if="ceros.length">
          <li v-for="f in ceros" :key="f.clave">
            <FilaPorcion :fila="f" :puesto="null" color="transparent" :valor="formato(0)" :parte="parte(0)" :barra="0" :activa="false" />
          </li>
        </ul>
      </div>
    </div>

    <table class="sr-only">
      <caption>{{ titulo }}</caption>
      <thead>
        <tr><th>Puesto</th><th>Nombre</th><th>Valor</th><th>Parte del total</th><th>Detalle</th></tr>
      </thead>
      <tbody>
        <tr v-for="(p, i) in todas" :key="p.clave">
          <td>{{ fijo ? '' : i + 1 }}</td>
          <td>{{ p.nombre !== p.etiqueta ? `${p.etiqueta} (${p.nombre})` : p.etiqueta }}</td>
          <td>{{ formato(p.valor) }}</td>
          <td>{{ parte(p.valor) }}</td>
          <td>{{ p.detalle ?? '' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { parte as parteDe, porciento } from '@/core/tablero/formato'
import { anchoCifras, anguloDe, arco, cunaEn, cunas, lectura, lugarRotulo, nivel, ordenar, OTROS, REGLAS, TODAS, type Nivel, type Porcion } from '@/core/tablero/porciones'
import FilaPorcion from './FilaPorcion.vue'

const props = withDefaults(
  defineProps<{
    porciones: Porcion[]
    formato: (v: number) => string
    /** Lo que dice el centro bajo el total. */
    totalRotulo: string
    /** La cola de cuñas angostas va a "Otros" (`REGLAS`); sin agrupar, todas tienen cuña. */
    agrupa?: boolean
    /** Categorías fijas (canales, operaciones): sin puesto, y las que están en cero siguen al final de la lista. */
    fijo?: boolean
    /** Cómo se llaman las porciones en plural ("usuarios"). */
    nombreResto?: string
    titulo: string
  }>(),
  { agrupa: false, fijo: false, nombreResto: 'porciones' },
)

/** Radio del lienzo (unidades del viewBox) y del hueco. */
const R = 100
const INTERIOR = 62
/** La cuña activa crece hacia afuera: las demás quedan este tanto más adentro. */
const REALCE = 4
/** Ángulo en el que se puede tocar una cuña, por angosta que sea (8°: ~13 px de arco en el borde de una dona de 15 rem). */
const TOQUE = (8 * Math.PI) / 180
/**
 * Un color por puesto en el nivel, en todas las vistas: la mayor siempre
 * `--cat-1`, la segunda `--cat-2`… (la paleta categórica del tablero, una por
 * cuña propia: `REGLAS.maximo`); "Otros" y lo que agrupa, gris. En ese orden
 * cada par vecino pasa la prueba de daltonismo, también el que cierra la vuelta.
 */
const PALETA = ['var(--cat-1)', 'var(--cat-2)', 'var(--cat-3)', 'var(--cat-4)', 'var(--cat-5)', 'var(--cat-6)']
/** Tamaño de letra del porcentaje en la cuña (unidades del viewBox: 12 px en la dona de 15 rem). */
const LETRA = 10

const reglas = props.agrupa ? REGLAS : TODAS
const niveles = ref<Nivel[]>([nivel(props.porciones, reglas)])
/** Cifras nuevas (la actualización de cada 5 minutos): se rehacen los niveles sin cerrar los que estaban abiertos. */
watch(
  () => props.porciones,
  (p) => {
    const nuevos = [nivel(p, reglas)]
    while (nuevos.length < niveles.value.length && nuevos.at(-1)!.resto.length) nuevos.push(nivel(nuevos.at(-1)!.resto, reglas))
    niveles.value = nuevos
  },
)
const actual = computed(() => niveles.value[niveles.value.length - 1]!)
const total = computed(() => niveles.value[0]!.total)
const lista = computed(() => cunas(actual.value))
/** Las categorías fijas que están en cero: sin cuña, al final de la lista del primer nivel. */
const ceros = computed(() => (props.fijo && niveles.value.length === 1 ? props.porciones.filter((p) => p.valor <= 0) : []))
const todas = computed(() => [...ordenar(props.porciones), ...(props.fijo ? props.porciones.filter((p) => p.valor <= 0) : [])])
const lecturaTexto = computed(() => (props.agrupa && !props.fijo ? lectura(props.porciones, props.nombreResto) : null))
const mayor = computed(() => Math.max(1, ...props.porciones.map((p) => p.valor)))
const propias = computed(() => actual.value.porciones)
const resto = computed(() => actual.value.resto)
const valorOtros = computed(() => resto.value.reduce((s, p) => s + p.valor, 0))
/** Puesto del primero del nivel en el orden general. */
const puestoBase = computed(() => niveles.value.slice(0, -1).reduce((s, n) => s + n.porciones.length, 0))
const enResto = computed(() => new Set(resto.value.map((p) => p.clave)))

const nombreNivel = (i: number) => (i === 0 ? 'Todos' : i === 1 ? 'Otros' : `Otros (${i})`)
/** Parte del total general (no del nivel): la misma escala en todos los niveles. */
const parte = (v: number) => parteDe(v / total.value)

function color(clave: string): string {
  if (clave === OTROS || enResto.value.has(clave)) return 'var(--dona-otros)'
  return PALETA[propias.value.findIndex((p) => p.clave === clave)] ?? 'transparent'
}
/** El texto sobre la cuña: blanco o casi negro según el fondo (`--cat-N-tinta`); sobre "Otros", el del texto. */
function tinta(clave: string): string {
  const i = propias.value.findIndex((p) => p.clave === clave)
  return i < 0 ? 'var(--p-text-color)' : `var(--cat-${i + 1}-tinta)`
}

/** El porcentaje del total en las cuñas donde cabe entero; en las demás lo dicen la lista y el centro. */
const rotulos = computed(() =>
  lista.value.flatMap((c) => {
    const texto = parte(c.clave === OTROS ? valorOtros.value : (propias.value.find((p) => p.clave === c.clave)?.valor ?? 0))
    const lugar = lugarRotulo(c, anchoCifras(texto) * LETRA, LETRA * 0.8, R - REALCE, INTERIOR)
    return lugar ? [{ clave: c.clave, texto, ...lugar }] : []
  }),
)

/** Lo que se resalta: una fila o una cuña; una fila de lo que agrupa "Otros" resalta "Otros". */
const activa = ref<string | null>(null)
const cunaActiva = computed(() => (activa.value !== null && enResto.value.has(activa.value) ? OTROS : activa.value))
const enfocada = computed<Porcion | null>(() => {
  const a = activa.value
  if (a === null) return null
  if (a === OTROS) return resto.value.length ? { clave: OTROS, etiqueta: `Otros · ${resto.value.length} ${props.nombreResto}`, nombre: 'Otros', valor: valorOtros.value } : null
  return propias.value.find((p) => p.clave === a) ?? resto.value.find((p) => p.clave === a) ?? null
})

const lienzo = ref<HTMLElement | null>(null)
/** La cuña bajo el puntero (por ángulo y dentro del anillo, con margen). */
function cunaBajo(e: PointerEvent | MouseEvent) {
  const caja = lienzo.value?.getBoundingClientRect()
  if (!caja?.width) return null
  const escala = (2 * R) / caja.width
  const x = (e.clientX - caja.left) * escala - R
  const y = (e.clientY - caja.top) * escala - R
  const r = Math.hypot(x, y)
  if (r < INTERIOR - 8 || r > R + 12) return null
  return cunaEn(lista.value, anguloDe(x, y), TOQUE)
}
function alMover(e: PointerEvent) {
  activa.value = cunaBajo(e)?.clave ?? null
}
function alTocar(e: MouseEvent) {
  if (cunaBajo(e)?.clave === OTROS) abrir()
}
/** Con el dedo no hay "pasar por encima": al levantarlo, lo tocado queda elegido. */
function salir(e: PointerEvent) {
  if (e.pointerType !== 'touch') activa.value = null
}

/** Teclado (una sola parada de Tab): flechas recorren las cuñas, Enter abre "Otros", Escape vuelve. */
function alTeclado(e: KeyboardEvent) {
  const claves = lista.value.map((c) => c.clave)
  const i = claves.indexOf(cunaActiva.value ?? '')
  const ir = (j: number) => {
    e.preventDefault()
    activa.value = claves[(j + claves.length) % claves.length] ?? null
  }
  if (!claves.length) return
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') ir(i + 1)
  else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ir(i < 0 ? claves.length - 1 : i - 1)
  else if (e.key === 'Home') ir(0)
  else if (e.key === 'End') ir(claves.length - 1)
  else if ((e.key === 'Enter' || e.key === ' ') && cunaActiva.value === OTROS) {
    e.preventDefault()
    abrir()
  } else if (e.key === 'Escape' && niveles.value.length > 1) {
    e.preventDefault()
    volver(niveles.value.length - 2)
  }
}

function abrir() {
  if (!resto.value.length) return
  niveles.value = [...niveles.value, nivel(resto.value, reglas)]
  activa.value = null
  arriba()
}
function volver(i: number) {
  niveles.value = niveles.value.slice(0, i + 1)
  activa.value = null
  arriba()
}
/** La lista de otro nivel empieza arriba. */
const listaEl = ref<HTMLElement | null>(null)
function arriba() {
  if (listaEl.value) listaEl.value.scrollTop = 0
}

const resumen = computed(() => {
  const partes = propias.value.map((p) => `${p.etiqueta} ${props.formato(p.valor)} (${parte(p.valor)})`)
  if (resto.value.length) partes.push(`Otros, ${resto.value.length} ${props.nombreResto}, ${props.formato(valorOtros.value)} (${parte(valorOtros.value)})`)
  return `${props.titulo}: ${partes.join(', ')}`
})
</script>

<style scoped>
.dona {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.camino {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.5rem;
  font-size: 0.8125rem;
}
.paso {
  background: none;
  border: 0;
  padding: 0.25rem 0;
  color: var(--p-primary-color);
  cursor: pointer;
}
.paso:hover {
  text-decoration: underline;
}
.cuerpo {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}
.lienzo {
  position: relative;
  width: min(100%, 15rem);
  aspect-ratio: 1;
  margin-inline: auto;
  touch-action: manipulation;
  border-radius: 50%;
}
.lienzo:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 4px;
}
/* El trazo del color de la tarjeta es la separación entre cuñas. */
.cuna {
  stroke: var(--p-content-background);
  stroke-width: 1.5;
  stroke-linejoin: round;
  transition: opacity 0.15s;
}
.rotulo {
  text-anchor: middle;
  dominant-baseline: central;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  pointer-events: none;
  transition: opacity 0.15s;
}
@media (prefers-reduced-motion: reduce) {
  .cuna,
  .rotulo {
    transition: none;
  }
}
.cuna.otros {
  cursor: pointer;
}
.cuna.apagada,
.rotulo.apagada {
  opacity: 0.35;
}
.centro {
  position: absolute;
  inset: 22%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  pointer-events: none;
  min-width: 0;
}
.cifra {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
}
.lista {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}
.lista.larga {
  max-height: 19rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-right: 0.25rem;
}
.otros-boton {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  gap: 0.5rem;
  align-items: start;
  width: 100%;
  min-height: 2.75rem;
  margin-top: 0.25rem;
  padding: 0.5rem;
  border: 1px dashed var(--p-content-border-color);
  border-radius: 0.375rem;
  background: none;
  color: inherit;
  font-size: 0.875rem;
  cursor: pointer;
}
.otros-boton.activa,
.otros-boton:hover {
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.08));
}
.muestra {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  margin-top: 0.3rem;
}
@container (min-width: 34rem) {
  .cuerpo {
    grid-template-columns: 15rem minmax(0, 1fr);
    gap: 1.5rem;
  }
  .lienzo {
    width: 15rem;
  }
}
</style>
