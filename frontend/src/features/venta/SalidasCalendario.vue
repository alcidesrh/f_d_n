<!--
  Salidas en calendario: horas en el eje vertical y, en cada hora, las
  salidas por la hora a la que sube el cliente. Alternativa a
  `SalidasLista.vue`; el vendedor elige cuál ve.

  Con una fecha, ese día solo (←/→ en el teclado mueve la fecha). Con un
  rango, una columna por día desde el primero, tantas como quepan; si
  quedan días fuera, "Siguiente" pone el primero que no se veía a la
  izquierda (←/→ pasan de página). Clic en una salida = abrir su croquis,
  como en la lista; sin tarifa para el viaje del cliente, no se elige.

  Lo cargado de un día hace menos de un minuto se reutiliza al volver a él,
  así aparece al instante. Reglas en `core/venta/calendario`.
-->
<template>
  <div ref="raiz" class="td" :class="{ 'td--cargando': cargando, 'td--rango': store.porRango }" :style="{ '--n': Math.max(1, columnas.length) }" @keydown="teclado">
    <!-- Rango con más días de los que caben: cuáles se ven y pasar a los demás. -->
    <div v-if="store.porRango && todos.length > columnas.length" class="td__paginas">
      <button type="button" class="td__pagina" :disabled="inicio === 0" @click="pasar(-1)">
        <icon name="chevron-left" size="1.1rem" />
        <span>Anterior</span>
      </button>
      <span class="td__paginas-texto tabular-nums">
        {{ tramo }}<small> · {{ columnas.length }} de {{ todos.length }} días</small>
      </span>
      <button type="button" class="td__pagina" :disabled="!siguiente" :title="siguiente ? `Desde el ${diaLargo(siguiente)}` : undefined" @click="pasar(1)">
        <span>Siguiente</span>
        <small v-if="siguiente" class="td__pagina-dia">{{ corto(siguiente) }}</small>
        <icon name="chevron-right" size="1.1rem" />
      </button>
    </div>

    <div class="td__cabecera">
      <span class="td__esquina" aria-hidden="true"><icon name="schedule-outline" size="1rem" /></span>
      <div v-for="c in columnas" :key="c.clave" class="td__dia" :class="{ 'td__dia--hoy': c.hoy }" aria-live="polite">
        <span class="td__dia-semana">{{ semana(c.dia) }}</span>
        <span class="td__dia-fecha">{{ numero(c.dia) }}<em v-if="relativo(c.dia)" class="td__dia-hoy">{{ relativo(c.dia) }}</em></span>
        <span class="td__dia-cuenta tabular-nums">
          <template v-if="c.salidas">{{ cuenta(c.salidas) }}<template v-if="libresDe(c.salidas) !== null"> · {{ libresDe(c.salidas) }} libres</template></template>
          <template v-else>Cargando…</template>
        </span>
      </div>
    </div>

    <div ref="cuerpo" :key="claveVista" class="td__cuerpo" :class="`td__cuerpo--${direccion}`">
      <template v-if="listo">
        <template v-for="f in filas" :key="f.hora">
          <!-- Renglón del grupo: de la primera a la última salida de la hora en cada día. -->
          <div class="td__fila td__rango" aria-hidden="true">
            <span />
            <span v-for="(lista, i) in f.dias" :key="i">{{ rango(lista) }}</span>
          </div>
          <div class="td__fila" :data-hora="f.hora">
            <span class="td__hora tabular-nums" :class="{ 'td__hora--ahora': hayHoy && f.hora === ahoraHM[0] }">{{ String(f.hora).padStart(2, "0") }}<small>:00</small></span>

            <div v-for="(lista, i) in f.dias" :key="i" class="td__col">
              <span v-if="columnas[i]?.hoy && f.hora === ahoraHM[0]" class="td__ahora" :style="{ top: `${(ahoraHM[1] / 60) * 100}%` }" aria-hidden="true" />
              <button
                v-for="r in lista"
                :key="r.id"
                type="button"
                class="ficha"
                :class="claseFicha(r)"
                :data-id="r.id"
                :aria-pressed="r.id === store.salidaId"
                :aria-disabled="!conTarifa(r)"
                :aria-label="etiqueta(r)"
                :style="{ '--f': `${(ocupacion(r) ?? 0) * 100}%` }"
                @click="elegir(r)"
              >
                <span class="ficha__hora tabular-nums">
                  {{ reloj(subidaDe(r)) }}
                  <small v-if="r.viaje?.sube.aprox" title="Hora estimada en la parada">≈</small>
                </span>
                <span class="ficha__viaje">
                  <span class="ficha__trayecto" :title="viaje(r)">
                    {{ punta(r, "sube") }}<icon name="arrow-right-alt" size="0.95rem" class="ficha__a" />{{ punta(r, "baja") }}
                    <span v-if="llegada(r)" class="ficha__llega tabular-nums" :title="`Llega ${llegada(r)!.texto}`">{{ llegada(r)!.hora }}<sup v-if="llegada(r)!.dias">+{{ llegada(r)!.dias }}</sup></span>
                  </span>
                  <span class="ficha__detalle">
                    <span :title="`${r.ruta.origen.nombre} → ${r.ruta.destino.nombre}`">{{ siglas(r) }}</span>
                    <span v-if="r.empresa" class="ficha__punto">{{ nombreEmpresa(r) }}</span>
                    <span v-if="r.bus" class="ficha__punto" :title="[r.bus.codigo, r.bus.gama].filter(Boolean).join(' · ')">{{ r.bus.codigo }}</span>
                    <span v-if="r.estado === 'abordando' || r.estado === 'iniciada'" class="ficha__estado" :class="`ficha__estado--${r.estado}`">{{ r.estado === "iniciada" ? "En ruta" : "Abordando" }}</span>
                  </span>
                </span>
                <span class="ficha__lado">
                  <span v-if="conTarifa(r)" class="ficha__precios tabular-nums">
                    <span v-for="p in r.precios" :key="p.clase"><b>{{ p.clase }}</b>{{ precioCorto(p.precio) }}</span>
                  </span>
                  <span v-else class="ficha__sin-tarifa">Sin tarifa</span>
                  <span v-if="r.capacidad" class="ficha__libres tabular-nums" :title="`${r.vendidos ?? 0} vendidos de ${r.capacidad}`">
                    <b>{{ libres(r) }}</b> libres
                  </span>
                </span>
                <span class="ficha__barra" aria-hidden="true" />
              </button>
            </div>
          </div>
        </template>

        <div v-if="!filas.length" class="td__vacio">
          <icon name="no-transfer-outline" size="2rem" color="text-surface-400" />
          <span>{{ store.porRango ? "No hay salidas en estos días y estación." : "No hay salidas este día y estación." }}</span>
        </div>
      </template>

      <!-- Llegando a un día sin datos todavía. -->
      <div v-else class="td__esqueleto" aria-busy="true">
        <div v-for="n in 5" :key="n" class="td__fila">
          <span />
          <div v-for="c in columnas" :key="c.clave" class="td__col"><Skeleton height="3.25rem" border-radius="0.6rem" /></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { diaISO, diasEntre, hora } from "@/core/venta/modelo";
import { diaVecino, diasDelRango, filasPorHora, horaMinuto, nivelOcupacion, ocupacion, porDia, reloj, subidaDe } from "@/core/venta/calendario";
import type { Importe, Opcion, SalidaResumen } from "@/core/venta/types";
import { useVentaStore } from "./store";

const emit = defineEmits<{ elegir: [id: number]; mover: [delta: number] }>();
const store = useVentaStore();

/** Lo cargado hace menos de esto se reutiliza al volver a un día. */
const VIGENCIA = 60_000;
/** Ancho mínimo de la columna de un día con rango (px): por debajo, la ficha ya no se lee. */
const ANCHO_DIA = 224;

const ahora = ref(Date.now());
const reloj60 = window.setInterval(() => (ahora.value = Date.now()), 30_000);
onBeforeUnmount(() => window.clearInterval(reloj60));
const ahoraHM = computed(() => horaMinuto(new Date(ahora.value).toISOString()));
const hoyISO = computed(() => diaISO(new Date(ahora.value)));

const claveDe = (d: Date) => `${diaISO(d)}|${store.estacionId ?? ""}|${store.destinoId ?? ""}`;
const claveDia = computed(() => claveDe(store.fecha));

// —— Un día: el de la store o, mientras llega, el que ya se tenía ——
const cache = new Map<string, { en: number; salidas: SalidaResumen[] }>();
/** El día que tiene cargado la store (con sus filtros); null mientras carga otro o antes de la primera carga. */
const diaCargado = ref<string | null>(store.contexto && !store.cargandoSalidas && !store.porRango ? claveDia.value : null);
watch(
  () => store.cargandoSalidas,
  (cargando) => {
    if (cargando || store.porRango) return;
    diaCargado.value = claveDia.value;
    cache.set(claveDia.value, { en: Date.now(), salidas: store.salidas });
  },
);
const delDia = computed<SalidaResumen[] | null>(() => {
  if (diaCargado.value === claveDia.value) return store.salidasVisibles;
  const c = cache.get(claveDia.value);
  return c && Date.now() - c.en < VIGENCIA ? store.visibles(c.salidas) : null;
});

// —— Rango: una columna por día, tantas como quepan ——
const raiz = ref<HTMLElement | null>(null);
const ancho = ref(0);
const observador = new ResizeObserver(([e]) => (ancho.value = e!.contentRect.width));
onMounted(() => raiz.value && observador.observe(raiz.value));
onBeforeUnmount(() => observador.disconnect());
const cupo = computed(() => Math.max(1, Math.floor((ancho.value - (ancho.value < 640 ? 36 : 52)) / ANCHO_DIA)));

const todos = computed(() => (store.porRango ? diasDelRango(store.desde, store.hasta) : []));
/** El primer día que se ve (índice en `todos`). */
const inicio = ref(0);
watch(
  () => [store.porRango, store.desde.getTime(), store.hasta.getTime()],
  () => (inicio.value = 0),
);
const visibles = computed(() => todos.value.slice(inicio.value, inicio.value + cupo.value));
const siguiente = computed(() => todos.value[inicio.value + visibles.value.length] ?? null);
/** Antes de la primera carga del rango no hay nada que repartir. */
const rangoListo = computed(() => store.porRango && !!store.contexto);

interface Columna {
  dia: Date;
  clave: string;
  hoy: boolean;
  salidas: SalidaResumen[] | null;
}
const columnas = computed<Columna[]>(() => {
  if (!store.porRango) {
    const clave = diaISO(store.fecha);
    return [{ dia: store.fecha, clave, hoy: clave === hoyISO.value, salidas: delDia.value }];
  }
  const claves = visibles.value.map(diaISO);
  const listas = rangoListo.value ? porDia(store.salidasVisibles, claves) : [];
  return visibles.value.map((dia, i) => ({ dia, clave: claves[i]!, hoy: claves[i] === hoyISO.value, salidas: listas[i] ?? null }));
});
const listo = computed(() => (store.porRango ? rangoListo.value : !!delDia.value));
const cargando = computed(() => store.cargandoSalidas && listo.value);
const hayHoy = computed(() => columnas.value.some((c) => c.hoy));
const filas = computed(() => filasPorHora(columnas.value.map((c) => c.salidas ?? [])));

// —— Moverse: de día con una fecha, de página con un rango ——
const direccion = ref<"izq" | "der" | "quieto">("quieto");
function mover(delta: number) {
  direccion.value = delta < 0 ? "izq" : "der";
  emit("mover", delta);
}
/** "Siguiente": el primer día que no se veía queda a la izquierda. */
function pasar(delta: number) {
  if (delta > 0 ? !siguiente.value : inicio.value === 0) return;
  direccion.value = delta < 0 ? "izq" : "der";
  inicio.value = delta > 0 ? inicio.value + visibles.value.length : Math.max(0, inicio.value - cupo.value);
}
function teclado(e: KeyboardEvent) {
  if (e.altKey || e.ctrlKey || e.metaKey || (e.key !== "ArrowLeft" && e.key !== "ArrowRight")) return;
  e.preventDefault();
  const delta = e.key === "ArrowLeft" ? -1 : 1;
  if (store.porRango) pasar(delta);
  else mover(delta);
}
const claveVista = computed(() => (store.porRango ? `rango|${diaISO(store.desde)}|${diaISO(store.hasta)}|${inicio.value}` : claveDia.value));

// —— Al llegar: la elegida, el ahora (si hoy se ve) o la primera salida ——
const cuerpo = ref<HTMLElement | null>(null);
let ubicado: string | null = null;
watch(
  [claveVista, listo],
  async () => {
    if (!listo.value || ubicado === claveVista.value) return;
    ubicado = claveVista.value;
    await nextTick();
    const el = cuerpo.value;
    if (!el) return;
    const ficha = store.salidaId != null ? el.querySelector<HTMLElement>(`.ficha[data-id="${store.salidaId}"]`) : null;
    const grupo = ficha?.closest<HTMLElement>(".td__fila") ?? (hayHoy.value ? [...el.querySelectorAll<HTMLElement>(".td__fila[data-hora]")].find((f) => Number(f.dataset.hora) >= ahoraHM.value[0]) : null);
    // Desde el renglón del grupo (el rango de horas), que va justo encima.
    const fila = (grupo?.previousElementSibling as HTMLElement | null) ?? grupo;
    el.scrollTop = fila ? Math.max(0, fila.offsetTop - el.clientHeight / 3) : 0;
  },
  { immediate: true, flush: "post" },
);

// —— Elegir ——
const conTarifa = (r: SalidaResumen) => !!r.precios?.length;
function elegir(r: SalidaResumen) {
  if (conTarifa(r)) emit("elegir", r.id);
}
function claseFicha(r: SalidaResumen) {
  return {
    "ficha--elegida": r.id === store.salidaId,
    "ficha--sin-tarifa": !conTarifa(r),
    "ficha--pasada": Date.parse(subidaDe(r)) < ahora.value,
    [`ficha--${nivelOcupacion(r)}`]: !!nivelOcupacion(r),
  };
}

// —— Textos ——
const semana = (d: Date) => new Intl.DateTimeFormat("es-GT", { weekday: "long" }).format(d);
const numero = (d: Date) => new Intl.DateTimeFormat("es-GT", { day: "numeric", month: "long" }).format(d);
const diaLargo = (d: Date) => new Intl.DateTimeFormat("es-GT", { weekday: "long", day: "numeric", month: "long" }).format(d);
/** "jue 22". */
const corto = (d: Date) => new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "numeric" }).format(d).replace(".", "");
/** "jue 22 – dom 25 de octubre": los días que se ven del rango. */
const tramo = computed(() => {
  const [a, b] = [visibles.value[0], visibles.value.at(-1)];
  if (!a || !b) return "";
  const mes = new Intl.DateTimeFormat("es-GT", { month: "long" }).format(b);
  return a === b ? `${corto(a)} de ${mes}` : `${corto(a)} – ${corto(b)} de ${mes}`;
});
/** "hoy", "ayer", "mañana" o nada. */
function relativo(d: Date) {
  const n = Math.round((diaVecino(d, 0).getTime() - diaVecino(new Date(ahora.value), 0).getTime()) / 86_400_000);
  return ({ [-1]: "ayer", 0: "hoy", 1: "mañana" } as Record<number, string>)[n] ?? null;
}
/** "11:30 a 11:55": la primera y la última hora de subida del grupo; una sola si coinciden. */
function rango(l: SalidaResumen[]) {
  if (!l.length) return "";
  const [desde, hasta] = [reloj(subidaDe(l[0]!)), reloj(subidaDe(l.at(-1)!))];
  return desde === hasta ? desde : `${desde} a ${hasta}`;
}
const cuenta = (l: SalidaResumen[]) => (l.length ? `${l.length} ${l.length === 1 ? "salida" : "salidas"}` : "Sin salidas");
const libresDe = (l: SalidaResumen[]) => (l.some((r) => r.capacidad) ? l.reduce((n, r) => n + libres(r), 0) : null);

const punta = (r: SalidaResumen, cual: "sube" | "baja") => r.viaje?.[cual].nombre ?? (cual === "sube" ? r.ruta.origen.nombre : r.ruta.destino.nombre) ?? "—";
const viaje = (r: SalidaResumen) => `${punta(r, "sube")} → ${punta(r, "baja")}`;
/** La hora de llegada a donde baja el cliente y, si es otro día, cuántos después. */
function llegada(r: SalidaResumen) {
  const b = r.viaje?.baja;
  if (!b?.hora) return null;
  return { hora: reloj(b.hora), dias: Math.max(0, diasEntre(subidaDe(r), b.hora)), texto: `${b.aprox ? "≈ " : ""}${hora(b.hora)}` };
}
const sigla = (o: Opcion & { alias: string | null }) => o.alias || o.nombre;
const siglas = (r: SalidaResumen) => `${sigla(r.ruta.origen)}–${sigla(r.ruta.destino)}`;
/** "TRANSPORTES FUENTES" → "Transportes Fuentes". */
const nombreEmpresa = (r: SalidaResumen) => (r.empresa?.nombre ?? "").toLocaleLowerCase("es").replace(/(^|[\s-])(\p{L})/gu, (_, sep: string, c: string) => sep + c.toLocaleUpperCase("es"));
const libres = (r: SalidaResumen) => Math.max(0, (r.capacidad ?? 0) - (r.vendidos ?? 0));
const precioCorto = (p: Importe | null) => (p ? p.texto.replace(/[.,]00$/, "") : "—");
const etiqueta = (r: SalidaResumen) =>
  [reloj(subidaDe(r)), viaje(r), siglas(r), r.empresa?.nombre, r.bus?.codigo, conTarifa(r) ? r.precios!.map((p) => `${p.clase} ${precioCorto(p.precio)}`).join(", ") : "sin tarifa", r.capacidad ? `${libres(r)} libres` : null].filter(Boolean).join(", ");
</script>

<style scoped>
/*
 * Columnas de día sobre un eje de horas. Cada día es una "hoja" (fondo
 * pleno); el eje queda hundido. Con un solo día la cabecera es grande y
 * centrada; con un rango, una por columna y el filo de color en la de hoy.
 */
.td {
  --linea: var(--p-content-border-color);
  --hundido: color-mix(in srgb, var(--p-surface-500) 6%, var(--p-content-background));
  --cols: 3.25rem repeat(var(--n), minmax(0, 1fr));
  container-type: inline-size;
  overflow: hidden;
  border: 1px solid var(--linea);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--hundido);
}
@container (width < 40rem) {
  .td__cabecera,
  .td__fila {
    --cols: 2.25rem repeat(var(--n), minmax(0, 1fr));
  }
}

/* —— Páginas del rango —— */
.td__paginas {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.35rem 0.4rem;
  border-bottom: 1px solid var(--linea);
}
.td__paginas-texto {
  min-width: 0;
  overflow: hidden;
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.td__paginas-texto small {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-weight: 500;
}
.td__pagina {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem 0.55rem;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-color);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    border-color 0.15s;
}
.td__pagina:hover:not(:disabled) {
  border-color: var(--linea);
  background: var(--p-content-background);
}
.td__pagina:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
.td__pagina:disabled {
  cursor: default;
  opacity: 0.4;
}
.td__pagina-dia {
  color: var(--p-primary-color);
  font-size: 0.75rem;
}
@container (width < 40rem) {
  .td__pagina > span,
  .td__paginas-texto small {
    display: none;
  }
}

/* —— Cabecera —— */
.td__cabecera {
  display: grid;
  grid-template-columns: var(--cols);
  border-bottom: 1px solid var(--linea);
}
.td__esquina {
  display: grid;
  place-items: center;
  color: var(--p-text-muted-color);
}
.td__dia {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  padding: 0.6rem 0.75rem;
  border-left: 1px solid var(--linea);
  background: var(--p-content-background);
  box-shadow: inset 0 3px 0 var(--p-primary-color);
  line-height: 1.15;
  text-align: center;
}
.td--rango .td__dia {
  padding: 0.45rem 0.5rem;
  box-shadow: none;
}
.td--rango .td__dia--hoy {
  box-shadow: inset 0 3px 0 var(--p-primary-color);
}
.td__dia-semana {
  color: var(--p-text-muted-color);
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.td__dia-fecha {
  display: inline-flex;
  align-items: baseline;
  gap: 0.4rem;
  font-size: 1.25rem;
  font-weight: 650;
  letter-spacing: -0.01em;
  white-space: nowrap;
}
.td--rango .td__dia-fecha {
  font-size: 1rem;
}
.td__dia-hoy {
  color: var(--p-primary-color);
  font-size: 0.75rem;
  font-style: normal;
  font-weight: 600;
}
.td__dia-cuenta {
  max-width: 100%;
  margin-top: 0.15rem;
  overflow: hidden;
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
@container (width < 40rem) {
  .td__dia {
    padding: 0.5rem 0.25rem;
  }
  .td__dia-fecha {
    font-size: 1.05rem;
  }
}

/* —— Cuerpo —— */
.td__cuerpo {
  position: relative;
  max-height: 38rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  transition: opacity 0.15s;
}
.td--cargando .td__cuerpo {
  opacity: 0.6;
}
.td__cuerpo--izq {
  animation: entra-izq 0.28s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.td__cuerpo--der {
  animation: entra-der 0.28s cubic-bezier(0.2, 0.7, 0.2, 1);
}
@keyframes entra-izq {
  from {
    opacity: 0;
    transform: translateX(-1.25rem);
  }
}
@keyframes entra-der {
  from {
    opacity: 0;
    transform: translateX(1.25rem);
  }
}

.td__fila {
  display: grid;
  grid-template-columns: var(--cols);
}
.td__fila + .td__rango > * {
  border-top: 1px dashed color-mix(in srgb, var(--linea) 80%, transparent);
}
/* Renglón del grupo: el rango de horas de cada día, sobre sus salidas. */
.td__rango > * {
  min-width: 0;
  overflow: hidden;
  padding: 0.3rem 0.75rem 0;
  color: var(--p-text-muted-color);
  font-size: 0.68rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.td__rango > * + * {
  border-left: 1px solid var(--linea);
  background: var(--p-content-background);
}
.td__hora {
  padding: 0.55rem 0.45rem 0 0;
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
  font-weight: 600;
  text-align: right;
}
.td__hora small {
  font-size: 0.65rem;
  font-weight: 400;
  opacity: 0.7;
}
.td__hora--ahora {
  color: var(--p-red-500);
}
@container (width < 40rem) {
  .td__hora small {
    display: none;
  }
}
.td__col {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.4rem 0.5rem;
  border-left: 1px solid var(--linea);
  background: var(--p-content-background);
  /* La ficha se acomoda al ancho de su día, no al de la página. */
  container: centro / inline-size;
}
.td--rango .td__col {
  padding-inline: 0.4rem;
}

/* El ahora (en el día de hoy): una línea roja en la hora en curso. */
.td__ahora {
  position: absolute;
  z-index: 2;
  right: 0;
  left: 0;
  height: 0;
  border-top: 1.5px solid var(--p-red-500);
  pointer-events: none;
}
.td__ahora::before {
  position: absolute;
  top: -4.5px;
  left: -4px;
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--p-red-500);
  content: "";
}

/* —— Ficha —— */
.ficha {
  --tinta-barra: var(--p-emerald-500);
  position: relative;
  display: grid;
  grid-template-columns: 3.4rem minmax(0, 1fr) max-content;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.5rem 0.65rem 0.6rem;
  overflow: hidden;
  border: 1px solid var(--linea);
  border-radius: 0.6rem;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition:
    border-color 0.15s,
    box-shadow 0.15s,
    transform 0.12s;
}
.ficha:hover {
  border-color: color-mix(in srgb, var(--p-primary-color) 45%, var(--linea));
  box-shadow: 0 4px 14px -6px color-mix(in srgb, var(--p-surface-900) 30%, transparent);
  transform: translateY(-1px);
}
.ficha:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
.ficha--elegida {
  border-color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 7%, var(--p-content-background));
  box-shadow: 0 0 0 1px var(--p-primary-color);
}
.ficha--elegida .ficha__hora {
  color: var(--p-primary-color);
}
.ficha--pasada:not(.ficha--elegida) {
  opacity: 0.55;
}
.ficha--sin-tarifa {
  cursor: not-allowed;
  opacity: 0.5;
}
.ficha--sin-tarifa:hover {
  border-color: var(--linea);
  box-shadow: none;
  transform: none;
}

.ficha__hora {
  display: flex;
  align-items: baseline;
  gap: 0.1rem;
  font-size: 1.15rem;
  font-weight: 650;
  letter-spacing: -0.02em;
}
.ficha__hora small {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-weight: 500;
}
.ficha__viaje {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.1rem;
  line-height: 1.25;
}
.ficha__trayecto {
  display: flex;
  min-width: 0;
  align-items: center;
  overflow: hidden;
  font-size: 0.92rem;
  font-weight: 560;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ficha__a {
  flex: none;
  margin-inline: 0.15rem;
  color: var(--p-text-muted-color);
}
.ficha__llega {
  margin-left: 0.45rem;
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-weight: 500;
}
.ficha__llega sup {
  margin-left: 0.05rem;
  color: var(--p-orange-600);
  font-weight: 650;
}
.ficha__detalle {
  display: flex;
  min-width: 0;
  align-items: center;
  overflow: hidden;
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  white-space: nowrap;
}
.ficha__detalle > * {
  overflow: hidden;
  text-overflow: ellipsis;
}
.ficha__punto::before {
  margin-inline: 0.35rem;
  content: "·";
}
.ficha__estado {
  flex: none;
  margin-left: 0.5rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 650;
  letter-spacing: 0.03em;
  line-height: 1.5;
  text-transform: uppercase;
}
.ficha__estado--abordando {
  background: var(--p-amber-100);
  color: var(--p-amber-800);
}
.ficha__estado--iniciada {
  background: var(--c-success-soft);
  color: var(--p-emerald-700);
}
.ficha__lado {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.15rem;
  line-height: 1.2;
}
.ficha__precios {
  display: inline-flex;
  gap: 0.55rem;
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
}
.ficha__precios b {
  margin-right: 0.2rem;
  color: var(--p-text-muted-color);
  font-size: 0.65rem;
  font-weight: 600;
}
.ficha__precios > span + span {
  color: var(--p-orange-600);
}
.ficha__sin-tarifa {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-style: italic;
  white-space: nowrap;
}
.ficha__libres {
  color: var(--p-text-muted-color);
  font-size: 0.72rem;
  white-space: nowrap;
}
.ficha__libres b {
  color: var(--p-text-color);
  font-weight: 650;
}
/* La ocupación: un hilo en el borde inferior que se llena. */
.ficha__barra {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 3px;
  background: linear-gradient(to right, var(--tinta-barra) var(--f), color-mix(in srgb, var(--p-surface-400) 18%, transparent) var(--f));
}
.ficha--alto {
  --tinta-barra: var(--p-amber-500);
}
.ficha--lleno {
  --tinta-barra: var(--p-red-500);
}
@container centro (width < 24rem) {
  .ficha {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.15rem 0.5rem;
    padding: 0.4rem 0.5rem 0.5rem;
  }
  .ficha__hora {
    grid-column: 1;
    font-size: 1rem;
  }
  .ficha__viaje {
    grid-column: 1 / -1;
    grid-row: 2;
  }
  .ficha__lado {
    grid-column: 2;
    grid-row: 1;
    flex-direction: row;
    align-items: baseline;
    gap: 0.5rem;
  }
}


/* —— Vacío —— */
.td__vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 3rem 1rem;
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
}

@media (prefers-reduced-motion: reduce) {
  .td__cuerpo--izq,
  .td__cuerpo--der {
    animation: none;
  }
  .ficha,
  .td__pagina {
    transition: none;
  }
}
</style>
