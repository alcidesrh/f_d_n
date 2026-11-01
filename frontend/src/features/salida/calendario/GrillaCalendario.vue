<!--
  La grilla del calendario de salidas: un día por fila (de la fecha elegida
  en adelante, hoy resaltado) y las horas en columnas; en cada cruce, las
  salidas de esa hora como fichas con el color de su ruta, lo ocupado
  (asientos o porciento) y una barra de fondo que se llena con la ocupación.
  Una línea marca el ahora. Los días con muchas salidas a una hora crecen.

  Pulsar una ficha la abre (`abrir`) o, en modo selección, la marca o
  desmarca. Las rutas se resaltan desde la leyenda (al pasar sobre ellas, o
  fijas al pulsarlas): las demás fichas se atenúan. `centrar()` lleva a la
  vista la columna de la hora filtrada o, sin filtro, la de la hora actual.
-->
<template>
  <div class="cal-marco" :class="{ 'cal-marco--cargando': store.cargando }">
    <div ref="scroller" class="cal" :class="{ 'cal--seleccion': store.seleccionando }" role="grid" :aria-rowcount="store.columnas.length + 1" :aria-colcount="HORAS.length + 1" :aria-multiselectable="store.seleccionando || undefined" aria-label="Calendario de salidas: días en filas, horas en columnas">
      <div class="cal__esquina" role="columnheader" aria-label="Día"><icon name="calendar-month-outline" /></div>
      <div v-for="h in HORAS" :key="h" role="columnheader" class="cal__hora" :class="{ 'cal__hora--ahora': h === ahora.hora, 'cal__hora--elegida': h === horaElegida }">{{ etiquetaHora(h) }}</div>

      <template v-for="d in store.columnas" :key="d">
        <div role="rowheader" class="cal__dia" :class="claseDia(d)" :aria-current="d === ahora.dia ? 'date' : undefined" :aria-label="`${diaLargo(d)}: ${resumenDia(d)}`">
          <span class="cal__dia-nombre">{{ semana(d) }}</span>
          <span class="cal__dia-num">{{ numero(d) }}</span>
          <span class="cal__dia-resumen tabular-nums">{{ resumenDia(d) }}</span>
          <span class="cal__dia-barra" aria-hidden="true"><span :style="{ width: `${porcientoDia(d)}%` }" /></span>
        </div>
        <div v-for="h in HORAS" :key="`${d}-${h}`" role="gridcell" class="cal__celda" :class="claseDia(d)" :data-dia="d" :data-hora="h">
          <span v-if="h === ahora.hora" class="cal__ahora" :class="{ 'cal__ahora--hoy': d === ahora.dia }" :style="{ left: `${ahora.fraccion * 100}%` }" aria-hidden="true" />
          <button v-for="s in store.celdas.get(d)?.[h] ?? []" :key="s.id" type="button" class="chip" :class="claseChip(s)" :data-ruta="s.rutaId" :style="estiloChip(s)" :aria-label="etiquetaChip(s)" :aria-pressed="store.seleccionando ? store.seleccion.has(s.id) : undefined" @click="pulsar($event, s)">
            <span class="chip__hora tabular-nums">{{ minutos(s.fecha) }}</span>
            <span class="chip__valor tabular-nums">{{ textoOcupacion(s, store.modo) }}</span>
          </button>
        </div>
      </template>
    </div>
    <p v-if="vacio" class="cal-vacio">No hay salidas en estos días con estos filtros.</p>
  </div>
</template>

<script setup lang="ts">
import { detalleOcupacion, finDeSemana, fraccion, nivel, puntas, textoOcupacion, tonoEmpresa, totales, ahoraEn } from "@/core/salida/calendario";
import { deDia, etiquetaEstado, horaDe } from "@/core/salida/filtro";
import type { SalidaCalendario } from "@/core/salida/types";
import { useCalendarioSalidasStore } from "./store";

const props = defineProps<{ activa?: number | null }>();
const emit = defineEmits<{ abrir: [evento: MouseEvent, salida: SalidaCalendario] }>();

const HORAS = Array.from({ length: 24 }, (_, h) => h);

const store = useCalendarioSalidasStore();
const scroller = ref<HTMLElement | null>(null);

const ahora = computed(() => ahoraEn(store.ahora));
const horaElegida = computed(() => (store.hora ? Number(store.hora.slice(0, 2)) : null));
const totalesDia = computed(() => new Map(store.columnas.map((d) => [d, totales(store.celdas.get(d)?.flat() ?? [])])));
const vacio = computed(() => !!store.datos && !store.cargando && !store.datos.items.length);

const semana = (d: string) => new Intl.DateTimeFormat("es-GT", { weekday: "short" }).format(deDia(d)).replace(".", "");
const numero = (d: string) => new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "short" }).format(deDia(d)).replace(".", "");
const diaLargo = (d: string) => new Intl.DateTimeFormat("es-GT", { weekday: "long", day: "numeric", month: "long" }).format(deDia(d));
const etiquetaHora = (h: number) => `${String(h).padStart(2, "0")}:00`;
/** La columna ya dice la hora: la ficha muestra los minutos («:30»). */
const minutos = (iso: string) => `:${String(new Date(iso).getMinutes()).padStart(2, "0")}`;

function pulsar(evento: MouseEvent, s: SalidaCalendario) {
  if (store.seleccionando) store.alternarSeleccion(s.id);
  else emit("abrir", evento, s);
}

function porcientoDia(d: string): number {
  const t = totalesDia.value.get(d);
  return t?.capacidad ? Math.round((t.vendidos / t.capacidad) * 100) : 0;
}

/** «44 salidas · 823» o «44 salidas · 61 %». */
function resumenDia(d: string): string {
  const t = totalesDia.value.get(d);
  if (!t?.salidas) return "Sin salidas";
  const ocupado = store.modo === "porciento" ? `${porcientoDia(d)} %` : `${t.vendidos} ocup.`;
  return `${t.salidas} ${t.salidas === 1 ? "salida" : "salidas"} · ${ocupado}`;
}

function claseDia(d: string) {
  return {
    "cal--hoy": d === ahora.value.dia,
    "cal--finde": finDeSemana(d),
    "cal--pasado": d < ahora.value.dia,
  };
}

function claseChip(s: SalidaCalendario) {
  const n = nivel(s);
  return [
    `chip--${s.estado}`,
    n ? `chip--${n}` : "",
    {
      "chip--atrasada": s.atrasada,
      "chip--empresa": s.empresaId !== null,
      "chip--atenuada": store.resaltadaEfectiva !== null && store.resaltadaEfectiva !== s.rutaId,
      "chip--activa": props.activa === s.id,
      "chip--seleccionada": store.seleccionando && store.seleccion.has(s.id),
    },
  ];
}

/** La ocupación (`--f`) y, si la salida tiene empresa, su tono (`--tono`) para el fondo tenue. */
function estiloChip(s: SalidaCalendario): Record<string, string | number> {
  const estilo: Record<string, string | number> = { "--f": `${(fraccion(s) ?? 0) * 100}%` };
  if (s.empresaId !== null) estilo["--tono"] = tonoEmpresa(s.empresaId);
  return estilo;
}

function etiquetaChip(s: SalidaCalendario): string {
  const r = store.rutaPorId.get(s.rutaId);
  const empresa = s.empresaId !== null ? store.empresas.get(s.empresaId) : null;
  return [horaDe(s.fecha), r ? `${r.nombre} (${puntas(r)})` : null, empresa, etiquetaEstado(s.estado).etiqueta, s.atrasada ? "atrasada" : null, detalleOcupacion(s)].filter(Boolean).join(", ");
}

/**
 * La columna de la hora filtrada (o de la actual) al centro del eje X
 * (medida sobre las celdas: las cabeceras son pegajosas) y el primer día arriba.
 */
function centrar({ suave = false } = {}) {
  const el = scroller.value;
  if (!el) return;
  const anchoDia = el.querySelector<HTMLElement>(".cal__esquina")?.offsetWidth ?? 0;
  const [h = 0, m = 0] = store.hora ? store.hora.split(":").map(Number) : [ahora.value.hora, ahora.value.fraccion * 60];
  const columna = el.querySelector<HTMLElement>(`.cal__celda[data-hora="${h}"]`);
  const destino: ScrollToOptions = { top: 0, behavior: suave ? "smooth" : "auto" };
  if (columna) destino.left = columna.offsetLeft + (columna.offsetWidth * m) / 60 - (anchoDia + (el.clientWidth - anchoDia) / 2);
  el.scrollTo(destino);
}

defineExpose({ centrar });
</script>

<style scoped>
.cal-marco {
  position: relative;
  min-width: 0;
  transition: opacity 0.15s;
}
.cal-marco--cargando {
  opacity: 0.65;
}
.cal {
  --col: 7.25rem;
  --dia: 8.5rem;
  --linea: var(--p-content-border-color);
  position: relative;
  display: grid;
  grid-template-columns: var(--dia) repeat(24, minmax(var(--col), 1fr));
  grid-auto-rows: max-content;
  max-height: clamp(16rem, calc(100dvh - var(--cal-arriba, 16rem) - 1rem), 200rem);
  overflow: auto;
  overscroll-behavior: contain;
  border: 1px solid var(--linea);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}

/* Cabeceras pegajosas: las horas arriba, los días a la izquierda. */
/* Capas: fichas (1–2) < días (4) < horas (5) < esquina (6). */
.cal__esquina,
.cal__hora {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--p-content-background);
  border-bottom: 1px solid var(--linea);
}
.cal__esquina {
  left: 0;
  z-index: 6;
  display: grid;
  place-items: center;
  padding: 0.35rem 0;
  border-right: 1px solid var(--linea);
  color: var(--p-text-muted-color);
}
.cal__hora {
  padding: 0.4rem 0.45rem;
  border-left: 1px solid var(--linea);
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}
.cal__hora--elegida {
  box-shadow: inset 0 -2px 0 var(--p-primary-color);
  color: var(--p-text-color);
}
.cal__hora--ahora {
  color: var(--p-red-500);
  font-weight: 700;
}

.cal__dia {
  position: sticky;
  left: 0;
  z-index: 4;
  display: grid;
  grid-template-columns: auto 1fr;
  align-content: start;
  align-items: baseline;
  gap: 0 0.35rem;
  padding: 0.45rem 0.55rem 0.4rem;
  border-right: 1px solid var(--linea);
  border-bottom: 1px solid var(--linea);
  background: var(--p-content-background);
  color: var(--p-text-color);
}
.cal__dia-nombre {
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.cal__dia-num {
  font-size: 0.95rem;
  font-weight: 650;
}
.cal__dia-resumen {
  grid-column: 1 / -1;
  overflow: hidden;
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cal__dia-barra {
  grid-column: 1 / -1;
  height: 3px;
  margin-top: 0.3rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--p-surface-200);
}
.cal__dia-barra > span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--p-primary-color);
}
.cal__dia.cal--hoy {
  background: color-mix(in srgb, var(--p-primary-color) 10%, var(--p-content-background));
}
.cal__dia.cal--hoy .cal__dia-num {
  color: var(--p-primary-color);
}

.cal__celda {
  position: relative;
  min-height: 3.6rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(3.3rem, 1fr));
  align-content: start;
  gap: 0.25rem;
  padding: 0.25rem;
  border-bottom: 1px solid var(--linea);
  border-left: 1px solid var(--linea);
}
.cal__celda.cal--finde {
  background: color-mix(in srgb, var(--p-surface-500) 5%, transparent);
}
.cal__celda.cal--hoy {
  background: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}

/* El ahora: una línea fina en toda la columna y marcada en la fila de hoy. */
.cal__ahora {
  position: absolute;
  z-index: 0;
  top: 0;
  bottom: 0;
  width: 0;
  border-left: 1px dashed color-mix(in srgb, var(--p-red-500) 45%, transparent);
  pointer-events: none;
}
.cal__ahora--hoy {
  border-left: 2px solid var(--p-red-500);
}
.cal__ahora--hoy::before {
  position: absolute;
  top: -4px;
  left: -5px;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: var(--p-red-500);
  content: "";
}

/*
 * Ficha de una salida: en la paleta `surface` (la de cada ruta se distingue
 * por su nombre, no por el color), con el fondo sin ocupar teñido tenue por
 * su empresa (`chip--empresa`). La paleta se invierte sola en modo oscuro.
 * El fondo se llena de izquierda a derecha con la ocupación (`--f`).
 */
.chip {
  --borde: var(--p-surface-500);
  --lleno: var(--p-emerald-200);
  --vacio: var(--p-surface-50);
  --tinta: var(--p-surface-800);
  position: relative;
  z-index: 1;
  display: inline-flex;
  min-width: 0;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.2rem;
  padding: 0.1rem 0.3rem 0.1rem 0.3rem;
  border: 1px solid color-mix(in srgb, var(--borde) 45%, transparent);
  border-left: 3px solid var(--borde);
  border-radius: 0.35rem;
  background: linear-gradient(to right, var(--lleno) var(--f), var(--vacio) var(--f));
  color: var(--tinta);
  font: inherit;
  font-size: 0.7rem;
  line-height: 1.35;
  cursor: pointer;
  transition:
    opacity 0.15s,
    transform 0.1s,
    box-shadow 0.15s;
}
/* El fondo sin ocupar, tenue con el tono de la empresa (generado: no es rampa, de ahí `light-dark()`). */
.chip--empresa {
  --vacio: light-dark(hsl(calc(var(--tono) * 1deg) 70% 94%), hsl(calc(var(--tono) * 1deg) 30% 20%));
}
.chip:hover,
.chip:focus-visible {
  z-index: 2;
  box-shadow: 0 2px 8px -2px color-mix(in srgb, var(--borde) 60%, transparent);
  transform: translateY(-1px);
}
.chip:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
.chip__hora {
  /*font-size: 0.62rem;*/
  font-weight: 600;

  /*opacity: 0.7;*/
}
.chip__valor {
  font-weight: 600;
}
.chip--activa {
  outline: 2px solid var(--borde);
  outline-offset: 1px;
}
.chip--atenuada {
  opacity: 0.18;
}
/* Modo selección: las marcadas, con el color primario. */
.cal--seleccion .chip {
  cursor: copy;
}
.chip--seleccionada {
  --borde: var(--p-primary-color);
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
  opacity: 1;
}
/* Llena: el número en negrita sobre el borde completo. */
.chip--llena {
  border-color: var(--borde);
}
.chip--abordando {
  border-style: dashed;
  border-left-style: solid;
}
/* En ruta: un punto que late. */
.chip--iniciada::after,
.chip--atrasada::after {
  position: absolute;
  top: -3px;
  right: -3px;
  width: 7px;
  height: 7px;
  border: 1px solid var(--p-content-background);
  border-radius: 999px;
  background: var(--c-success);
  content: "";
}
.chip--iniciada::after {
  animation: latir 1.6s ease-in-out infinite;
}
.chip--atrasada::after {
  background: var(--c-warning);
}
.chip--finalizada {
  filter: saturate(0.35);
  opacity: 0.75;
}
.chip--finalizada.chip--atenuada,
.chip--cancelada.chip--atenuada {
  opacity: 0.15;
}
.chip--cancelada {
  background: repeating-linear-gradient(135deg, var(--vacio) 0 4px, transparent 4px 8px);
  opacity: 0.6;
  text-decoration: line-through;
}
@keyframes latir {
  50% {
    transform: scale(1.35);
    opacity: 0.6;
  }
}
@media (prefers-reduced-motion: reduce) {
  .chip--iniciada::after {
    animation: none;
  }
  .chip {
    transition: none;
  }
}

.cal-vacio {
  position: absolute;
  top: 45%;
  left: 50%;
  z-index: 7;
  margin: 0;
  padding: 0.6rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
  transform: translate(-50%, -50%);
  pointer-events: none;
}
</style>
