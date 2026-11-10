<!--
  Reproducción de la trayectoria GPS de una salida (ADR-050), ruta `/seguimiento/reproducir` (`?salida=` abre una).
  Se elige el día y una de sus salidas con lecturas guardadas; el mapa dibuja lo ya reproducido
  en raya de color (como la estela del mapa en vivo) y la ruta planificada en línea fina con sus estaciones, y el bus avanza con el reloj a la velocidad elegida (×10 a ×300). La barra
  lleva a cualquier momento. Un hueco de más de 5 min sin lecturas se dibuja en raya gris y se salta al reproducir.
  «Seguir al bus» mueve el mapa cuando el bus se sale de la vista.
  Temporal: también las trayectorias de los teléfonos de prueba (`?dispositivo=&desde=`), sin salida: más de 5 min
  sin lecturas o detenido cierra una y empieza otra.
  Contenido angosto: mapa, controles y detalle apilados; desde 56rem de ancho: el detalle a la derecha.
  Maximizar (o Esc para salir), como en el mapa en vivo: día y salida, mapa, controles y detalle cubren toda la pantalla.
  Con `salida.eliminar`, la ✕ de cada opción borra su trayectoria de la base (las lecturas GPS, tras confirmar) y la quita de la lista.
-->
<template>
  <div class="@container flex flex-col gap-4" :class="maximizado ? 'fixed inset-0 z-[900] overflow-auto bg-surface-0 p-3' : ''">
    <!-- Angosto: el botón de maximizar va junto al día y la salida debajo; ancho: los tres en una fila. -->
    <section class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 @xl:grid-cols-[12rem_minmax(0,28rem)_minmax(0,1fr)] @xl:items-end">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Día</span>
        <DatePicker v-model="dia" date-format="dd/mm/yy" show-icon :max-date="hoy" :manual-input="false" fluid input-id="dia-trayectorias" @update:model-value="cargarDia" />
      </label>
      <label class="col-span-2 flex flex-col gap-1 @xl:col-span-1">
        <span class="text-xs font-medium text-muted-color">{{ telefonosDelDia.length ? "Salida o teléfono con GPS" : "Salida con GPS" }}</span>
        <Select v-model="elegida" :options="opciones" option-label="etiqueta" option-value="id" filter :loading="cargandoDia" :placeholder="cargandoDia ? 'Cargando…' : opciones.length ? 'Elija una' : 'Ninguna salida con GPS este día'" :disabled="!cargandoDia && opciones.length === 0" fluid @change="alElegir">
          <!-- La ✕ borra la trayectoria de la base. El Select elige en mousedown: la ✕ lo detiene. -->
          <template #option="{ option }">
            <div class="flex w-full min-w-0 items-center gap-2">
              <span class="min-w-0 flex-1 truncate" :title="option.etiqueta">{{ option.etiqueta }}</span>
              <Button v-if="puedeBorrar" text rounded size="small" severity="danger" class="tap-target -my-1 shrink-0" aria-label="Borrar esta trayectoria" title="Borrar esta trayectoria" @mousedown.stop.prevent @click.stop="borrar(option)">
                <icon name="close" />
              </Button>
            </div>
          </template>
        </Select>
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
        <!-- La clase dinámica va en el padre: Vue reescribiría el class del contenedor de MapLibre y le borraría las clases que él añade. -->
        <div class="relative" :class="{ 'mapa-maximizado min-h-[18rem] flex-1': maximizado }">
          <div ref="mapaEl" class="mapa-reproduccion rounded-border border border-surface-200" role="application" aria-label="Mapa de la trayectoria" />
          <p v-if="(!trayectoria && !telefono) || puntos.length === 0" class="pointer-events-none absolute inset-x-0 top-1/2 z-[500] mx-auto w-fit max-w-[90%] -translate-y-1/2 rounded-border bg-surface-0/90 px-3 py-2 text-center text-sm shadow">
            <template v-if="cargando">Cargando la trayectoria…</template>
            <template v-else-if="trayectoria">Esta salida no tiene lecturas GPS guardadas.</template>
            <template v-else-if="telefono">Esta trayectoria del teléfono no tiene lecturas.</template>
            <template v-else>Elija un día y una salida para reproducir su trayectoria.</template>
          </p>
        </div>

        <div v-if="puntos.length > 0" class="flex flex-col gap-3" aria-label="Controles de reproducción">
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
        <article v-if="trayectoria || telefono" class="flex flex-col gap-2" :aria-label="trayectoria ? 'Detalle de la salida' : 'Detalle del teléfono'">
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
              <dt class="text-muted-color">Promedio</dt>
              <dd class="m-0">{{ kmhDe(promedios.desdeArranqueKmh) }} · en marcha {{ kmhDe(promedios.enMarchaKmh) }}</dd>
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
        <p v-else class="m-0 text-xs text-muted-color">Lo recorrido sale de las lecturas que envió el GPS del bus, desde media hora antes de su salida hasta unas horas después de su llegada programada (sin entrar en su siguiente salida). La raya de color es lo ya reproducido; la gris, un tramo sin señal de más de 5 min; la línea fina de color, la ruta planificada.</p>
        <p v-if="puntos.length > 0" class="m-0 text-xs text-muted-color">El promedio divide lo recorrido desde que arrancó entre el tiempo transcurrido, con las paradas; el de en marcha no cuenta lo detenido (menos de {{ DETENIDO_KMH }} km/h) ni los tramos sin señal.</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Map as MapaLibre, Marker } from "maplibre-gl";
import { useRoute, useRouter } from "vue-router";
import { useConfirm } from "primevue/useconfirm";
import { borrarTrayectoria, borrarTrayectoriaDeTelefono, fetchTrayectoria, fetchTrayectoriaDeTelefono, fetchTrayectorias } from "@/core/seguimiento/api";
import { useAccesoStore } from "@/core/acceso/store";
import { notify } from "@/core/notify";
import { DETENIDO_KMH, avanzar, enMarchaAcumulado, kmAcumulados, partirPorHuecos, posicionEn, promediosEn, recorridoHasta } from "@/core/seguimiento/reproduccion";
import type { PuntoGps, RespuestaTrayectorias, Trayectoria, TrayectoriaDeTelefono } from "@/core/seguimiento/types";
import { aDia } from "@/core/salida/filtro";
import { HttpError } from "@/core/http";
import { BLANCO, GRIS_LUGAR, asegurarCapas, encuadrar, fijarDatos, globoAlPasar } from "@/shared/mapa/capas";
import { aLngLat, coleccion, dentroDe, linea, lineas, punto, type LatLng } from "@/shared/mapa/geo";
import { maplibregl } from "@/shared/mapa/maplibre";
import { useMapa } from "@/shared/mapa/useMapa";
import { COLOR_TELEFONO, colorEmpresa, oscurecer } from "./colores";
import { ORIGEN_COORDENADA, diaDe, horaDe, horaSegundosDe } from "./formato";
import { elementoBus, pintarBus } from "./marcador";

/** Lo que conserva la purga (`PurgarLecturasGps::ANTIGUEDAD`). */
const DIAS_GUARDADAS = 90;
const VELOCIDADES = [10, 30, 60, 120, 300].map((value) => ({ label: `×${value}`, value }));
/** Cada cuánto se redibuja como mucho la línea recorrida al reproducir (ms). */
const REDIBUJO_MS = 50;

const route = useRoute();
const router = useRouter();
const confirm = useConfirm();
const acceso = useAccesoStore();
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

let marcador: Marker | null = null;
let cuadro = 0;
let ultimoCuadro = 0;
let ultimoDibujo = 0;
let cargaDia = 0;
let cargaSalida = 0;

const { mapa, listo } = useMapa(mapaEl, (m) => {
  prepararCapas(m);
  globoAlPasar(m, "rep-estaciones", (p) => `${escapar(String(p.nombre ?? ""))}${p.exacta ? "" : `<br><small>${escapar(String(p.nota ?? ""))}</small>`}`);
  dibujarFijo();
  dibujarMovil(true);
  enfocar(false);
});

const puntos = computed<PuntoGps[]>(() => trayectoria.value?.puntos ?? telefono.value?.puntos ?? []);
const km = computed(() => kmAcumulados(puntos.value));
const kmTotal = computed(() => km.value[km.value.length - 1] ?? 0);
const inicio = computed(() => puntos.value[0]?.[0] ?? 0);
const fin = computed(() => puntos.value[puntos.value.length - 1]?.[0] ?? 0);
const posicion = computed(() => posicionEn(puntos.value, km.value, t.value));
const enMarcha = computed(() => enMarchaAcumulado(puntos.value));
const promedios = computed(() => promediosEn(puntos.value, km.value, enMarcha.value, t.value));
const kmhDe = (v: number | null) => (v === null ? "—" : `${v.toFixed(0)} km/h`);
const progreso = computed(() => (fin.value > inicio.value ? (t.value - inicio.value) / (fin.value - inicio.value) : 0));
const color = computed(() => (trayectoria.value ? colorEmpresa(trayectoria.value.empresaId, trayectoria.value.empresa) : telefono.value ? COLOR_TELEFONO : "#115e59"));
const colorRaya = computed(() => oscurecer(color.value, 0.4));
const telefonosDelDia = computed(() => delDia.value?.telefonos ?? []);
/** La ✕ de cada opción: borrar exige `salida.eliminar` (las de teléfonos, en todas las empresas; lo decide el backend). */
const puedeBorrar = computed(() => acceso.puede("salida.eliminar"));
const claveSalida = (id: number) => `s:${id}`;
const claveTelefono = (dispositivo: string, desde: number) => `t:${desde}:${dispositivo}`;
const deClave = (clave: string): { salida: number } | { dispositivo: string; desde: number } => {
  if (clave.startsWith("s:")) return { salida: Number(clave.slice(2)) };
  const [, desde = "", ...nombre] = clave.split(":");
  return { dispositivo: nombre.join(":"), desde: Number(desde) };
};
/** Lo que pide la URL (`?salida=` o `?dispositivo=&desde=`); null si nada. */
const claveDeRuta = computed<string | null>(() => {
  const id = Number(route.query.salida);
  if (Number.isInteger(id) && id > 0) return claveSalida(id);
  const dispositivo = typeof route.query.dispositivo === "string" ? route.query.dispositivo : "";
  const desde = Number(route.query.desde);
  return dispositivo && Number.isInteger(desde) && desde > 0 ? claveTelefono(dispositivo, desde) : null;
});
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
  const o = deClave(clave);
  if ("salida" in o) {
    void router.replace({ query: { salida: String(o.salida) } });
    void abrirSalida(o.salida);
    return;
  }
  void router.replace({ query: { dispositivo: o.dispositivo, desde: String(o.desde) } });
  void abrirTelefono(o.dispositivo, o.desde);
}

/** Borra de la base la trayectoria de una opción (las lecturas GPS; la salida se queda) y la quita de la lista. */
function borrar(op: { id: string; etiqueta: string }) {
  const o = deClave(op.id);
  confirm.require({
    header: "Borrar trayectoria",
    message: `Se borrarán de la base de datos las lecturas GPS de «${op.etiqueta}»${"salida" in o ? " (la salida se queda)" : ""}. No se puede deshacer.`,
    acceptProps: { label: "Borrar", severity: "danger" },
    rejectProps: { label: "Cancelar", severity: "secondary" },
    accept: async () => {
      try {
        const r = await ("salida" in o ? borrarTrayectoria(o.salida) : borrarTrayectoriaDeTelefono(o.dispositivo, o.desde));
        quitar(op.id);
        notify.success(`Trayectoria borrada (${r.borradas} ${r.borradas === 1 ? "lectura" : "lecturas"}).`);
      } catch (e) {
        notify.error(e instanceof Error ? e.message : String(e));
      }
    },
  });
}

/** Quita la opción de la lista del día; si era la abierta, la cierra. */
function quitar(clave: string) {
  const d = delDia.value;
  const o = deClave(clave);
  if (d && "salida" in o && d.salidas.some((s) => s.salidaId === o.salida)) delDia.value = { ...d, salidas: d.salidas.filter((s) => s.salidaId !== o.salida), sinGps: d.sinGps + 1 };
  else if (d && !("salida" in o)) delDia.value = { ...d, telefonos: (d.telefonos ?? []).filter((x) => claveTelefono(x.dispositivo, x.desde) !== clave) };
  if (elegida.value === clave) cerrar();
}

/** Deja el mapa sin trayectoria, como al entrar. */
function cerrar() {
  ++cargaSalida;
  pausar();
  cargando.value = false;
  trayectoria.value = null;
  telefono.value = null;
  elegida.value = null;
  dibujarFijo();
  dibujarMovil(true);
  void router.replace({ query: {} });
}

/** Fuentes vacías y capas, de abajo arriba: ruta planificada, estaciones, huecos sin señal y lo recorrido. El bus va encima, en el DOM. */
function prepararCapas(m: MapaLibre) {
  for (const id of ["rep-trazado", "rep-estaciones", "rep-huecos", "rep-recorrido"]) fijarDatos(m, id, coleccion([]));
  asegurarCapas(m, [
    // Continua y fina: la raya es de la trayectoria.
    { id: "rep-trazado", type: "line", source: "rep-trazado", paint: { "line-color": ["get", "color"], "line-width": 2, "line-opacity": 0.45 } },
    { id: "rep-estaciones", type: "circle", source: "rep-estaciones", paint: { "circle-radius": 3, "circle-color": ["case", ["get", "exacta"], ["get", "color"], BLANCO], "circle-stroke-color": ["get", "color"], "circle-stroke-width": 2 } },
    // Punteada: 2 px de trazo y 6 de hueco (MapLibre mide la raya en anchos de línea).
    { id: "rep-huecos", type: "line", source: "rep-huecos", paint: { "line-color": GRIS_LUGAR, "line-width": 2, "line-opacity": 0.8, "line-dasharray": [1, 3] } },
    // En raya, como la estela del mapa en vivo: 6 px y 6 px con línea de 3 px.
    { id: "rep-recorrido", type: "line", source: "rep-recorrido", paint: { "line-color": ["get", "color"], "line-width": 3, "line-dasharray": [2, 2] } },
  ]);
}

/** Ruta planificada, estaciones y los huecos sin señal (en gris). Lo recorrido se dibuja al reproducir, no antes. Un teléfono no tiene ruta. */
function dibujarFijo() {
  const m = mapa.value;
  if (!m || !listo.value) return;
  const tr = trayectoria.value;
  fijarDatos(m, "rep-trazado", lineas([tr ? linea(tr.trazado, { color: color.value }) : null]));
  fijarDatos(m, "rep-estaciones", coleccion((tr?.estaciones ?? []).map((e) => punto([e.lat, e.lng], { color: color.value, exacta: e.origen === "gps", nombre: e.nombre, nota: ORIGEN_COORDENADA[e.origen] ?? "" }))));
  const huecos = trayectoria.value || telefono.value ? partirPorHuecos(puntos.value).huecos : [];
  fijarDatos(m, "rep-huecos", lineas(huecos.map((h) => linea(h))));
}

/** Lo recorrido hasta `t` y el bus. `forzar`: aunque no haya pasado el intervalo de redibujo. */
function dibujarMovil(forzar = false) {
  const m = mapa.value;
  if (!m || !listo.value) return;
  const p = posicion.value;
  if (!p) {
    fijarDatos(m, "rep-recorrido", coleccion([]));
    marcador?.remove();
    marcador = null;
    return;
  }
  const ahora = performance.now();
  if (!forzar && ahora - ultimoDibujo < REDIBUJO_MS) return;
  ultimoDibujo = ahora;

  // Más oscura que el color.
  fijarDatos(m, "rep-recorrido", lineas([linea(recorridoHasta(puntos.value, p), { color: colorRaya.value })]));

  if (!marcador) marcador = new maplibregl.Marker({ element: elementoBus("Bus en la reproducción") }).setLngLat(aLngLat([p.lat, p.lng])).addTo(m);
  else marcador.setLngLat(aLngLat([p.lat, p.lng]));
  pintarBus(marcador.getElement(), { color: color.value, rumbo: p.rumbo, activo: true, quieto: p.sinSenal || p.velocidadKmh < DETENIDO_KMH });

  if (seguir.value && reproduciendo.value && !dentroDe(m.getBounds().toArray() as [[number, number], [number, number]], [p.lat, p.lng], 0.15)) m.panTo(aLngLat([p.lat, p.lng]), { duration: 400 });
}

function enfocar(animar = true) {
  const m = mapa.value;
  if (!m || !listo.value || (!trayectoria.value && !telefono.value)) return;
  const todos: LatLng[] = [...puntos.value.map((p) => [p[1], p[2]] as LatLng), ...(trayectoria.value?.trazado ?? [])];
  // La de un teléfono puede ser de unas calles: se acerca más.
  encuadrar(m, todos, { margen: 30, maxZoom: trayectoria.value ? 12 : 15, animar });
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

/** Abre lo que pide la URL y pone la lista en su día; true si el día cambió. */
async function abrirDeRuta(clave: string): Promise<boolean> {
  const o = deClave(clave);
  await ("salida" in o ? abrirSalida(o.salida) : abrirTelefono(o.dispositivo, o.desde));
  const empieza = trayectoria.value?.partida ?? telefono.value?.desde;
  if (!empieza || aDia(new Date(empieza * 1000)) === aDia(dia.value)) return false;
  dia.value = new Date(empieza * 1000);
  return true;
}

// La página se conserva al cambiar la URL (`meta.conservar`). Lo elegido aquí ya la escribe; lo que llega
// de fuera (menú, historial) se abre. Al irse a otra ruta, la URL cambia antes de desmontarla: se ignora.
const nombreRuta = route.name;
watch(claveDeRuta, async (clave) => {
  if (route.name !== nombreRuta || clave === elegida.value) return;
  if (!clave) cerrar();
  else if (await abrirDeRuta(clave)) void cargarDia();
});

onMounted(async () => {
  window.addEventListener("keydown", alTeclear);
  cuadro = requestAnimationFrame(cuadroSiguiente);

  const clave = claveDeRuta.value;
  if (clave) await abrirDeRuta(clave);
  void cargarDia();
});

onBeforeUnmount(() => {
  cancelAnimationFrame(cuadro);
  window.removeEventListener("keydown", alTeclear);
  document.body.style.overflow = "";
  marcador?.remove();
  marcador = null;
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
