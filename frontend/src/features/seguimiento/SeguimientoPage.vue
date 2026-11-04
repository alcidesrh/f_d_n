<!--
  Mapa en tiempo real de buses en recorrido. Muestra las salidas en recorrido; mientras no haya GPS reales, la posición se infiere del
  cronograma (distancia, hora y velocidad media) y el navegador anima los
  buses con el reloj; la lista se refresca cada 30 s. Un bus con lectura GPS
  vigente se pinta tal cual la entrega el servidor. Filtros por empresa y
  salida (que enfoca el bus y su ruta).
  Contenido angosto: mapa y lista apilados; desde 56rem de ancho: lado a lado.
  Por defecto solo los buses que envían GPS; el interruptor "Solo con GPS" apagado muestra también los simulados.
  Con "Solo con GPS" no se dibujan las rutas ni sus enclaves: solo los buses y, en raya discontinua, la trayectoria
  que cada uno va dejando con sus lecturas (se guarda en el navegador mientras la página está abierta; "Actualizar" la borra
  y vuelve a empezar desde la lectura siguiente).
  Interruptor "Nombres de enclaves": chip con el nombre junto a cada punto (si no, solo al pasar el cursor).
  Maximizar (o Esc para salir): filtros, mapa y lista cubren toda la pantalla.
  Un bus con GPS ofrece «Reproducir lo recorrido»: su trayectoria guardada en `/seguimiento/reproducir` (ADR-050).
  Temporal: los teléfonos de prueba (nombre acordado en vez de código de bus; solo para quien ve todas las empresas) se
  pintan aparte, en gris oscuro y sin filtros, con su estela y «Reproducir» de la trayectoria en curso.
-->
<template>
  <div class="@container flex flex-col gap-4" :class="maximizado ? 'fixed inset-0 z-[900] overflow-auto bg-surface-0 p-3' : ''">
    <!-- <Toolbar>
      <template #start><PageHead /></template>
      <template #end> -->
    <span class="flex items-center gap-2 text-xs text-muted-color ml-auto" aria-live="polite">
      <span class="inline-block size-2 rounded-full" :class="error ? 'bg-red-500' : 'bg-green-500'" />
      <template v-if="actualizado">Actualizado {{ horaDe(actualizado) }}</template>
      <template v-else>Cargando…</template>
      <Button size="small" text rounded class="tap-target" aria-label="Actualizar ahora" :loading="cargando" @click="reiniciarTrayectorias"><icon name="refresh" /></Button>
      <Button size="small" text rounded class="tap-target" :aria-label="maximizado ? 'Salir de pantalla completa' : 'Maximizar mapa'" :title="maximizado ? 'Salir de pantalla completa (Esc)' : 'Maximizar'" :aria-pressed="maximizado" @click="alternarMaximizado">
        <icon :name="maximizado ? 'fullscreen-exit' : 'fullscreen'" />
      </Button>
    </span>
    <!-- </template>
    </Toolbar> -->

    <!-- Mismas columnas que mapa + lista: el borde derecho de las empresas es el del mapa. -->
    <section class="panel grid grid-cols-1 gap-3 @4xl:grid-cols-[minmax(0,1fr)_22rem] @4xl:items-end">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <label class="flex w-full flex-col gap-1 @xl:w-[28rem]">
          <span class="text-xs font-medium text-muted-color">Salida</span>
          <Select v-model="filtro.salidaId" :options="opcionesSalida" option-label="etiqueta" option-value="id" show-clear filter placeholder="Todas" fluid :disabled="opcionesSalida.length === 0" @change="alCambiarSalida" />
        </label>
        <div v-if="(datos?.empresas.length ?? 0) > 1" class="flex flex-wrap items-center justify-end gap-2">
          <button v-for="e in datos?.empresas" :key="e.id" type="button" class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="filtro.empresaId === e.id ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 bg-transparent text-color'" :aria-pressed="filtro.empresaId === e.id" @click="alternarEmpresa(e.id)">
            {{ e.nombre }}
          </button>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-color">
        <label class="flex cursor-pointer items-center gap-2">
          <ToggleSwitch v-model="filtro.soloGps" input-id="solo-gps" @update:model-value="alCambiarFuente" />
          <span>Solo con GPS</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
          <ToggleSwitch v-model="mostrarNombres" input-id="nombres-enclaves" />
          <span>Nombres de enclaves</span>
        </label>
        <span class="flex items-center gap-2">
          <icon name="directions-bus-outline" />
          {{ visibles.length }} {{ visibles.length === 1 ? "bus" : "buses" }} {{ filtro.soloGps ? "con GPS" : "en recorrido" }}<template v-if="filtro.soloGps && simulados > 0"> · {{ simulados }} sin GPS</template>
        </span>
      </div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-else-if="datos && datos.sinTrazado > 0" severity="warn" :closable="false"> {{ datos.sinTrazado }} {{ datos.sinTrazado === 1 ? "salida no se muestra" : "salidas no se muestran" }} porque las estaciones de su ruta no tienen ubicación. </Message>

    <div class="grid grid-cols-1 gap-4 @4xl:grid-cols-[minmax(0,1fr)_22rem]" :class="maximizado ? 'min-h-0 flex-1 grid-rows-[minmax(18rem,1fr)_auto] @4xl:grid-rows-1' : ''">
      <!-- La clase dinámica va en el padre: Vue reescribiría el class del contenedor de Leaflet y le borraría las clases que él añade. -->
      <div class="relative min-h-0" :class="{ 'mapa-maximizado': maximizado }">
        <div ref="mapaEl" class="mapa-buses rounded-border border border-surface-200" role="application" aria-label="Mapa de buses en recorrido" />
        <p v-if="datos && !cargando && visibles.length === 0 && telefonos.length === 0" class="pointer-events-none absolute inset-x-0 top-1/2 z-[500] mx-auto w-fit -translate-y-1/2 rounded-border bg-surface-0/90 px-3 py-2 text-sm shadow">{{ filtro.soloGps ? "Ningún bus está enviando GPS" : "No hay buses en recorrido" }}{{ hayFiltro ? " con este filtro" : " en este momento" }}.<template v-if="filtro.soloGps && simulados > 0"> Apague «Solo con GPS» para ver los {{ simulados }} simulados.</template></p>
      </div>

      <aside class="flex min-h-0 flex-col gap-3" :class="maximizado ? 'overflow-y-auto' : ''">
        <article v-if="seleccionado" class="panel flex flex-col gap-2" aria-label="Detalle del bus">
          <header class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="m-0 truncate text-sm font-semibold">{{ seleccionado.origen }} → {{ seleccionado.destino }}</p>
              <p class="m-0 text-xs text-muted-color">{{ seleccionado.empresa }} · salió {{ horaDe(seleccionado.partida) }}</p>
            </div>
            <Tag :value="seleccionado.posicion.fuente === 'gps' ? 'GPS' : 'Simulada'" :severity="seleccionado.posicion.fuente === 'gps' ? 'success' : 'secondary'" />
          </header>
          <p v-if="!seleccionado.marcadaIniciada" class="m-0 text-xs text-muted-color">En el sistema figura «{{ seleccionado.estadoSistema }}»; se asume que ya salió por su hora y sus boletos vendidos.</p>
          <ProgressBar :value="Math.round((seleccionadoPos?.progreso ?? 0) * 100)" :show-value="false" style="height: 0.5rem" />
          <dl class="m-0 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <dt class="text-muted-color">Estado</dt>
            <dd class="m-0">{{ ETIQUETA_ESTADO[seleccionadoPos?.estado ?? "en_ruta"] }}</dd>
            <dt class="text-muted-color">Recorrido</dt>
            <dd class="m-0">{{ (seleccionadoPos?.km ?? 0).toFixed(0) }} de {{ seleccionado.kilometros.toFixed(0) }} km</dd>
            <dt class="text-muted-color">Velocidad</dt>
            <dd class="m-0">{{ (seleccionadoPos?.velocidadKmh ?? 0).toFixed(0) }} km/h</dd>
            <dt class="text-muted-color">Próxima estación</dt>
            <dd class="m-0">{{ seleccionadoProxima ? `${seleccionadoProxima.nombre} (${horaDe(seleccionadoProxima.llegada)}, ${duracion(seleccionadoProxima.llegada - ahora)})` : "—" }}</dd>
            <dt class="text-muted-color">Llegada estimada</dt>
            <dd class="m-0">{{ horaDe(seleccionado.llegadaEstimada) }}</dd>
            <dt class="text-muted-color">Bus</dt>
            <dd class="m-0">
              {{ seleccionado.bus }}<template v-if="seleccionado.placa"> · {{ seleccionado.placa }}</template>
            </dd>
            <dt class="text-muted-color">Piloto</dt>
            <dd class="m-0">{{ seleccionado.piloto ?? "Sin asignar" }}</dd>
            <dt class="text-muted-color">Ruta</dt>
            <dd class="m-0">{{ seleccionado.rutaNombre }}</dd>
          </dl>
          <RouterLink v-if="seleccionado.posicion.fuente === 'gps'" :to="{ name: 'seguimiento-reproducir', query: { salida: seleccionado.salidaId } }" class="flex items-center gap-1 self-start text-xs font-medium text-primary">
            <icon name="replay" sm />
            Reproducir lo recorrido
          </RouterLink>
        </article>

        <ul v-if="telefonos.length" class="m-0 flex list-none flex-col gap-1.5 p-0" aria-label="Teléfonos de prueba">
          <li v-for="x in telefonos" :key="x.dispositivo" class="flex items-center gap-2 rounded-border border border-dashed border-surface-300 p-2 text-sm">
            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 bg-transparent p-0 text-left" @click="enfocarTelefono(x)">
              <span class="inline-block size-3 shrink-0 rounded-full" :style="{ background: COLOR_TELEFONO }" />
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">Teléfono de prueba {{ x.dispositivo }}</span>
                <span class="block truncate text-xs text-muted-color">{{ x.posicion.velocidadKmh.toFixed(0) }} km/h · lectura {{ horaDe(x.posicion.instante) }} · desde {{ horaDe(x.desde) }}</span>
              </span>
            </button>
            <RouterLink :to="{ name: 'seguimiento-reproducir', query: { dispositivo: x.dispositivo, desde: x.desde } }" class="tap-target flex shrink-0 items-center justify-center text-primary" aria-label="Reproducir lo recorrido" title="Reproducir lo recorrido">
              <icon name="replay" />
            </RouterLink>
          </li>
        </ul>

        <ul class="m-0 flex list-none flex-col gap-1.5 p-0" :class="maximizado ? '' : 'max-h-[28rem] overflow-y-auto'" aria-label="Buses en recorrido">
          <li v-for="b in visibles" :key="b.salidaId">
            <button type="button" class="flex w-full items-center gap-2 rounded-border border bg-transparent p-2 text-left text-sm" :class="b.salidaId === seleccionadoId ? 'border-primary' : 'border-surface-200'" :aria-pressed="b.salidaId === seleccionadoId" @click="elegir(b.salidaId, true)">
              <span class="inline-block size-3 shrink-0 rounded-full" :style="{ background: colorEmpresa(b.empresaId, b.empresa) }" />
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">{{ b.origen }} → {{ b.destino }}</span>
                <span class="block truncate text-xs text-muted-color">{{ b.empresa }} · bus {{ b.bus }} · {{ horaDe(b.partida) }}</span>
              </span>
              <span class="shrink-0 text-xs tabular-nums text-muted-color">{{ Math.round((estimadas.get(b.salidaId)?.progreso ?? 0) * 100) }}%</span>
            </button>
          </li>
        </ul>
        <p v-if="!filtro.soloGps" class="m-0 text-xs text-muted-color">Las posiciones sin GPS son una simulación a partir de la hora de salida, la distancia de la ruta y una velocidad media. Se reemplazan por la lectura del GPS cuando el bus la envíe.</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { fetchBusesEnRecorrido } from "@/core/seguimiento/api";
import { FILTRO_VACIO, filtrarBuses, filtroVigente, salidasDisponibles } from "@/core/seguimiento/filtro";
import { estilosPorRuta, posicionEnArco, trazadoArqueado } from "@/core/seguimiento/arco";
import { posicionEn, proximaParada } from "@/core/seguimiento/posicion";
import type { BusEnRecorrido, FiltroSeguimiento, PosicionEstimada, RespuestaSeguimiento, TelefonoEnVivo } from "@/core/seguimiento/types";
import { HttpError } from "@/core/http";
import { COLOR_TELEFONO, colorEmpresa } from "./colores";
import { iconoBus } from "./marcador";
import { ETIQUETA_ESTADO, ORIGEN_COORDENADA, duracion, horaDe } from "./formato";

const REFRESCO_MS = 30_000;
const ANIMACION_MS = 1_000;
/** Centro aproximado de Guatemala. */
const CENTRO: L.LatLngTuple = [15.4, -90.2];

const mapaEl = ref<HTMLElement | null>(null);
const datos = ref<RespuestaSeguimiento | null>(null);
const cargando = ref(false);
const error = ref("");
const actualizado = ref<number | null>(null);
const filtro = reactive<FiltroSeguimiento>({ ...FILTRO_VACIO });
const seleccionadoId = ref<number | null>(null);
/** Reloj del servidor en segundos: el del navegador corregido con el desfase de la última respuesta. */
const ahora = ref(Math.floor(Date.now() / 1000));
const estimadas = ref(new Map<number, PosicionEstimada>());

let mapa: L.Map | null = null;
let capaRutas: L.LayerGroup | null = null;
let capaBuses: L.LayerGroup | null = null;
let capaTrayectorias: L.LayerGroup | null = null;
let capaTelefonos: L.LayerGroup | null = null;
let desfaseMs = 0;
let temporizador: ReturnType<typeof setInterval> | undefined;
let animacion: ReturnType<typeof setInterval> | undefined;
let carga = 0;
let observador: ResizeObserver | undefined;
const maximizado = ref(false);
const mostrarNombres = ref(false);
const marcadores = new Map<number, L.Marker>();
/** Lecturas GPS recibidas de cada salida desde que se abrió la página (o desde el último "Actualizar"). */
const trayectorias = new Map<number, { instante: number; puntos: [number, number][] }>();
/** Lo mismo de cada teléfono de prueba, en su trayectoria en curso. */
const estelas = new Map<string, { desde: number; instante: number; puntos: [number, number][] }>();

/** Curvatura y fase de guiones de cada ruta, fijas aunque el filtro cambie. */
const estilos = computed(() => estilosPorRuta((datos.value?.buses ?? []).map((b) => b.ruta)));
const arcoDe = (b: BusEnRecorrido) => estilos.value.get(b.ruta)?.k ?? 0;
const visibles = computed(() => filtrarBuses(datos.value?.buses ?? [], filtro));
const telefonos = computed<TelefonoEnVivo[]>(() => datos.value?.telefonos ?? []);
const hayFiltro = computed(() => filtro.empresaId !== null || filtro.salidaId !== null);
/** Buses en recorrido que el filtro "Solo con GPS" deja fuera. */
const simulados = computed(() => filtrarBuses(datos.value?.buses ?? [], { ...filtro, salidaId: null, soloGps: false }).length - filtrarBuses(datos.value?.buses ?? [], { ...filtro, salidaId: null }).length);
const opcionesSalida = computed(() => salidasDisponibles(datos.value?.buses ?? [], filtro.empresaId, filtro.soloGps).map((b) => ({ id: b.salidaId, etiqueta: `${horaDe(b.partida)} · ${b.origen} → ${b.destino} · bus ${b.bus}` })));
const seleccionado = computed<BusEnRecorrido | null>(() => visibles.value.find((b) => b.salidaId === seleccionadoId.value) ?? null);
const seleccionadoPos = computed(() => (seleccionado.value ? (estimadas.value.get(seleccionado.value.salidaId) ?? null) : null));
const seleccionadoProxima = computed(() => (seleccionado.value ? proximaParada(seleccionado.value, ahora.value) : null));

const iconoDe = (b: BusEnRecorrido, p: PosicionEstimada, activo: boolean): L.DivIcon => iconoBus(colorEmpresa(b.empresaId, b.empresa), p.rumbo, { activo, quieto: p.estado !== "en_ruta" });

const escapar = (s: string) => s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);

/** Suma la lectura GPS de cada bus a su trayectoria (una sola vez por lectura). */
function registrarTrayectorias(buses: BusEnRecorrido[]) {
  const vigentes = new Set<number>();
  for (const b of buses) {
    if (b.posicion.fuente !== "gps") continue;
    vigentes.add(b.salidaId);
    const t = trayectorias.get(b.salidaId) ?? { instante: -1, puntos: [] };
    trayectorias.set(b.salidaId, t);
    if (b.posicion.instante === t.instante) continue;
    t.instante = b.posicion.instante;
    const ultimo = t.puntos[t.puntos.length - 1];
    if (!ultimo || ultimo[0] !== b.posicion.lat || ultimo[1] !== b.posicion.lng) t.puntos.push([b.posicion.lat, b.posicion.lng]);
  }
  // La salida que ya no está en recorrido (o dejó de enviar GPS) pierde su trayectoria.
  for (const id of trayectorias.keys()) if (!vigentes.has(id)) trayectorias.delete(id);
}

function dibujarTrayectorias() {
  if (!capaTrayectorias) return;
  capaTrayectorias.clearLayers();
  if (!filtro.soloGps) return;
  for (const b of visibles.value) {
    const t = trayectorias.get(b.salidaId);
    if (!t || t.puntos.length < 2) continue;
    L.polyline(t.puntos, { color: colorEmpresa(b.empresaId, b.empresa), weight: 3, opacity: 0.9, dashArray: "6 6", interactive: false }).addTo(capaTrayectorias);
  }
}

/** Suma la lectura de cada teléfono de prueba a su estela; si empezó otra trayectoria, la estela vuelve a empezar. */
function registrarTelefonos(lista: TelefonoEnVivo[]) {
  const vigentes = new Set(lista.map((x) => x.dispositivo));
  for (const x of lista) {
    let e = estelas.get(x.dispositivo);
    if (!e || e.desde !== x.desde) {
      e = { desde: x.desde, instante: -1, puntos: [] };
      estelas.set(x.dispositivo, e);
    }
    if (x.posicion.instante === e.instante) continue;
    e.instante = x.posicion.instante;
    const ultimo = e.puntos[e.puntos.length - 1];
    if (!ultimo || ultimo[0] !== x.posicion.lat || ultimo[1] !== x.posicion.lng) e.puntos.push([x.posicion.lat, x.posicion.lng]);
  }
  for (const d of estelas.keys()) if (!vigentes.has(d)) estelas.delete(d);
}

/** Los teléfonos de prueba con su estela. Van aparte de los buses: sin empresa, no los afectan los filtros. */
function dibujarTelefonos() {
  if (!capaTelefonos) return;
  capaTelefonos.clearLayers();
  for (const x of telefonos.value) {
    const e = estelas.get(x.dispositivo);
    if (e && e.puntos.length > 1) L.polyline(e.puntos, { color: COLOR_TELEFONO, weight: 3, opacity: 0.9, dashArray: "6 6", interactive: false }).addTo(capaTelefonos);
    L.marker([x.posicion.lat, x.posicion.lng], { icon: iconoBus(COLOR_TELEFONO, x.posicion.rumbo, { quieto: x.posicion.estado !== "en_ruta" }), keyboard: true, title: `Teléfono de prueba ${x.dispositivo}`, zIndexOffset: 200 })
      .on("click", () => enfocarTelefono(x))
      .addTo(capaTelefonos);
  }
}

function enfocarTelefono(x: TelefonoEnVivo) {
  if (mapa) mapa.flyTo([x.posicion.lat, x.posicion.lng], Math.max(mapa.getZoom(), 15), { duration: 0.6 });
}

/** "Actualizar": borra lo dibujado y la trayectoria vuelve a empezar desde la lectura que llega. */
function reiniciarTrayectorias() {
  trayectorias.clear();
  estelas.clear();
  dibujarTrayectorias();
  dibujarTelefonos();
  void cargar(false);
}

function dibujarRutas() {
  if (!capaRutas) return;
  capaRutas.clearLayers();
  // Solo con GPS: el mapa muestra los buses y su trayectoria, no la ruta planificada.
  if (filtro.soloGps) return;
  const vistas = new Set<string>();
  const estaciones = new Set<string>();
  // Con un bus elegido solo se dibuja su ruta; si no, la de cada ruta una vez.
  const buses = seleccionado.value ? [seleccionado.value] : visibles.value;
  for (const b of buses) {
    if (vistas.has(b.ruta)) continue;
    vistas.add(b.ruta);
    const color = colorEmpresa(b.empresaId, b.empresa);
    const estilo = estilos.value.get(b.ruta);
    L.polyline(trazadoArqueado(b.trazado, estilo?.k ?? 0), { color, weight: 3, opacity: 0.85, dashArray: "6 6", dashOffset: String(estilo?.fase ?? 0) }).addTo(capaRutas);
    for (const e of b.estaciones) {
      const exacta = e.origen === "gps";
      const punto = L.circleMarker([e.lat, e.lng], { radius: 4, color, weight: 2, fillColor: exacta ? color : "#fff", fillOpacity: 1 });
      if (mostrarNombres.value) {
        // Una etiqueta por lugar, aunque lo compartan varias rutas.
        const clave = `${e.nombre}|${e.lat}|${e.lng}`;
        if (!estaciones.has(clave)) {
          estaciones.add(clave);
          punto.bindTooltip(escapar(e.nombre), { permanent: true, direction: "right", offset: [8, 0], className: "enclave-chip", interactive: false });
        }
      } else {
        punto.bindTooltip(`${escapar(e.nombre)}${exacta ? "" : `<br><small>${escapar(ORIGEN_COORDENADA[e.origen] ?? "")}</small>`}`, { className: "enclave-tip" });
      }
      punto.addTo(capaRutas);
    }
  }
}

function sincronizarBuses() {
  if (!capaBuses) return;
  const ids = new Set(visibles.value.map((b) => b.salidaId));
  for (const [id, m] of marcadores) {
    if (!ids.has(id)) {
      capaBuses.removeLayer(m);
      marcadores.delete(id);
    }
  }
  for (const b of visibles.value) {
    if (marcadores.has(b.salidaId)) continue;
    const p = estimadas.value.get(b.salidaId) ?? posicionEn(b, ahora.value);
    const m = L.marker(posicionEnArco(b, p, arcoDe(b)), { icon: iconoDe(b, p, false), keyboard: true, title: `${b.origen} → ${b.destino} (${b.empresa})`, zIndexOffset: 100 });
    m.on("click", () => elegir(b.salidaId, false));
    m.addTo(capaBuses);
    marcadores.set(b.salidaId, m);
  }
}

/** Recalcula la posición de cada bus con el reloj y mueve su marcador. */
function animar() {
  ahora.value = Math.floor((Date.now() + desfaseMs) / 1000);
  const nuevas = new Map<number, PosicionEstimada>();
  for (const b of visibles.value) {
    const p = posicionEn(b, ahora.value);
    nuevas.set(b.salidaId, p);
    const m = marcadores.get(b.salidaId);
    if (m) {
      m.setLatLng(posicionEnArco(b, p, arcoDe(b)));
      m.setIcon(iconoDe(b, p, b.salidaId === seleccionadoId.value));
    }
  }
  estimadas.value = nuevas;
}

function enfocar(buses: BusEnRecorrido[]) {
  if (!mapa) return;
  if (buses.length === 0) {
    // Sin buses, los teléfonos de prueba (si hay).
    const tel = telefonos.value.flatMap((x) => [[x.posicion.lat, x.posicion.lng] as [number, number], ...(estelas.get(x.dispositivo)?.puntos ?? [])]);
    if (tel.length) mapa.fitBounds(L.latLngBounds(tel), { padding: [40, 40], maxZoom: 15 });
    return;
  }
  const puntos = filtro.soloGps ? buses.map((b) => [b.posicion.lat, b.posicion.lng] as [number, number]).concat(buses.flatMap((b) => trayectorias.get(b.salidaId)?.puntos ?? [])) : buses.flatMap((b) => b.trazado);
  if (puntos.length) mapa.fitBounds(L.latLngBounds(puntos), { padding: [40, 40], maxZoom: 11 });
}

watch(mostrarNombres, dibujarRutas);

function alternarMaximizado() {
  maximizado.value = !maximizado.value;
  document.body.style.overflow = maximizado.value ? "hidden" : "";
}

const alTeclear = (ev: KeyboardEvent) => {
  if (ev.key === "Escape" && maximizado.value) alternarMaximizado();
};

function refrescarVista() {
  dibujarRutas();
  dibujarTrayectorias();
  sincronizarBuses();
  animar();
}

function elegir(id: number | null, centrar: boolean) {
  seleccionadoId.value = id === seleccionadoId.value && centrar ? null : id;
  refrescarVista();
  const b = seleccionado.value;
  const p = b ? estimadas.value.get(b.salidaId) : null;
  if (centrar && b && p && mapa) mapa.flyTo(posicionEnArco(b, p, arcoDe(b)), Math.max(mapa.getZoom(), 9), { duration: 0.6 });
}

function alternarEmpresa(id: number) {
  filtro.empresaId = filtro.empresaId === id ? null : id;
  alCambiarEmpresa();
}

function alCambiarEmpresa() {
  filtro.salidaId = null;
  seleccionadoId.value = null;
  refrescarVista();
  enfocar(visibles.value);
}

function alCambiarFuente() {
  Object.assign(filtro, filtroVigente(datos.value?.buses ?? [], filtro));
  if (!visibles.value.some((b) => b.salidaId === seleccionadoId.value)) seleccionadoId.value = null;
  refrescarVista();
  enfocar(visibles.value);
}

function alCambiarSalida() {
  seleccionadoId.value = filtro.salidaId;
  refrescarVista();
  enfocar(visibles.value);
}

async function cargar(silencioso: boolean) {
  const mia = ++carga;
  cargando.value = !silencioso;
  try {
    const r = await fetchBusesEnRecorrido(silencioso);
    if (mia !== carga) return;
    const primera = datos.value === null;
    datos.value = r;
    registrarTrayectorias(r.buses);
    registrarTelefonos(r.telefonos ?? []);
    desfaseMs = r.generado * 1000 - Date.now();
    actualizado.value = r.generado;
    error.value = "";
    Object.assign(filtro, filtroVigente(r.buses, filtro));
    if (seleccionadoId.value !== null && !r.buses.some((b) => b.salidaId === seleccionadoId.value)) seleccionadoId.value = null;
    refrescarVista();
    dibujarTelefonos();
    if (primera) enfocar(visibles.value);
  } catch (e) {
    if (mia !== carga) return;
    error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las salidas (salida.ver)." : e instanceof Error ? e.message : String(e);
  } finally {
    if (mia === carga) cargando.value = false;
  }
}

onMounted(() => {
  if (!mapaEl.value) return;
  mapa = L.map(mapaEl.value, { center: CENTRO, zoom: 7, zoomControl: true });
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(mapa);
  capaRutas = L.layerGroup().addTo(mapa);
  capaTrayectorias = L.layerGroup().addTo(mapa);
  capaBuses = L.layerGroup().addTo(mapa);
  capaTelefonos = L.layerGroup().addTo(mapa);
  // El contenedor cambia de ancho al abrir/cerrar sidebars o al cambiar de breakpoint.
  observador = new ResizeObserver(() => mapa?.invalidateSize());
  observador.observe(mapaEl.value);
  window.addEventListener("keydown", alTeclear);
  void cargar(false);
  temporizador = setInterval(() => void cargar(true), REFRESCO_MS);
  animacion = setInterval(animar, ANIMACION_MS);
});

onBeforeUnmount(() => {
  clearInterval(temporizador);
  clearInterval(animacion);
  window.removeEventListener("keydown", alTeclear);
  document.body.style.overflow = "";
  observador?.disconnect();
  mapa?.remove();
  mapa = null;
  marcadores.clear();
  trayectorias.clear();
  estelas.clear();
});
</script>

<style>
.mapa-buses {
  height: clamp(22rem, 60dvh, 44rem);
  width: 100%;
  z-index: 0;
}
.enclave-tip {
  font-weight: 500;
}
.leaflet-tooltip.enclave-chip {
  padding: 1px 6px;
  font-size: 10px;
  line-height: 1.4;
  font-weight: 700;
  color: var(--p-slate-50);
  background: var(--p-slate-800);
  border: 1px solid var(--p-slate-800);
  border-radius: 4px;
  box-shadow: none;
  white-space: nowrap;
}
/* Saeta hacia el punto: ::before es el borde y ::after el relleno, ambos del mismo slate. */
.leaflet-tooltip.enclave-chip.leaflet-tooltip-right::before {
  border-right-color: var(--p-slate-800);
}
.leaflet-tooltip.enclave-chip.leaflet-tooltip-right::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 0;
  margin-top: -5px;
  margin-left: -9px;
  border: 5px solid transparent;
  border-right-color: var(--p-slate-800);
  pointer-events: none;
}
.mapa-maximizado .mapa-buses {
  height: 100%;
  min-height: 18rem;
}
</style>
