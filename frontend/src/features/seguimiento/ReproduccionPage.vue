<!--
  Reproducción de la trayectoria GPS de una salida (ADR-050), ruta `/seguimiento/reproducir` (`?salida=` abre una).
  Se elige el día y una de sus salidas con lecturas guardadas; el mapa dibuja lo recorrido (tenue) y la ruta
  planificada con sus estaciones, y el bus avanza con el reloj a la velocidad elegida (×10 a ×300). La barra
  lleva a cualquier momento. Un hueco de más de 5 min sin lecturas se dibuja en raya gris y se salta al reproducir.
  «Seguir al bus» mueve el mapa cuando el bus se sale de la vista.
  Temporal: también las trayectorias de los teléfonos de prueba (`?dispositivo=&desde=`), sin salida: más de 5 min
  sin lecturas o detenido cierra una y empieza otra.
  Contenido angosto: mapa, controles y detalle apilados; desde 56rem de ancho: el detalle a la derecha.
  Maximizar (o Esc para salir), como en el mapa en vivo: día y salida, mapa, controles y detalle cubren toda la pantalla.
-->
<template>
  <div class="@container flex flex-col gap-4" :class="maximizado ? 'fixed inset-0 z-[900] overflow-auto bg-surface-0 p-3' : ''">
    <!-- Angosto: el botón de maximizar va junto al día y la salida debajo; ancho: los tres en una fila. -->
    <section class="panel grid grid-cols-[minmax(0,1fr)_auto] gap-3 @xl:grid-cols-[12rem_minmax(0,28rem)_minmax(0,1fr)] @xl:items-end">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Día</span>
        <DatePicker v-model="dia" date-format="dd/mm/yy" show-icon :max-date="hoy" :manual-input="false" fluid input-id="dia-trayectorias" @update:model-value="cargarDia" />
      </label>
      <label class="col-span-2 flex flex-col gap-1 @xl:col-span-1">
        <span class="text-xs font-medium text-muted-color">{{ telefonosDelDia.length ? "Salida o teléfono con GPS" : "Salida con GPS" }}</span>
        <Select v-model="elegida" :options="opciones" option-label="etiqueta" option-value="id" filter :loading="cargandoDia" :placeholder="cargandoDia ? 'Cargando…' : opciones.length ? 'Elija una' : 'Ninguna salida con GPS este día'" :disabled="!cargandoDia && opciones.length === 0" fluid @change="alElegir" />
      </label>
      <Button size="small" text rounded class="tap-target col-start-2 row-start-1 self-end justify-self-end @xl:col-start-3" :aria-label="maximizado ? 'Salir de pantalla completa' : 'Maximizar mapa'" :title="maximizado ? 'Salir de pantalla completa (Esc)' : 'Maximizar'" :aria-pressed="maximizado" @click="alternarMaximizado">
        <icon :name="maximizado ? 'fullscreen-exit' : 'fullscreen'" />
      </Button>
      <p v-if="delDia" class="col-span-2 m-0 text-xs text-muted-color @xl:col-span-3">
        {{ delDia.salidas.length }} {{ delDia.salidas.length === 1 ? "salida" : "salidas" }} con GPS<template v-if="delDia.sinGps > 0"> · {{ delDia.sinGps }} sin lecturas</template><template v-if="telefonosDelDia.length"> · {{ telefonosDelDia.length }} {{ telefonosDelDia.length === 1 ? "trayectoria" : "trayectorias" }} de teléfonos de prueba</template>. Las lecturas se guardan {{ DIAS_GUARDADAS }} días.
      </p>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Maximizado: el mapa crece hasta llenar lo que dejan los controles (nunca menos de 18rem). -->
    <div class="grid grid-cols-1 gap-4 @4xl:grid-cols-[minmax(0,1fr)_22rem]" :class="maximizado ? 'min-h-0 flex-1 grid-rows-[1fr_auto] @4xl:grid-rows-[1fr]' : ''">
      <div class="flex min-w-0 flex-col gap-3">
        <!-- La clase dinámica va en el padre: Vue reescribiría el class del contenedor de Leaflet y le borraría las clases que él añade. -->
        <div class="relative" :class="{ 'mapa-maximizado min-h-[18rem] flex-1': maximizado }">
          <div ref="mapaEl" class="mapa-reproduccion rounded-border border border-surface-200" role="application" aria-label="Mapa de la trayectoria" />
          <p v-if="(!trayectoria && !telefono) || puntos.length === 0" class="pointer-events-none absolute inset-x-0 top-1/2 z-[500] mx-auto w-fit max-w-[90%] -translate-y-1/2 rounded-border bg-surface-0/90 px-3 py-2 text-center text-sm shadow">
            <template v-if="cargando">Cargando la trayectoria…</template>
            <template v-else-if="trayectoria">Esta salida no tiene lecturas GPS guardadas.</template>
            <template v-else-if="telefono">Esta trayectoria del teléfono no tiene lecturas.</template>
            <template v-else>Elija un día y una salida para reproducir su trayectoria.</template>
          </p>
        </div>

        <div v-if="puntos.length > 0" class="panel flex flex-col gap-3" aria-label="Controles de reproducción">
          <div class="flex items-center gap-2">
            <Button rounded class="tap-target shrink-0" :aria-label="reproduciendo ? 'Pausar' : 'Reproducir'" @click="alternar">
              <icon :name="reproduciendo ? 'pause' : 'play-arrow'" class="text-inherit" />
            </Button>
            <Button text rounded class="tap-target shrink-0" aria-label="Volver al inicio" @click="alInicio"><icon name="skip-previous" /></Button>
            <span class="shrink-0 text-sm font-semibold tabular-nums" aria-live="off">{{ horaSegundosDe(t) }}</span>
            <Slider v-model="t" :min="inicio" :max="fin" :step="1" class="mx-2 min-w-0 flex-1" aria-label="Momento de la trayectoria" />
          </div>
          <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <SelectButton v-model="velocidad" :options="VELOCIDADES" option-label="label" option-value="value" :allow-empty="false" size="small" aria-label="Velocidad de reproducción" />
            <label class="flex cursor-pointer items-center gap-2 text-sm text-muted-color">
              <ToggleSwitch v-model="seguir" input-id="seguir-bus" />
              <span>Seguir al bus</span>
            </label>
          </div>
        </div>
      </div>

      <aside class="flex min-h-0 flex-col gap-3" :class="maximizado ? 'overflow-y-auto' : ''">
        <article v-if="trayectoria || telefono" class="panel flex flex-col gap-2" :aria-label="trayectoria ? 'Detalle de la salida' : 'Detalle del teléfono'">
          <header class="flex items-start justify-between gap-2">
            <div v-if="trayectoria" class="min-w-0">
              <p class="m-0 truncate text-sm font-semibold">{{ trayectoria.origen }} → {{ trayectoria.destino }}</p>
              <p class="m-0 text-xs text-muted-color">{{ trayectoria.empresa }} · {{ diaDe(trayectoria.partida) }}, salió {{ horaDe(trayectoria.partida) }}</p>
            </div>
            <div v-else-if="telefono" class="min-w-0">
              <p class="m-0 truncate text-sm font-semibold">Teléfono de prueba {{ telefono.dispositivo }}</p>
              <p class="m-0 text-xs text-muted-color">{{ diaDe(telefono.desde) }}, de {{ horaDe(telefono.desde) }} a {{ horaDe(telefono.hasta) }}</p>
            </div>
            <Tag v-if="posicion?.sinSenal" value="Sin señal" severity="warn" />
            <Tag v-else-if="puntos.length > 0" :value="reproduciendo ? `×${velocidad}` : 'En pausa'" :severity="reproduciendo ? 'success' : 'secondary'" />
          </header>
          <ProgressBar v-if="puntos.length > 0" :value="Math.round(progreso * 100)" :show-value="false" style="height: 0.5rem" />
          <dl class="m-0 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <template v-if="posicion">
              <dt class="text-muted-color">Hora</dt>
              <dd class="m-0 tabular-nums">{{ horaSegundosDe(t) }}</dd>
              <dt class="text-muted-color">Velocidad</dt>
              <dd class="m-0">{{ posicion.velocidadKmh.toFixed(0) }} km/h</dd>
              <dt class="text-muted-color">Recorrido</dt>
              <dd class="m-0">{{ posicion.km.toFixed(1) }} de {{ kmTotal.toFixed(1) }} km (GPS)</dd>
            </template>
            <dt class="text-muted-color">Lecturas</dt>
            <dd class="m-0">{{ puntos.length }}<template v-if="puntos.length > 0"> · {{ horaDe(inicio) }} a {{ horaDe(fin) }}</template></dd>
            <template v-if="trayectoria">
              <dt class="text-muted-color">Llegada programada</dt>
              <dd class="m-0">{{ trayectoria.llegadaProgramada ? horaDe(trayectoria.llegadaProgramada) : "—" }}</dd>
              <dt class="text-muted-color">Bus</dt>
              <dd class="m-0">
                {{ trayectoria.bus }}<template v-if="trayectoria.placa"> · {{ trayectoria.placa }}</template>
              </dd>
              <dt class="text-muted-color">Piloto</dt>
              <dd class="m-0">{{ trayectoria.piloto ?? "Sin asignar" }}</dd>
              <dt class="text-muted-color">Ruta</dt>
              <dd class="m-0">{{ trayectoria.rutaNombre }}</dd>
            </template>
          </dl>
        </article>
        <p v-if="telefono" class="m-0 text-xs text-muted-color">Lo recorrido sale de las lecturas que envió el teléfono de prueba. Más de 5 min sin lecturas cierra una trayectoria y la lectura siguiente empieza otra; más de 5 min detenido la cierra al detenerse y la siguiente empieza al arrancar.</p>
        <p v-else class="m-0 text-xs text-muted-color">Lo recorrido sale de las lecturas que envió el GPS del bus, desde media hora antes de su salida hasta unas horas después de su llegada programada (sin entrar en su siguiente salida). La raya gris marca un tramo sin señal de más de 5 min; la discontinua de color, la ruta planificada.</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { useRoute, useRouter } from "vue-router";
import { fetchTrayectoria, fetchTrayectoriaDeTelefono, fetchTrayectorias } from "@/core/seguimiento/api";
import { avanzar, kmAcumulados, partirPorHuecos, posicionEn, recorridoHasta } from "@/core/seguimiento/reproduccion";
import type { PuntoGps, RespuestaTrayectorias, Trayectoria, TrayectoriaDeTelefono } from "@/core/seguimiento/types";
import { aDia } from "@/core/salida/filtro";
import { HttpError } from "@/core/http";
import { COLOR_TELEFONO, colorEmpresa } from "./colores";
import { ORIGEN_COORDENADA, diaDe, horaDe, horaSegundosDe } from "./formato";
import { iconoBus } from "./marcador";

/** Lo que conserva la purga (`PurgarLecturasGps::ANTIGUEDAD`). */
const DIAS_GUARDADAS = 90;
const VELOCIDADES = [10, 30, 60, 120, 300].map((value) => ({ label: `×${value}`, value }));
/** Centro aproximado de Guatemala. */
const CENTRO: L.LatLngTuple = [15.4, -90.2];
/** Cada cuánto se redibuja como mucho la línea recorrida al reproducir (ms). */
const REDIBUJO_MS = 50;

const route = useRoute();
const router = useRouter();
const mapaEl = ref<HTMLElement | null>(null);
const hoy = new Date();
const dia = ref<Date>(new Date());
const delDia = ref<RespuestaTrayectorias | null>(null);
const cargandoDia = ref(false);
/** La opción elegida: `s:<salida>` o `t:<desde>:<dispositivo>`. */
const elegida = ref<string | null>(null);
/** Lo abierto: la trayectoria de una salida o la de un teléfono de prueba (nunca las dos). */
const trayectoria = ref<Trayectoria | null>(null);
const telefono = ref<TrayectoriaDeTelefono | null>(null);
const cargando = ref(false);
const error = ref("");
const t = ref(0);
const reproduciendo = ref(false);
const velocidad = ref(60);
const seguir = ref(true);
const maximizado = ref(false);

let mapa: L.Map | null = null;
let capaFija: L.LayerGroup | null = null;
let linea: L.Polyline | null = null;
let marcador: L.Marker | null = null;
let observador: ResizeObserver | undefined;
let cuadro = 0;
let ultimoCuadro = 0;
let ultimoDibujo = 0;
let cargaDia = 0;
let cargaSalida = 0;

const puntos = computed<PuntoGps[]>(() => trayectoria.value?.puntos ?? telefono.value?.puntos ?? []);
const km = computed(() => kmAcumulados(puntos.value));
const kmTotal = computed(() => km.value[km.value.length - 1] ?? 0);
const inicio = computed(() => puntos.value[0]?.[0] ?? 0);
const fin = computed(() => puntos.value[puntos.value.length - 1]?.[0] ?? 0);
const posicion = computed(() => posicionEn(puntos.value, km.value, t.value));
const progreso = computed(() => (fin.value > inicio.value ? (t.value - inicio.value) / (fin.value - inicio.value) : 0));
const color = computed(() => (trayectoria.value ? colorEmpresa(trayectoria.value.empresaId, trayectoria.value.empresa) : telefono.value ? COLOR_TELEFONO : "#115e59"));
const telefonosDelDia = computed(() => delDia.value?.telefonos ?? []);
const claveSalida = (id: number) => `s:${id}`;
const claveTelefono = (dispositivo: string, desde: number) => `t:${desde}:${dispositivo}`;
const opciones = computed(() =>
  [
    ...(delDia.value?.salidas ?? []).map((s) => ({ id: claveSalida(s.salidaId), t: s.partida, etiqueta: `${horaDe(s.partida)} · ${s.origen} → ${s.destino} · bus ${s.bus} · ${s.lecturas} lecturas` })),
    ...telefonosDelDia.value.map((x) => ({ id: claveTelefono(x.dispositivo, x.desde), t: x.desde, etiqueta: `${horaDe(x.desde)} a ${horaDe(x.hasta)} · teléfono de prueba ${x.dispositivo} · ${x.lecturas} lecturas` })),
  ].sort((a, b) => a.t - b.t),
);

const escapar = (s: string) => s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
const mensajeDe = (e: unknown) => (e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las salidas (salida.ver)." : e instanceof Error ? e.message : String(e));

async function cargarDia() {
  const mia = ++cargaDia;
  cargandoDia.value = true;
  try {
    const r = await fetchTrayectorias(aDia(dia.value));
    if (mia !== cargaDia) return;
    delDia.value = r;
    error.value = "";
  } catch (e) {
    if (mia === cargaDia) error.value = mensajeDe(e);
  } finally {
    if (mia === cargaDia) cargandoDia.value = false;
  }
}

const abrirSalida = (id: number) => abrir(fetchTrayectoria(id).then((salida) => ({ salida })));
const abrirTelefono = (dispositivo: string, desde: number) => abrir(fetchTrayectoriaDeTelefono(dispositivo, desde).then((tel) => ({ telefono: tel })));

async function abrir(pedido: Promise<{ salida: Trayectoria } | { telefono: TrayectoriaDeTelefono }>) {
  const mia = ++cargaSalida;
  pausar();
  cargando.value = true;
  try {
    const r = await pedido;
    if (mia !== cargaSalida) return;
    trayectoria.value = "salida" in r ? r.salida : null;
    telefono.value = "telefono" in r ? r.telefono : null;
    elegida.value = "salida" in r ? claveSalida(r.salida.salidaId) : claveTelefono(r.telefono.dispositivo, r.telefono.desde);
    t.value = puntos.value[0]?.[0] ?? 0;
    error.value = "";
    dibujarFijo();
    dibujarMovil(true);
    enfocar();
  } catch (e) {
    if (mia === cargaSalida) error.value = mensajeDe(e);
  } finally {
    if (mia === cargaSalida) cargando.value = false;
  }
}

function alElegir() {
  const clave = elegida.value;
  if (!clave) return;
  if (clave.startsWith("s:")) {
    const id = Number(clave.slice(2));
    void router.replace({ query: { salida: String(id) } });
    void abrirSalida(id);
    return;
  }
  const [, desde = "", ...nombre] = clave.split(":");
  const dispositivo = nombre.join(":");
  void router.replace({ query: { dispositivo, desde } });
  void abrirTelefono(dispositivo, Number(desde));
}

/** Ruta planificada, estaciones y todo lo recorrido (tenue, con los huecos sin señal en gris). Un teléfono no tiene ruta. */
function dibujarFijo() {
  if (!capaFija || (!trayectoria.value && !telefono.value)) return;
  capaFija.clearLayers();
  const tr = trayectoria.value;
  if (tr && tr.trazado.length > 1) L.polyline(tr.trazado, { color: color.value, weight: 2, opacity: 0.5, dashArray: "6 6", interactive: false }).addTo(capaFija);
  for (const e of tr?.estaciones ?? []) {
    const exacta = e.origen === "gps";
    L.circleMarker([e.lat, e.lng], { radius: 4, color: color.value, weight: 2, fillColor: exacta ? color.value : "#fff", fillOpacity: 1 })
      .bindTooltip(`${escapar(e.nombre)}${exacta ? "" : `<br><small>${escapar(ORIGEN_COORDENADA[e.origen] ?? "")}</small>`}`)
      .addTo(capaFija);
  }
  const { tramos, huecos } = partirPorHuecos(puntos.value);
  for (const tramo of tramos) L.polyline(tramo, { color: color.value, weight: 4, opacity: 0.25, interactive: false }).addTo(capaFija);
  for (const h of huecos) L.polyline(h, { color: "#64748b", weight: 2, opacity: 0.8, dashArray: "2 6", interactive: false }).addTo(capaFija);
}

/** Lo recorrido hasta `t` y el bus. `forzar`: aunque no haya pasado el intervalo de redibujo. */
function dibujarMovil(forzar = false) {
  if (!mapa) return;
  const p = posicion.value;
  if (!p) {
    linea?.remove();
    marcador?.remove();
    linea = marcador = null;
    return;
  }
  const ahora = performance.now();
  if (!forzar && ahora - ultimoDibujo < REDIBUJO_MS) return;
  ultimoDibujo = ahora;

  const recorrido = recorridoHasta(puntos.value, p);
  if (!linea) linea = L.polyline(recorrido, { color: color.value, weight: 4, opacity: 0.95, interactive: false }).addTo(mapa);
  else linea.setStyle({ color: color.value }).setLatLngs(recorrido);

  const icono = iconoBus(color.value, p.rumbo, { activo: true, quieto: p.sinSenal || p.velocidadKmh < 3 });
  if (!marcador) marcador = L.marker([p.lat, p.lng], { icon: icono, keyboard: false, zIndexOffset: 100 }).addTo(mapa);
  else marcador.setLatLng([p.lat, p.lng]).setIcon(icono);

  if (seguir.value && reproduciendo.value && !mapa.getBounds().pad(-0.15).contains([p.lat, p.lng])) mapa.panTo([p.lat, p.lng], { animate: true, duration: 0.4 });
}

function enfocar() {
  if (!mapa || (!trayectoria.value && !telefono.value)) return;
  const todos = [...puntos.value.map((p) => [p[1], p[2]] as [number, number]), ...(trayectoria.value?.trazado ?? [])];
  // La de un teléfono puede ser de unas calles: se acerca más.
  if (todos.length) mapa.fitBounds(L.latLngBounds(todos), { padding: [30, 30], maxZoom: trayectoria.value ? 13 : 16 });
}

function cuadroSiguiente(ms: number) {
  const dt = ultimoCuadro ? (ms - ultimoCuadro) / 1000 : 0;
  ultimoCuadro = ms;
  if (reproduciendo.value) {
    t.value = avanzar(puntos.value, t.value, dt * velocidad.value);
    if (t.value >= fin.value) pausar();
  }
  cuadro = requestAnimationFrame(cuadroSiguiente);
}

function reproducir() {
  if (puntos.value.length === 0) return;
  if (t.value >= fin.value) t.value = inicio.value;
  reproduciendo.value = true;
}

function pausar() {
  reproduciendo.value = false;
}

const alternar = () => (reproduciendo.value ? pausar() : reproducir());

function alInicio() {
  t.value = inicio.value;
  dibujarMovil(true);
}

// Al reproducir, el redibujo va con el intervalo; al mover la barra en pausa, enseguida.
watch(t, () => dibujarMovil(!reproduciendo.value));
watch(reproduciendo, () => dibujarMovil(true));

function alternarMaximizado() {
  maximizado.value = !maximizado.value;
  document.body.style.overflow = maximizado.value ? "hidden" : "";
}

/** Espacio reproduce o pausa, salvo sobre un control (un botón enfocado ya lo hace con su propio clic). Esc sale de pantalla completa. */
const alTeclear = (ev: KeyboardEvent) => {
  if (ev.key === "Escape" && maximizado.value) {
    alternarMaximizado();
    return;
  }
  const libre = ev.target === document.body || (ev.target instanceof Node && !!mapaEl.value?.contains(ev.target));
  if (ev.key === " " && libre && puntos.value.length > 0) {
    ev.preventDefault();
    alternar();
  }
};

onMounted(async () => {
  if (!mapaEl.value) return;
  mapa = L.map(mapaEl.value, { center: CENTRO, zoom: 7, zoomControl: true });
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(mapa);
  capaFija = L.layerGroup().addTo(mapa);
  observador = new ResizeObserver(() => mapa?.invalidateSize());
  observador.observe(mapaEl.value);
  window.addEventListener("keydown", alTeclear);
  cuadro = requestAnimationFrame(cuadroSiguiente);

  const id = Number(route.query.salida);
  const dispositivo = typeof route.query.dispositivo === "string" ? route.query.dispositivo : "";
  const desde = Number(route.query.desde);
  if (Number.isInteger(id) && id > 0) await abrirSalida(id);
  else if (dispositivo && Number.isInteger(desde) && desde > 0) await abrirTelefono(dispositivo, desde);
  // El día de la lista es el de lo abierto.
  const empieza = trayectoria.value?.partida ?? telefono.value?.desde;
  if (empieza) dia.value = new Date(empieza * 1000);
  void cargarDia();
});

onBeforeUnmount(() => {
  cancelAnimationFrame(cuadro);
  window.removeEventListener("keydown", alTeclear);
  document.body.style.overflow = "";
  observador?.disconnect();
  mapa?.remove();
  mapa = capaFija = linea = marcador = null;
});
</script>

<style>
.mapa-reproduccion {
  height: clamp(22rem, 60dvh, 44rem);
  width: 100%;
  z-index: 0;
}
.mapa-maximizado .mapa-reproduccion {
  height: 100%;
}
</style>
