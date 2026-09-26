<!--
  Editor del croquis de un bus: la versión web del "lienzo" del legado.

  - Cuatro pilas infinitas (chofer, puerta, asiento A, asiento B): se arrastra
    una pieza a una celda, o se hace clic en la pila (pincel) y luego en las
    celdas vacías.
  - Arrastrar un elemento lo mueve (sobre otro, los intercambia); soltarlo
    fuera del bus lo quita. Los asientos con boletos vendidos no se quitan.
  - Una rejilla por planta (hasta dos) de alto ajustable.
  - Teclado: flechas mueven lo seleccionado, Supr lo quita, Esc suelta el
    pincel, Ctrl+Z / Ctrl+Shift+Z deshacen y rehacen.

  Expone `croquis()` (elementos a guardar), `problemas` y `modificado`.
-->
<template>
  <div
    ref="raiz"
    class="ed @container"
    tabindex="0"
    :class="{ 'ed--arrastrando': !!arrastrando, 'ed--pincel': !!pincel }"
    @keydown="onKeydown"
  >
    <!-- Barra de herramientas -->
    <div class="ed-toolbar">
      <div class="flex flex-wrap items-center gap-1">
        <Button
          v-if="plantas.length < PLANTAS_MAX"
          size="small"
          text
          label="Planta alta"
          title="Agregar la planta alta (bus de dos pisos)"
          @click="aplicar(agregarPlanta(estado))"
        >
          <template #icon><icon name="stack-push" class="mr-1" /></template>
        </Button>
        <Button
          size="small"
          text
          title="Deshacer (Ctrl+Z)"
          aria-label="Deshacer"
          :disabled="!pasado.length"
          @click="deshacer"
        >
          <template #icon><icon name="arrow-back-up" /></template>
        </Button>
        <Button
          size="small"
          text
          title="Rehacer (Ctrl+Shift+Z)"
          aria-label="Rehacer"
          :disabled="!futuro.length"
          @click="rehacer"
        >
          <template #icon><icon name="arrow-forward-up" /></template>
        </Button>
        <Button
          size="small"
          text
          label="Numeración"
          aria-haspopup="true"
          :disabled="!resumenActual.asientos"
          @click="menuNumeracion?.toggle($event)"
        >
          <template #icon><icon name="list-numbers" class="mr-1" /></template>
        </Button>
        <Menu ref="menuNumeracion" :model="accionesNumeracion" popup>
          <template #itemicon="{ item }"><icon :name="String(item.icon)" class="mr-2" /></template>
        </Menu>
        <Button size="small" text label="Usar plantilla" @click="dialogoPlantilla = true">
          <template #icon><icon name="copy" class="mr-1" /></template>
        </Button>
        <Button
          size="small"
          text
          severity="danger"
          label="Vaciar"
          :disabled="!estado.elementos.length"
          @click="pedirVaciar"
        >
          <template #icon><icon name="eraser" class="mr-1" /></template>
        </Button>
      </div>
      <Tag v-if="modificado" severity="warn" value="Croquis sin guardar" class="text-xs" />
    </div>

    <div class="ed-body">
      <!-- Pilas -->
      <div class="ed-pilas" role="toolbar" aria-label="Piezas del croquis">
        <button
          v-for="pila in pilas"
          :key="pila.key"
          type="button"
          class="ed-pila"
          :class="{ 'ed-pila--activa': esPincel(pila.pieza) }"
          :aria-pressed="esPincel(pila.pieza)"
          :title="`${pila.titulo}: arrastrá a una celda, o clic para colocar varias`"
          @pointerdown="iniciar($event, { origen: 'pila', pieza: pila.pieza })"
          @click="onClickPila(pila.pieza)"
        >
          <span class="ed-pila__stack" aria-hidden="true">
            <span class="ed-pila__layer" />
            <span class="ed-pila__layer" />
            <span class="ed-pila__glyph">
              <SeatGlyph
                v-if="pila.pieza.tipo === 'asiento'"
                :clase="pila.pieza.clase"
                :numero="siguiente"
              />
              <SignalGlyph v-else :tipo="pila.pieza.tipo" />
            </span>
          </span>
          <span class="ed-pila__text">
            <span class="ed-pila__title">{{ pila.titulo }}</span>
            <span class="ed-pila__detail">{{ pila.detalle }}</span>
          </span>
        </button>
      </div>

      <!-- Lienzo -->
      <div class="ed-canvas" :class="{ 'ed-canvas--quitar': quitandoAlSoltar }">
        <BusMap
          :elementos="estado.elementos"
          :plantas="plantas"
          :rejilla="rejilla"
          celdas-vacias
          etiquetas
          tamano="md"
        >
          <template #planta-cabecera="{ planta, nombre }">
            <span class="font-semibold">{{ nombre }}</span>
            <span class="text-muted-color">{{ asientosEn(planta) }} asientos</span>
            <Button
              v-if="planta !== 1 && !hayElementosEn(planta)"
              size="small"
              text
              severity="secondary"
              class="ml-auto p-0!"
              title="Quitar la planta (está vacía)"
              aria-label="Quitar planta"
              @click="aplicar(quitarPlanta(estado, planta))"
            >
              <template #icon><icon name="x" sm /></template>
            </Button>
          </template>

          <template #celda="{ elemento, clave, planta, fila, columna }">
            <div
              class="ed-cell"
              :class="{
                'ed-cell--vacia': !elemento,
                'ed-cell--sel': elemento && seleccion === uidDe(elemento),
                'ed-cell--activa': !elemento && claveActiva === clave,
                'ed-cell--over': destino === clave,
                'ed-cell--error': celdasConError.has(clave),
                'ed-cell--origen': elemento && arrastrandoUid === uidDe(elemento),
              }"
              @pointerdown="elemento && iniciar($event, { origen: 'celda', uid: uidDe(elemento) })"
              @click="onClickCelda({ planta, fila, columna }, elemento)"
            >
              <template v-if="elemento">
                <SeatGlyph
                  v-if="elemento.tipo === 'asiento'"
                  :clase="elemento.clase"
                  :numero="elemento.numero"
                />
                <SignalGlyph
                  v-else
                  :tipo="elemento.tipo"
                  :espejo="columna <= estado.columnas / 2"
                />
                <span
                  v-if="elemento.tipo === 'asiento' && elemento.conBoletos"
                  class="ed-cell__lock"
                  title="Tiene boletos vendidos"
                >
                  <icon name="lock" size=".6rem" />
                </span>
              </template>
              <span v-else-if="pincel" class="ed-cell__preview" aria-hidden="true">
                <SeatGlyph
                  v-if="pincel.tipo === 'asiento'"
                  :clase="pincel.clase"
                  :numero="siguiente"
                />
                <SignalGlyph v-else :tipo="pincel.tipo" />
              </span>
            </div>
          </template>

          <template #planta-pie="{ planta }">
            <div class="ed-rows">
              <div class="ed-stepper" role="group" :aria-label="`Filas de la planta ${planta}`">
                <button
                  type="button"
                  title="Quitar la última fila (si está vacía)"
                  aria-label="Quitar la última fila"
                  :disabled="!puedeQuitarUltimaFila(planta)"
                  @click="aplicar(quitarFila(estado, planta))"
                >
                  −
                </button>
                <span>{{ estado.filas[planta] }} filas</span>
                <button
                  type="button"
                  title="Agregar una fila al final"
                  aria-label="Agregar una fila"
                  :disabled="estado.filas[planta]! >= FILAS_MAX"
                  @click="aplicar(agregarFila(estado, planta))"
                >
                  +
                </button>
              </div>
              <Button
                size="small"
                text
                severity="secondary"
                label="Rellenar"
                :title="`Llenar las celdas libres con asientos clase ${claseRelleno(planta)} (2 + pasillo + 2)`"
                @click="aplicar(rellenar(estado, planta, claseRelleno(planta)))"
              />
            </div>
          </template>
        </BusMap>
      </div>

      <!-- Inspector -->
      <aside class="ed-inspector" aria-live="polite">
        <template v-if="activa">
          <header class="ed-inspector__head">
            <span class="ed-inspector__kind">{{ tituloActiva }}</span>
            <span class="text-xs text-muted-color">
              {{ plantas.length > 1 ? (activa.planta === 1 ? 'Planta baja' : 'Planta alta') : '' }}
              Fila {{ activa.fila }} · Columna {{ activa.columna }}
            </span>
          </header>

          <template v-if="elementoSel && elementoSel.tipo === 'asiento'">
            <label class="ed-field">
              <span>Número</span>
              <InputNumber
                :model-value="elementoSel.numero"
                :min="1"
                :max="NUMERO_MAX"
                :use-grouping="false"
                size="small"
                fluid
                :invalid="numeroRepetido"
                @update:model-value="(n: number | null) => n && cambiarAsiento({ numero: n })"
              />
              <small v-if="numeroRepetido" class="text-red-500">Número repetido</small>
            </label>
            <div class="ed-field">
              <span>Clase</span>
              <SelectButton
                :model-value="elementoSel.clase"
                :options="CLASES"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                size="small"
                @update:model-value="(clase: ClaseAsiento) => cambiarAsiento({ clase })"
              />
            </div>
            <Message
              v-if="elementoSel.conBoletos"
              severity="secondary"
              size="small"
              :closable="false"
            >
              Tiene boletos vendidos: se puede mover o renumerar, pero no quitar.
            </Message>
          </template>

          <p v-else-if="!elementoSel" class="text-sm text-muted-color">
            Celda vacía. Arrastrá una pieza hasta aquí o elegí una pila y hacé clic.
          </p>

          <div class="ed-inspector__actions">
            <Button
              v-if="elementoSel"
              size="small"
              severity="danger"
              outlined
              label="Quitar"
              :disabled="elementoSel.tipo === 'asiento' && elementoSel.conBoletos"
              @click="quitarSeleccion"
            >
              <template #icon><icon name="trash" class="mr-1" /></template>
            </Button>
            <Button
              size="small"
              text
              severity="secondary"
              label="Insertar fila antes"
              :disabled="estado.filas[activa.planta]! >= FILAS_MAX"
              @click="aplicar(insertarFila(estado, activa.planta, activa.fila))"
            />
            <Button
              size="small"
              text
              severity="secondary"
              label="Eliminar fila"
              :disabled="!puedeEliminarFila(activa.planta, activa.fila)"
              title="Solo filas vacías"
              @click="eliminarFilaActiva"
            />
          </div>
        </template>

        <template v-else>
          <header class="ed-inspector__head">
            <span class="ed-inspector__kind">Cómo se arma</span>
          </header>
          <ul class="ed-tips">
            <li><icon name="hand-grab" /> Arrastrá una pieza de las pilas a una celda.</li>
            <li>
              <icon name="brush" /> Clic en una pila para colocar varias seguidas; Esc para
              soltarla.
            </li>
            <li>
              <icon name="arrows-exchange" /> Arrastrá un elemento para moverlo; sobre otro, se
              intercambian.
            </li>
            <li><icon name="trash-x" /> Soltarlo fuera del bus lo quita.</li>
            <li><icon name="keyboard" /> Flechas mueven, Supr quita, Ctrl+Z deshace.</li>
          </ul>
        </template>
      </aside>
    </div>

    <!-- Estado -->
    <footer class="ed-footer">
      <div class="ed-stats">
        <span class="ed-stat"
          ><strong>{{ resumenActual.asientos }}</strong> asientos</span
        >
        <span class="ed-stat ed-stat--a"
          ><strong>{{ resumenActual.porClase.A }}</strong> clase A</span
        >
        <span class="ed-stat ed-stat--b"
          ><strong>{{ resumenActual.porClase.B }}</strong> clase B</span
        >
        <span class="ed-stat"
          ><strong>{{ plantas.length }}</strong>
          {{ plantas.length === 1 ? 'planta' : 'plantas' }}</span
        >
        <span class="ed-stat"
          ><strong>{{ resumenActual.puertas }}</strong>
          {{ resumenActual.puertas === 1 ? 'puerta' : 'puertas' }}</span
        >
        <span class="ed-stat" :class="{ 'ed-stat--falta': !resumenActual.chofer }">
          <icon :name="resumenActual.chofer ? 'circle-check' : 'circle-dashed'" sm />
          chofer
        </span>
      </div>
      <ul v-if="problemas.length" class="ed-problemas">
        <li
          v-for="(problema, i) in problemas"
          :key="i"
          :class="problema.nivel === 'error' ? 'text-red-600' : 'text-amber-600'"
        >
          <icon :name="problema.nivel === 'error' ? 'alert-octagon' : 'alert-triangle'" sm />
          {{ problema.mensaje }}
        </li>
      </ul>
    </footer>

    <!-- Fantasma del arrastre -->
    <Teleport to="body">
      <div
        v-if="arrastrando"
        class="ed-ghost"
        :class="{ 'ed-ghost--quitar': quitandoAlSoltar }"
        :style="{ transform: `translate(${puntero.x}px, ${puntero.y}px)` }"
      >
        <SeatGlyph
          v-if="piezaArrastrada?.tipo === 'asiento'"
          :clase="piezaArrastrada.clase"
          :numero="numeroArrastrado"
        />
        <SignalGlyph v-else-if="piezaArrastrada" :tipo="piezaArrastrada.tipo" />
        <span v-if="quitandoAlSoltar" class="ed-ghost__badge">Quitar</span>
      </div>
    </Teleport>

    <PlantillaDialog
      v-model:visible="dialogoPlantilla"
      :excluir-bus-id="busId"
      @elegir="usarPlantilla"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import {
  celda as claveDe,
  esAsiento,
  FILAS_MAX,
  NUMERO_MAX,
  PLANTAS_MAX,
  resumen,
  siguienteNumero,
  validar,
} from '@/core/croquis/model'
import type {
  AsientoCroquis,
  ClaseAsiento,
  ElementoCroquis,
  PlantillaCroquis,
  Posicion,
} from '@/core/croquis/types'
import { notify } from '@/core/notify'
import BusMap from '@/shared/bus-map/BusMap.vue'
import SeatGlyph from '@/shared/bus-map/SeatGlyph.vue'
import SignalGlyph from '@/shared/bus-map/SignalGlyph.vue'
import {
  aCroquis,
  actualizarAsiento,
  agregarFila,
  agregarPlanta,
  aplicarPlantilla,
  buscar,
  colocar,
  compactarNumeracion,
  crearEstado,
  elementoEn,
  eliminarFila,
  insertarFila,
  mover,
  numerarPorPosicion,
  plantasEditor,
  quitar,
  quitarFila,
  quitarPlanta,
  rellenar,
  vaciar,
  type ElementoEditor,
  type EstadoEditor,
  type Pieza,
} from './editor'
import PlantillaDialog from './PlantillaDialog.vue'
import { usePointerDrag } from './usePointerDrag'

const props = defineProps<{
  inicial: readonly ElementoCroquis[]
  /** Bus en edición (se excluye de las plantillas). */
  busId?: number | null
}>()

const CLASES = [
  { label: 'A', value: 'A' },
  { label: 'B · reclinable', value: 'B' },
]
const HISTORIAL_MAX = 80

// Estado + historial ---------------------------------------------------------
const estado = shallowRef<EstadoEditor>(crearEstado(props.inicial))
const pasado = shallowRef<EstadoEditor[]>([])
const futuro = shallowRef<EstadoEditor[]>([])
const firmaInicial = shallowRef(JSON.stringify(aCroquis(estado.value)))

watch(
  () => props.inicial,
  (inicial) => {
    estado.value = crearEstado(inicial)
    pasado.value = []
    futuro.value = []
    firmaInicial.value = JSON.stringify(aCroquis(estado.value))
    seleccion.value = null
    claveActiva.value = null
  },
)

function aplicar(nuevo: EstadoEditor) {
  if (nuevo === estado.value) return
  pasado.value = [...pasado.value, estado.value].slice(-HISTORIAL_MAX)
  futuro.value = []
  estado.value = nuevo
}

function deshacer() {
  const anterior = pasado.value[pasado.value.length - 1]
  if (!anterior) return
  futuro.value = [estado.value, ...futuro.value]
  pasado.value = pasado.value.slice(0, -1)
  estado.value = anterior
}

function rehacer() {
  const siguienteEstado = futuro.value[0]
  if (!siguienteEstado) return
  pasado.value = [...pasado.value, estado.value]
  futuro.value = futuro.value.slice(1)
  estado.value = siguienteEstado
}

const croquis = computed(() => aCroquis(estado.value))
const modificado = computed(() => JSON.stringify(croquis.value) !== firmaInicial.value)
const problemas = computed(() => validar(croquis.value))
const celdasConError = computed(
  () => new Set(problemas.value.filter((p) => p.nivel === 'error').flatMap((p) => p.celdas)),
)
const resumenActual = computed(() => resumen(estado.value.elementos))
const siguiente = computed(() => siguienteNumero(estado.value.elementos))
const plantas = computed(() => plantasEditor(estado.value))
const rejilla = computed(() =>
  Object.fromEntries(
    plantas.value.map((p) => [
      p,
      { filas: estado.value.filas[p]!, columnas: estado.value.columnas },
    ]),
  ),
)

const asientosEn = (planta: number) =>
  estado.value.elementos.filter((e) => e.planta === planta && esAsiento(e)).length
const hayElementosEn = (planta: number) => estado.value.elementos.some((e) => e.planta === planta)
const puedeQuitarUltimaFila = (planta: number) => quitarFila(estado.value, planta) !== estado.value
const puedeEliminarFila = (planta: number, fila: number) =>
  eliminarFila(estado.value, planta, fila) !== estado.value
/** En un bus de dos pisos la planta baja es la de clase B. */
const claseRelleno = (planta: number): ClaseAsiento =>
  plantas.value.length > 1 && planta === 1 ? 'B' : 'A'

// Pilas y pincel ---------------------------------------------------------------
const pincel = ref<Pieza | null>(null)

const pilas = computed(() => [
  {
    key: 'chofer',
    pieza: { tipo: 'chofer' } as Pieza,
    titulo: 'Chofer',
    detalle: resumenActual.value.chofer ? 'Colocado · uno por bus' : 'Uno por bus',
  },
  {
    key: 'puerta',
    pieza: { tipo: 'puerta' } as Pieza,
    titulo: 'Puerta',
    detalle: `${resumenActual.value.puertas} en el croquis`,
  },
  {
    key: 'A',
    pieza: { tipo: 'asiento', clase: 'A' } as Pieza,
    titulo: 'Asiento A',
    detalle: `Sigue el Nº ${siguiente.value}`,
  },
  {
    key: 'B',
    pieza: { tipo: 'asiento', clase: 'B' } as Pieza,
    titulo: 'Asiento B',
    detalle: 'Reclinable · planta baja',
  },
])

const mismaPieza = (a: Pieza, b: Pieza) =>
  a.tipo === b.tipo && (a.tipo !== 'asiento' || a.clase === (b as typeof a).clase)
const esPincel = (pieza: Pieza) => !!pincel.value && mismaPieza(pincel.value, pieza)

function onClickPila(pieza: Pieza) {
  if (consumirClick()) return
  pincel.value = esPincel(pieza) ? null : pieza
  raiz.value?.focus({ preventScroll: true })
}

// Selección --------------------------------------------------------------------
const raiz = ref<HTMLElement | null>(null)
const seleccion = ref<string | null>(null)
/** Celda enfocada (con o sin elemento): para el inspector y las filas. */
const claveActiva = ref<string | null>(null)

const uidDe = (e: ElementoCroquis) => (e as ElementoEditor).uid
const elementoSel = computed(() => buscar(estado.value, seleccion.value))
const activa = computed<Posicion | null>(() => {
  if (elementoSel.value) return elementoSel.value
  if (!claveActiva.value) return null
  const [planta, fila, columna] = claveActiva.value.split(':').map(Number) as [
    number,
    number,
    number,
  ]
  const p = { planta, fila, columna }
  return estado.value.filas[planta] !== undefined && fila <= estado.value.filas[planta]! ? p : null
})
const tituloActiva = computed(() => {
  const e = elementoSel.value
  if (!e) return 'Celda vacía'
  if (e.tipo === 'asiento') return `Asiento ${e.numero}`
  return e.tipo === 'chofer' ? 'Chofer' : 'Puerta'
})
const numeroRepetido = computed(() => {
  const e = elementoSel.value
  return (
    !!e &&
    esAsiento(e) &&
    estado.value.elementos.some((o) => o.uid !== e.uid && esAsiento(o) && o.numero === e.numero)
  )
})

function seleccionar(p: Posicion, elemento: ElementoCroquis | null) {
  claveActiva.value = claveDe(p)
  seleccion.value = elemento ? uidDe(elemento) : null
}

function onClickCelda(p: Posicion, elemento: ElementoCroquis | null) {
  if (consumirClick()) return
  raiz.value?.focus({ preventScroll: true })
  if (!elemento && pincel.value) {
    const { estado: nuevo, uid } = colocar(estado.value, pincel.value, p)
    aplicar(nuevo)
    claveActiva.value = claveDe(p)
    seleccion.value = uid
    return
  }
  if (elemento && seleccion.value === uidDe(elemento)) {
    seleccion.value = null
    claveActiva.value = null
    return
  }
  seleccionar(p, elemento)
}

function cambiarAsiento(cambios: Partial<Pick<AsientoCroquis, 'numero' | 'clase'>>) {
  if (seleccion.value) aplicar(actualizarAsiento(estado.value, seleccion.value, cambios))
}

function quitarUid(uid: string) {
  const elemento = buscar(estado.value, uid)
  if (elemento && esAsiento(elemento) && elemento.conBoletos) {
    notify.warning(`El asiento ${elemento.numero} tiene boletos vendidos: no se puede quitar.`)
    return
  }
  aplicar(quitar(estado.value, uid))
  if (seleccion.value === uid) seleccion.value = null
}

const quitarSeleccion = () => seleccion.value && quitarUid(seleccion.value)

function eliminarFilaActiva() {
  const p = activa.value
  if (!p) return
  aplicar(eliminarFila(estado.value, p.planta, p.fila))
  claveActiva.value = null
}

// Arrastre ---------------------------------------------------------------------
type Carga = { origen: 'pila'; pieza: Pieza } | { origen: 'celda'; uid: string }

const { arrastrando, destino, puntero, iniciar, consumirClick } = usePointerDrag<Carga>({
  soltar(carga, clave) {
    if (!clave) {
      if (carga.origen === 'celda') quitarUid(carga.uid)
      return
    }
    const [planta, fila, columna] = clave.split(':').map(Number) as [number, number, number]
    const p = { planta, fila, columna }
    if (carga.origen === 'pila') {
      const { estado: nuevo, uid } = colocar(estado.value, carga.pieza, p)
      if (nuevo === estado.value) return
      aplicar(nuevo)
      claveActiva.value = clave
      seleccion.value = uid
    } else {
      aplicar(mover(estado.value, carga.uid, p))
      claveActiva.value = clave
      seleccion.value = carga.uid
    }
  },
})

const arrastrandoUid = computed(() =>
  arrastrando.value?.origen === 'celda' ? arrastrando.value.uid : null,
)
const elementoArrastrado = computed(() => buscar(estado.value, arrastrandoUid.value))
const piezaArrastrada = computed<Pieza | null>(() => {
  const carga = arrastrando.value
  if (!carga) return null
  if (carga.origen === 'pila') return carga.pieza
  const e = elementoArrastrado.value
  if (!e) return null
  return esAsiento(e) ? { tipo: 'asiento', clase: e.clase } : { tipo: e.tipo }
})
const numeroArrastrado = computed(() => {
  const e = elementoArrastrado.value
  return e && esAsiento(e) ? e.numero : siguiente.value
})
const quitandoAlSoltar = computed(
  () => arrastrando.value?.origen === 'celda' && destino.value === null,
)

// Teclado ------------------------------------------------------------------------
const DIRECCIONES: Record<string, [number, number]> = {
  ArrowUp: [-1, 0],
  ArrowDown: [1, 0],
  ArrowLeft: [0, -1],
  ArrowRight: [0, 1],
}

function onKeydown(event: KeyboardEvent) {
  const objetivo = event.target as HTMLElement
  if (objetivo.closest('input, textarea, [contenteditable="true"], .p-selectbutton')) return
  const mod = event.ctrlKey || event.metaKey

  if (mod && event.key.toLowerCase() === 'z') {
    event.preventDefault()
    if (event.shiftKey) rehacer()
    else deshacer()
    return
  }
  if (mod && event.key.toLowerCase() === 'y') {
    event.preventDefault()
    rehacer()
    return
  }
  if (event.key === 'Escape') {
    pincel.value = null
    seleccion.value = null
    claveActiva.value = null
    return
  }
  if ((event.key === 'Delete' || event.key === 'Backspace') && seleccion.value) {
    event.preventDefault()
    quitarSeleccion()
    return
  }
  const delta = DIRECCIONES[event.key]
  const p = activa.value
  if (!delta || !p) return
  event.preventDefault()
  const destinoPos = { planta: p.planta, fila: p.fila + delta[0], columna: p.columna + delta[1] }
  if (
    destinoPos.fila < 1 ||
    destinoPos.columna < 1 ||
    destinoPos.fila > estado.value.filas[p.planta]! ||
    destinoPos.columna > estado.value.columnas
  )
    return
  if (seleccion.value) aplicar(mover(estado.value, seleccion.value, destinoPos))
  else seleccionar(destinoPos, elementoEn(estado.value, destinoPos))
  claveActiva.value = claveDe(destinoPos)
}

// Acciones -----------------------------------------------------------------------
const menuNumeracion = ref<{ toggle: (event: Event) => void } | null>(null)
const accionesNumeracion = [
  {
    label: 'Cerrar huecos (1…N, mismo orden)',
    icon: 'sort-ascending-numbers',
    command: () => aplicar(compactarNumeracion(estado.value)),
  },
  {
    label: 'Numerar por posición (planta, fila, columna)',
    icon: 'arrows-sort',
    command: () => aplicar(numerarPorPosicion(estado.value)),
  },
]

const confirm = useConfirm()
function pedirVaciar() {
  confirm.require({
    header: 'Vaciar el croquis',
    message:
      'Se quitan todos los elementos (salvo los asientos con boletos vendidos). Se puede deshacer.',
    acceptProps: { label: 'Vaciar', severity: 'danger' },
    rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
    accept: () => {
      aplicar(vaciar(estado.value))
      seleccion.value = null
    },
  })
}

const dialogoPlantilla = ref(false)

function usarPlantilla(plantilla: PlantillaCroquis) {
  const { estado: nuevo, conBoletosPerdidos } = aplicarPlantilla(estado.value, plantilla.elementos)
  aplicar(nuevo)
  seleccion.value = null
  claveActiva.value = null
  const codigos = plantilla.buses
    .slice(0, 3)
    .map((b) => b.codigo)
    .join(', ')
  notify.success(`Croquis tomado de ${codigos}${plantilla.buses.length > 3 ? '…' : ''}`)
  if (conBoletosPerdidos.length) {
    notify.warning(
      `La plantilla no tiene los asientos ${conBoletosPerdidos.join(', ')}, que tienen boletos vendidos: no se podrá guardar así.`,
    )
  }
}

defineExpose({
  croquis: () => croquis.value,
  problemas,
  modificado,
})
</script>

<style scoped>
.ed {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  outline: none;
}
.ed-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.25rem 0.4rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.6rem;
  background: var(--p-content-background);
}
.ed-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.9rem;
  align-items: start;
}
@container (min-width: 44rem) {
  .ed-body {
    grid-template-columns: minmax(0, 1fr) 15rem;
  }
  .ed-pilas {
    grid-column: 1 / -1;
  }
  .ed-inspector {
    position: sticky;
    top: 0.75rem;
  }
}
@container (min-width: 66rem) {
  .ed-body {
    grid-template-columns: 11.5rem minmax(0, 1fr) 16rem;
  }
  .ed-pilas {
    grid-column: auto;
    flex-direction: column;
    position: sticky;
    top: 0.75rem;
  }
}

/* Pilas --------------------------------------------------------------------- */
.ed-pilas {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}
.ed-pila {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex: 1 1 10rem;
  padding: 0.55rem 0.65rem 0.55rem 0.55rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.7rem;
  background: var(--p-content-background);
  text-align: left;
  cursor: grab;
  touch-action: none;
  user-select: none;
  transition:
    border-color 0.15s,
    box-shadow 0.15s,
    background 0.15s;
}
.ed-pila:hover {
  border-color: color-mix(in srgb, var(--p-primary-color) 45%, var(--p-content-border-color));
}
.ed-pila:active {
  cursor: grabbing;
}
.ed-pila--activa {
  border-color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 7%, var(--p-content-background));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-primary-color) 18%, transparent);
}
.ed-pila__stack {
  position: relative;
  flex: none;
  width: 2.6rem;
  height: 2.6rem;
}
.ed-pila__layer,
.ed-pila__glyph {
  position: absolute;
  inset: 0;
  border-radius: 0.55rem;
}
.ed-pila__layer {
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}
.ed-pila__layer:nth-child(1) {
  transform: translate(5px, 5px);
  opacity: 0.55;
}
.ed-pila__layer:nth-child(2) {
  transform: translate(2.5px, 2.5px);
  opacity: 0.8;
}
.ed-pila__glyph {
  display: grid;
  padding: 0.12rem;
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
}
.ed-pila:hover .ed-pila__glyph {
  transform: translate(-1px, -2px);
  transition: transform 0.15s;
}
.ed-pila__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.2;
}
.ed-pila__title {
  font-weight: 600;
  font-size: 0.85rem;
}
.ed-pila__detail {
  font-size: 0.72rem;
  color: var(--p-text-muted-color);
}

/* Lienzo -------------------------------------------------------------------- */
.ed-canvas {
  display: flex;
  justify-content: center;
  padding: 1.1rem 0.75rem 0.5rem;
  border-radius: 0.9rem;
  background:
    radial-gradient(
        circle at 1px 1px,
        color-mix(in srgb, var(--p-text-color) 12%, transparent) 1px,
        transparent 0
      )
      0 0 / 14px 14px,
    color-mix(in srgb, var(--p-text-color) 2%, var(--p-content-background));
  border: 1px solid var(--p-content-border-color);
  overflow-x: auto;
  transition: box-shadow 0.15s;
}
.ed-canvas--quitar {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--c-danger, #ef4444) 35%, transparent) inset;
}
.ed-canvas :deep(.bm-planta__head) {
  align-items: center;
}
.ed-rows {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.25rem 0.5rem;
  font-size: 0.78rem;
}
.ed-stepper {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
}
.ed-stepper span {
  padding-inline: 0.35rem;
  font-variant-numeric: tabular-nums;
}
.ed-stepper button {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  font-size: 1rem;
  line-height: 1;
  color: var(--p-text-color);
}
.ed-stepper button:hover:not(:disabled) {
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
.ed-stepper button:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Celdas del editor ------------------------------------------------------------ */
.ed-cell {
  position: relative;
  display: grid;
  width: 100%;
  height: 100%;
  border-radius: 0.45rem;
  cursor: grab;
  touch-action: none;
  user-select: none;
  transition:
    background 0.12s,
    box-shadow 0.12s,
    opacity 0.12s;
}
.ed-cell--vacia {
  cursor: default;
  border: 1px dashed color-mix(in srgb, var(--p-text-color) 13%, transparent);
}
.ed--pincel .ed-cell--vacia {
  cursor: copy;
}
.ed-cell--vacia:hover {
  background: color-mix(in srgb, var(--p-primary-color) 6%, transparent);
}
.ed-cell--activa {
  border-color: var(--p-primary-color);
  border-style: solid;
}
.ed-cell--sel {
  box-shadow:
    0 0 0 2px var(--p-content-background),
    0 0 0 4px var(--p-primary-color);
}
.ed-cell--over {
  background: color-mix(in srgb, var(--p-primary-color) 16%, transparent);
  box-shadow: 0 0 0 2px var(--p-primary-color) inset;
}
.ed-cell--error {
  box-shadow:
    0 0 0 2px var(--p-content-background),
    0 0 0 4px var(--c-danger, #ef4444);
}
.ed-cell--origen {
  opacity: 0.3;
}
.ed-cell__preview {
  display: grid;
  opacity: 0;
  transition: opacity 0.12s;
  pointer-events: none;
}
.ed-cell--vacia:hover .ed-cell__preview {
  opacity: 0.45;
}
.ed-cell__lock {
  position: absolute;
  right: -0.2rem;
  top: -0.2rem;
  display: grid;
  place-items: center;
  width: 0.95rem;
  height: 0.95rem;
  border-radius: 999px;
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  color: var(--p-text-muted-color);
}

/* Inspector ----------------------------------------------------------------- */
.ed-inspector {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  padding: 0.9rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.7rem;
  background: var(--p-content-background);
  min-height: 12rem;
}
.ed-inspector__head {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}
.ed-inspector__kind {
  font-weight: 650;
}
.ed-inspector__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: auto;
}
.ed-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.8rem;
  font-weight: 500;
}
.ed-tips {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
.ed-tips li {
  display: grid;
  grid-template-columns: 1.1rem 1fr;
  gap: 0.4rem;
  align-items: start;
}

/* Pie ------------------------------------------------------------------------ */
.ed-footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.6rem 1.5rem;
  font-size: 0.8rem;
}
.ed-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.ed-stat {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-text-color) 6%, transparent);
  color: var(--p-text-muted-color);
}
.ed-stat strong {
  color: var(--p-text-color);
  font-variant-numeric: tabular-nums;
}
.ed-stat--a {
  background: var(--bm-seat-a);
}
.ed-stat--b {
  background: var(--bm-seat-b);
}
.ed-stat--falta {
  color: var(--c-warning, #f59e0b);
}
.ed-problemas {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.ed-problemas li {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

/* Fantasma ------------------------------------------------------------------ */
.ed-ghost {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 2000;
  width: 2.6rem;
  height: 2.6rem;
  margin: -1.3rem 0 0 -1.3rem;
  pointer-events: none;
  display: grid;
  filter: drop-shadow(0 10px 14px rgb(0 0 0 / 0.22));
}
.ed-ghost--quitar {
  opacity: 0.55;
}
.ed-ghost__badge {
  position: absolute;
  left: 50%;
  top: 100%;
  transform: translate(-50%, 0.25rem);
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 600;
  color: white;
  background: var(--c-danger, #ef4444);
  white-space: nowrap;
}
</style>
