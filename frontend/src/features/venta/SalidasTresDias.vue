<!--
  Salidas en tres días: el elegido en el centro (el de la fecha del filtro)
  y, a los lados, el anterior y el siguiente, alineados por la hora a la que
  sube el cliente. Alternativa a `SalidasLista.vue`; el vendedor elige cuál ve.

  Solo las salidas del centro se eligen (clic = abrir su croquis, como en la
  lista). Las de los lados se ven atenuadas: pulsarlas (o la cabecera de su
  día, o ←/→ en el teclado) mueve la fecha a ese día y resalta la pulsada.
  Sin tarifa para el viaje del cliente, la ficha no se elige.

  Los días vecinos se piden aparte con los mismos filtros ("Sube en", "Baja
  en"); lo que se cargó hace menos de un minuto se reutiliza al cambiar de
  día, así el día al que se llega aparece al instante. Reglas en `core/venta/tresDias`.
-->
<template>
  <div class="td" :class="{ 'td--cargando': cargandoCentro }" @keydown="teclado">
    <!-- Cabecera: los tres días; los laterales llevan a su fecha. -->
    <div class="td__cabecera">
      <span class="td__esquina" aria-hidden="true"><icon name="schedule-outline" size="1rem" /></span>
      <button type="button" class="td__dia td__dia--lado" :aria-label="`Ir al ${diaLargo(dias[0])}`" @click="mover(-1)">
        <icon name="chevron-left" size="1.1rem" class="td__flecha" />
        <span class="td__dia-texto">
          <span class="td__dia-rel">{{ relativo(dias[0]) }}</span>
          <span class="td__dia-cuenta tabular-nums">{{ cuenta(laterales.antes) }}</span>
        </span>
      </button>
      <div class="td__dia td__dia--centro" aria-live="polite">
        <span class="td__dia-semana">{{ semana(dias[1]) }}</span>
        <span class="td__dia-fecha">{{ numero(dias[1]) }}<em v-if="relativoCentro" class="td__dia-hoy">{{ relativoCentro }}</em></span>
        <span class="td__dia-cuenta tabular-nums">
          <template v-if="centro">{{ cuenta(centro) }}<template v-if="libresCentro !== null"> · {{ libresCentro }} libres</template></template>
          <template v-else>Cargando…</template>
        </span>
      </div>
      <button type="button" class="td__dia td__dia--lado td__dia--despues" :aria-label="`Ir al ${diaLargo(dias[2])}`" @click="mover(1)">
        <span class="td__dia-texto">
          <span class="td__dia-rel">{{ relativo(dias[2]) }}</span>
          <span class="td__dia-cuenta tabular-nums">{{ cuenta(laterales.despues) }}</span>
        </span>
        <icon name="chevron-right" size="1.1rem" class="td__flecha" />
      </button>
    </div>

    <div ref="cuerpo" :key="claveDia" class="td__cuerpo" :class="`td__cuerpo--${direccion}`">
      <template v-if="centro">
        <div v-if="!centro.length && filas.length" class="td__fila td__fila--aviso">
          <span />
          <span class="td__lado" />
          <p class="td__vacio-dia"><icon name="no-transfer-outline" size="1.1rem" /> Sin salidas este día</p>
          <span class="td__lado" />
        </div>

        <div v-for="f in filas" :key="f.hora" class="td__fila" :data-hora="f.hora">
          <span class="td__hora tabular-nums" :class="{ 'td__hora--ahora': esHoy && f.hora === ahoraHM[0] }">{{ String(f.hora).padStart(2, "0") }}<small>:00</small></span>

          <div v-for="(lista, i) in f.dias" :key="i" :class="i === 1 ? 'td__centro' : 'td__lado'">
            <span v-if="i === 1 && esHoy && f.hora === ahoraHM[0]" class="td__ahora" :style="{ top: `${(ahoraHM[1] / 60) * 100}%` }" aria-hidden="true" />

            <!-- Centro: fichas completas, elegibles. -->
            <template v-if="i === 1">
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
            </template>

            <!-- Lados: fichas mínimas; pulsarlas lleva a su día. -->
            <template v-else>
              <button
                v-for="r in lista"
                :key="r.id"
                type="button"
                class="sombra"
                :class="{ 'sombra--lleno': nivelOcupacion(r) === 'lleno', 'sombra--sin-tarifa': !conTarifa(r) }"
                :style="{ '--f': `${(ocupacion(r) ?? 0) * 100}%` }"
                :title="`${reloj(subidaDe(r))} · ${viaje(r)} — ver el ${diaLargo(dias[i]!)}`"
                :aria-label="`${diaLargo(dias[i]!)}, ${reloj(subidaDe(r))}, ${viaje(r)}. Ir a ese día`"
                tabindex="-1"
                @click="mover(i === 0 ? -1 : 1, r.id)"
              >
                <span class="sombra__hora tabular-nums">{{ reloj(subidaDe(r)) }}</span>
                <span class="sombra__ruta">{{ siglas(r) }}</span>
              </button>
            </template>
          </div>
        </div>

        <div v-if="!filas.length" class="td__vacio">
          <icon name="no-transfer-outline" size="2rem" color="text-surface-400" />
          <span>No hay salidas para estos días y estación.</span>
        </div>
      </template>

      <!-- Llegando a un día sin datos todavía. -->
      <div v-else class="td__esqueleto" aria-busy="true">
        <div v-for="n in 5" :key="n" class="td__fila">
          <span />
          <span class="td__lado" />
          <div class="td__centro"><Skeleton height="3.25rem" border-radius="0.6rem" /></div>
          <span class="td__lado" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from "vue";
import * as api from "@/core/venta/api";
import { diaISO, diasEntre, hora } from "@/core/venta/modelo";
import { diaVecino, filasPorHora, horaMinuto, nivelOcupacion, ocupacion, reloj, subidaDe } from "@/core/venta/tresDias";
import type { Importe, Opcion, SalidaResumen } from "@/core/venta/types";
import { useVentaStore } from "./store";

const emit = defineEmits<{ elegir: [id: number]; mover: [delta: number] }>();
const store = useVentaStore();

/** Lo cargado hace menos de esto se reutiliza al moverse de día. */
const VIGENCIA = 60_000;

const ahora = ref(Date.now());
const reloj60 = window.setInterval(() => (ahora.value = Date.now()), 30_000);
onBeforeUnmount(() => window.clearInterval(reloj60));
const ahoraHM = computed(() => horaMinuto(new Date(ahora.value).toISOString()));

const dias = computed(() => [diaVecino(store.fecha, -1), diaVecino(store.fecha, 0), diaVecino(store.fecha, 1)] as const);
const claveDe = (d: Date) => `${diaISO(d)}|${store.estacionId ?? ""}|${store.destinoId ?? ""}`;
const claveDia = computed(() => claveDe(store.fecha));
const esHoy = computed(() => diaISO(store.fecha) === diaISO(new Date(ahora.value)));

// —— Datos: el centro es el de la store; los lados se piden aquí ——
const cache = new Map<string, { en: number; salidas: SalidaResumen[] }>();
const vigente = (clave: string) => {
  const c = cache.get(clave);
  return c && Date.now() - c.en < VIGENCIA ? c.salidas : null;
};
/** El día que tiene cargado la store (con sus filtros); null mientras carga otro o antes de la primera carga. */
const diaCargado = ref<string | null>(store.contexto && !store.cargandoSalidas ? claveDia.value : null);
watch(
  () => store.cargandoSalidas,
  (cargando) => {
    if (cargando || store.porRango) return;
    diaCargado.value = claveDia.value;
    cache.set(claveDia.value, { en: Date.now(), salidas: store.salidas });
  },
);
/** Las salidas del centro: las de la store o, mientras llega, las que ya se tenían de ese día. */
const centro = computed<SalidaResumen[] | null>(() => {
  if (diaCargado.value === claveDia.value) return store.salidasVisibles;
  const previas = vigente(claveDia.value);
  return previas ? store.visibles(previas) : null;
});
const cargandoCentro = computed(() => store.cargandoSalidas && !!centro.value);

const crudos = reactive({ antes: [] as SalidaResumen[], despues: [] as SalidaResumen[] });
const laterales = computed(() => ({ antes: store.visibles(crudos.antes), despues: store.visibles(crudos.despues) }));
let turno = 0;
async function lateral(d: Date): Promise<SalidaResumen[]> {
  const clave = claveDe(d);
  const previas = vigente(clave);
  if (previas) return previas;
  try {
    const salidas = await api.fetchSalidas({ fecha: diaISO(d) }, store.estacionId, store.destinoId);
    cache.set(clave, { en: Date.now(), salidas });
    return salidas;
  } catch {
    return [];
  }
}
watch(
  claveDia,
  async () => {
    const n = ++turno;
    const [antes, despues] = await Promise.all([lateral(dias.value[0]), lateral(dias.value[2])]);
    if (n !== turno) return;
    crudos.antes = antes;
    crudos.despues = despues;
  },
  { immediate: true },
);

const filas = computed(() => filasPorHora([laterales.value.antes, centro.value ?? [], laterales.value.despues]));
const libresCentro = computed(() => (centro.value?.some((r) => r.capacidad) ? centro.value.reduce((n, r) => n + libres(r), 0) : null));

// —— Moverse de día ——
const direccion = ref<"izq" | "der" | "quieto">("quieto");
/** La salida lateral que se pulsó: al llegar a su día se lleva a la vista y se resalta. */
const destacada = ref<number | null>(null);
function mover(delta: number, id: number | null = null) {
  direccion.value = delta < 0 ? "izq" : "der";
  destacada.value = id;
  emit("mover", delta);
}
function teclado(e: KeyboardEvent) {
  if (e.altKey || e.ctrlKey || e.metaKey || (e.key !== "ArrowLeft" && e.key !== "ArrowRight")) return;
  e.preventDefault();
  mover(e.key === "ArrowLeft" ? -1 : 1);
}

// —— Al llegar a un día: la destacada, la elegida, el ahora (hoy) o la primera salida ——
const cuerpo = ref<HTMLElement | null>(null);
let ubicado: string | null = null;
let apagar: ReturnType<typeof setTimeout> | undefined;
onBeforeUnmount(() => clearTimeout(apagar));
watch(
  [claveDia, centro],
  async () => {
    if (!centro.value || ubicado === claveDia.value) return;
    ubicado = claveDia.value;
    await nextTick();
    const el = cuerpo.value;
    if (!el) return;
    const id = destacada.value ?? store.salidaId;
    const ficha = id != null ? el.querySelector<HTMLElement>(`.ficha[data-id="${id}"]`) : null;
    const fila = ficha?.closest<HTMLElement>(".td__fila") ?? (esHoy.value ? [...el.querySelectorAll<HTMLElement>(".td__fila[data-hora]")].find((f) => Number(f.dataset.hora) >= ahoraHM.value[0]) : null);
    el.scrollTop = fila ? Math.max(0, fila.offsetTop - el.clientHeight / 3) : 0;
    if (ficha && destacada.value != null) {
      ficha.classList.add("ficha--destacada");
      clearTimeout(apagar);
      apagar = setTimeout(() => ficha.classList.remove("ficha--destacada"), 2200);
    }
    destacada.value = null;
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
const difHoy = (d: Date) => Math.round((diaVecino(d, 0).getTime() - diaVecino(new Date(ahora.value), 0).getTime()) / 86_400_000);
/** "Ayer", "Hoy", "Mañana" o "mié 21". */
function relativo(d: Date) {
  const n = difHoy(d);
  if (n === -1) return "Ayer";
  if (n === 0) return "Hoy";
  if (n === 1) return "Mañana";
  return new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "numeric" }).format(d).replace(".", "");
}
const relativoCentro = computed(() => ({ [-1]: "ayer", 0: "hoy", 1: "mañana" })[difHoy(store.fecha)] ?? null);
const cuenta = (l: SalidaResumen[]) => (l.length ? `${l.length} ${l.length === 1 ? "salida" : "salidas"}` : "Sin salidas");

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
 * Tres columnas de día sobre un eje de horas. El centro es la "hoja": fondo
 * pleno y filos de color; los lados quedan hundidos (fondo tenue, fichas
 * mínimas y atenuadas) para que se lean como contexto, no como opciones.
 */
.td {
  --linea: var(--p-content-border-color);
  --hundido: color-mix(in srgb, var(--p-surface-500) 6%, var(--p-content-background));
  --filo: color-mix(in srgb, var(--p-primary-color) 28%, transparent);
  --cols: 3.25rem minmax(4.5rem, 0.75fr) minmax(0, 3fr) minmax(4.5rem, 0.75fr);
  container-type: inline-size;
  overflow: hidden;
  border: 1px solid var(--linea);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--hundido);
}
@container (width < 40rem) {
  .td__cabecera,
  .td__fila {
    --cols: 2.25rem 3.6rem minmax(0, 1fr) 3.6rem;
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
  min-width: 0;
  padding: 0.6rem 0.75rem;
  border: 0;
  background: transparent;
  color: var(--p-text-color);
  font: inherit;
}
.td__dia--lado {
  display: flex;
  align-items: center;
  gap: 0.1rem;
  padding-inline: 0.3rem;
  color: var(--p-text-muted-color);
  text-align: left;
  cursor: pointer;
  transition:
    color 0.15s,
    background 0.15s;
}
.td__dia--despues {
  justify-content: flex-end;
  text-align: right;
}
.td__dia--lado:hover,
.td__dia--lado:focus-visible {
  background: color-mix(in srgb, var(--p-primary-color) 7%, transparent);
  color: var(--p-text-color);
}
.td__dia--lado:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}
.td__flecha {
  flex: none;
  transition: transform 0.15s;
}
.td__dia--lado:hover .td__flecha {
  transform: translateX(-2px);
}
.td__dia--despues:hover .td__flecha {
  transform: translateX(2px);
}
.td__dia-texto {
  display: flex;
  min-width: 0;
  flex-direction: column;
  line-height: 1.2;
}
.td__dia-rel {
  overflow: hidden;
  font-size: 0.8rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.td__dia-cuenta {
  overflow: hidden;
  font-size: 0.7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  opacity: 0.85;
}
.td__dia--centro {
  display: flex;
  flex-direction: column;
  align-items: center;
  border-inline: 1px solid var(--filo);
  background: var(--p-content-background);
  box-shadow: inset 0 3px 0 var(--p-primary-color);
  line-height: 1.15;
  text-align: center;
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
}
.td__dia-hoy {
  color: var(--p-primary-color);
  font-size: 0.75rem;
  font-style: normal;
  font-weight: 600;
}
.td__dia--centro .td__dia-cuenta {
  margin-top: 0.15rem;
  color: var(--p-text-muted-color);
}
@container (width < 40rem) {
  .td__dia {
    padding: 0.5rem 0.25rem;
  }
  .td__dia--lado {
    flex-direction: column;
    justify-content: center;
    text-align: center;
  }
  .td__dia--despues {
    flex-direction: column-reverse;
  }
  .td__dia--lado .td__dia-cuenta {
    display: none;
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
.td__fila + .td__fila > * {
  border-top: 1px dashed color-mix(in srgb, var(--linea) 80%, transparent);
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
.td__lado {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.35rem 0.4rem;
}
.td__centro {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.4rem 0.5rem;
  border-inline: 1px solid var(--filo);
  background: var(--p-content-background);
  /* La ficha se acomoda al ancho de esta columna, no al de la página. */
  container: centro / inline-size;
}

/* El ahora (solo hoy): una línea roja en la hora en curso. */
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

/* —— Ficha del centro —— */
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
.ficha--destacada {
  animation: destacar 0.7s ease-in-out 3;
}
@keyframes destacar {
  50% {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--p-primary-color) 35%, transparent);
  }
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

/* —— Ficha de los lados: hora y siglas, atenuada —— */
.sombra {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: 0.2rem 0.4rem 0.3rem;
  overflow: hidden;
  border: 1px solid transparent;
  border-radius: 0.4rem;
  background: color-mix(in srgb, var(--p-content-background) 55%, transparent);
  color: var(--p-text-muted-color);
  font: inherit;
  line-height: 1.2;
  text-align: left;
  cursor: pointer;
  opacity: 0.75;
  transition:
    opacity 0.15s,
    border-color 0.15s;
}
.sombra::after {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 2px;
  background: linear-gradient(to right, color-mix(in srgb, var(--p-surface-500) 55%, transparent) var(--f), transparent var(--f));
  content: "";
}
.sombra--lleno::after {
  background: linear-gradient(to right, color-mix(in srgb, var(--p-red-500) 55%, transparent) var(--f), transparent var(--f));
}
.sombra:hover {
  border-color: var(--linea);
  color: var(--p-text-color);
  opacity: 1;
}
.sombra--sin-tarifa {
  opacity: 0.4;
}
.sombra__hora {
  font-size: 0.78rem;
  font-weight: 600;
}
.sombra__ruta {
  overflow: hidden;
  font-size: 0.66rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
@container (width < 40rem) {
  .td__lado {
    padding: 0.3rem 0.15rem;
  }
  .sombra {
    align-items: center;
    padding: 0.15rem 0.1rem 0.25rem;
  }
  .sombra__hora {
    font-size: 0.7rem;
  }
  .sombra__ruta {
    display: none;
  }
}

/* —— Vacíos —— */
.td__vacio-dia {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  margin: 0;
  padding: 0.75rem;
  border-inline: 1px solid var(--filo);
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
}
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
  .td__cuerpo--der,
  .ficha--destacada {
    animation: none;
  }
  .ficha,
  .sombra,
  .td__flecha {
    transition: none;
  }
}
</style>
