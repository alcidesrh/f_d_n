<!--
  Tarifas sobre el mapa (MapLibre, `shared/mapa`, ADR-055). Solo se muestran los enclaves activos
  (la ruta sí pasa por sus paradas inactivas). Sin ruta: los enclaves, solo con su nombre; clic en uno y luego en otro los elige como origen y destino.
  Con una ruta: su trazo con las paradas, los enclaves que
  no están en ella se ocultan y, a medio camino de cada trayecto con tarifas,
  un indicador con su número. Clic en el indicador: popup con un acordeón de
  las tarifas (detalle y «Editar»). Reglas puras en `core/tarifa/mapa.ts`.
-->
<template>
  <div class="@container flex flex-col gap-4" :class="maximizado ? 'fixed inset-0 z-[900] overflow-auto bg-surface-0 p-3' : ''">
    <section class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <label class="flex w-full flex-col gap-1 @xl:w-[28rem]">
          <span class="text-xs font-medium text-muted-color">Ruta</span>
          <SelectorEntidad v-model="rutaId" entidad="Ruta" placeholder="Todos los enclaves" aria-label="Ruta" />
        </label>
        <label class="flex min-w-[12rem] flex-1 flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Origen</span>
          <SelectorEntidad v-model="origenId" entidad="Enclave" placeholder="Cualquiera" aria-label="Origen" />
        </label>
        <label class="flex min-w-[12rem] flex-1 flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Destino</span>
          <SelectorEntidad v-model="destinoId" entidad="Enclave" placeholder="Cualquiera" aria-label="Destino" />
        </label>
        <div class="flex items-center gap-2">
          <Button label="Nueva tarifa" size="small" severity="secondary" outlined @click="router.push('/form/BoletoTarifa')" />
          <Button size="small" text rounded class="tap-target" :aria-label="maximizado ? 'Salir de pantalla completa' : 'Maximizar mapa'" :title="maximizado ? 'Salir de pantalla completa (Esc)' : 'Maximizar'" :aria-pressed="maximizado" @click="alternarMaximizado">
            <icon :name="maximizado ? 'fullscreen-exit' : 'fullscreen'" />
          </Button>
        </div>
      </div>
      <!-- Lo que hay sobre el mapa no cambia de alto al elegir: el mapa no salta en la página. -->
      <p class="m-0 min-h-[1lh] truncate text-xs text-muted-color" :title="avisoTrayecto" aria-live="polite">{{ avisoTrayecto }}</p>

      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-color">
        <label class="flex cursor-pointer items-center gap-2">
          <ToggleSwitch v-model="mostrarNombres" input-id="nombres-tarifas" />
          <span>Nombres de enclaves</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
          <ToggleSwitch v-model="mostrarTarifas" input-id="tarifas-mapa" />
          <span>Tarifas</span>
        </label>
      </div>
    </section>

    <!-- La clase va en el envoltorio: Vue reescribiría el class del mapa y le borraría las clases de MapLibre. -->
    <div :class="maximizado ? 'mapa-maximizado flex min-h-0 flex-1 flex-col' : ''">
      <div ref="mapaEl" class="mapa-tarifas rounded-border border border-surface-200" role="application" aria-label="Mapa de tarifas" />
    </div>

    <!-- Debajo del mapa: al aparecer no lo desplazan. -->
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-else-if="sinUbicar.length" severity="warn" :closable="false">Sin ubicación en el mapa: {{ sinUbicar.join(", ") }}.</Message>
  </div>
</template>

<script setup lang="ts">
import type { Map as MapaLibre, Marker, Popup } from "maplibre-gl";
import { fetchOpcionesRutas } from "@/core/ruta/api";
import type { Lugar } from "@/core/ruta/types";
import SelectorEntidad from "@/shared/entidad/SelectorEntidad.vue";
import { fetchTarifasDeRuta, fetchTarifasDeTrayecto } from "@/core/tarifa/api";
import { puntoMedio, type RutaTarifasMapa, type TarifaMapa, type TrayectoMapa } from "@/core/tarifa/mapa";
import { HttpError } from "@/core/http";
import { BLANCO, Etiquetas, GRIS_LUGAR, alPulsar, asegurarCapas, colorPrimario, encuadrar, fijarDatos, globoAlPasar } from "@/shared/mapa/capas";
import { aLngLat, coleccion, linea, lineas, punto, type LatLng } from "@/shared/mapa/geo";
import { maplibregl } from "@/shared/mapa/maplibre";
import { useMapa } from "@/shared/mapa/useMapa";

/** Relleno del enclave elegido como origen; el mismo ámbar del trayecto en la ruta. */
const COLOR_ORIGEN = "#f59e0b";

const router = useRouter();
const mapaEl = ref<HTMLElement | null>(null);
let etiquetas: Etiquetas | null = null;
let indicadores: Marker[] = [];
let popup: Popup | null = null;
let carga = 0;
// Sin teclado: pulsar el mapa no le da el foco (el navegador desplazaría la página y se perdería el clic en un indicador).
const { mapa, listo } = useMapa(
  mapaEl,
  (m) => {
    etiquetas = new Etiquetas(m);
    prepararCapas(m);
    // Clic en un enclave o en una parada: lo elige como origen o destino.
    alPulsar(m, "tarifas-lugares", (p) => elegirEnclave(Number(p.id)));
    alPulsar(m, "tarifas-paradas", (p) => elegirEnclave(Number(p.enclaveId)));
    // Sin los chips, el nombre del enclave al pasar el cursor.
    globoAlPasar(m, "tarifas-lugares", (p) => (mostrarNombres.value ? null : escapar(String(p.nombre ?? ""))));
    dibujarTodo();
    enfocar(false);
  },
  { teclado: false },
);

const lugares = ref<Lugar[]>([]);
const rutaId = ref<number | null>(null);
const origenId = ref<number | null>(null);
const destinoId = ref<number | null>(null);
const datos = ref<RutaTarifasMapa | null>(null);
const avisoTrayecto = computed(() => {
  const ambos = origenId.value !== null && destinoId.value !== null;
  if (ambos && origenId.value === destinoId.value) return "El origen y el destino son el mismo enclave.";
  if (rutaId.value !== null) {
    if (ambos && datos.value && trayectosVista.value.length === 0) return "Esta ruta no tiene un trayecto de ese origen a ese destino (el origen va antes que el destino).";
    return "";
  }
  if ((origenId.value === null) !== (destinoId.value === null)) return "Elija también el otro extremo para ver el trayecto.";
  return "";
});
const error = ref("");
const mostrarNombres = ref(false);
const mostrarTarifas = ref(false);
const maximizado = ref(false);

/** Pantalla completa: cubre la ventana y bloquea el scroll de la página detrás. */
function alternarMaximizado() {
  maximizado.value = !maximizado.value;
  document.body.style.overflow = maximizado.value ? "hidden" : "";
}

const alTeclear = (ev: KeyboardEvent) => {
  if (ev.key === "Escape" && maximizado.value) alternarMaximizado();
};

/** Con una ruta y origen + destino elegidos, solo el trayecto entre ellos (en el orden de la ruta). */
const trayectosVista = computed(() => {
  const todos = datos.value?.trayectos ?? [];
  if (rutaId.value === null || origenId.value === null || destinoId.value === null) return todos;
  const desde = indiceParada(origenId.value);
  const hasta = indiceParada(destinoId.value);
  return todos.filter((t) => t.desde === desde && t.hasta === hasta);
});

/** Posición del enclave en la ruta cargada; -1 si no está. */
function indiceParada(enclaveId: number): number {
  return datos.value?.ruta.paradas.findIndex((p) => p.enclaveId === enclaveId) ?? -1;
}
const sinUbicar = computed(() => (datos.value?.ruta.paradas ?? []).filter((p) => p.activo && (p.latitud === null || p.longitud === null)).map((p) => p.nombre));

/** Lo que se dibuja como punto: enclaves activos con ubicación. */
const enMapa = <T extends { latitud: number | null; longitud: number | null; activo: boolean }>(x: T[]) => x.filter((l) => l.activo && l.latitud !== null && l.longitud !== null);

const escapar = (s: string) => s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);

/** Fuentes vacías y capas: enclaves abajo, encima la ruta, el trayecto elegido y las paradas. */
function prepararCapas(m: MapaLibre) {
  for (const id of ["tarifas-lugares", "tarifas-linea", "tarifas-tramo", "tarifas-paradas"]) fijarDatos(m, id, coleccion([]));
  // El círculo crece con el zoom, de 5 px a 16 px (las puntas de la ruta, 2 px más); el borde de 2 px va por fuera.
  asegurarCapas(m, [
    {
      id: "tarifas-lugares",
      type: "circle",
      source: "tarifas-lugares",
      paint: { "circle-radius": ["interpolate", ["linear"], ["zoom"], 5, 4, 16, 15], "circle-color": ["case", ["get", "origen"], COLOR_ORIGEN, BLANCO], "circle-stroke-color": GRIS_LUGAR, "circle-stroke-width": 2 },
    },
    { id: "tarifas-linea", type: "line", source: "tarifas-linea", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": ["get", "color"], "line-width": 3, "line-opacity": 0.85 } },
    { id: "tarifas-tramo", type: "line", source: "tarifas-tramo", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": COLOR_ORIGEN, "line-width": 6, "line-opacity": 0.95 } },
    {
      id: "tarifas-paradas",
      type: "circle",
      source: "tarifas-paradas",
      paint: {
        "circle-radius": ["interpolate", ["linear"], ["zoom"], 5, ["case", ["get", "punta"], 6, 4], 16, ["case", ["get", "punta"], 17, 15]],
        "circle-color": ["case", ["get", "origen"], COLOR_ORIGEN, ["get", "punta"], ["get", "color"], BLANCO],
        "circle-stroke-color": ["get", "color"],
        "circle-stroke-width": 2,
      },
    },
  ]);
}

function dibujarTodo() {
  dibujarEnclaves();
  dibujarRuta();
  dibujarTarifas();
}

/** Sin ruta: los enclaves activos con ubicación. Con ruta: se ocultan. */
function dibujarEnclaves() {
  const m = mapa.value;
  if (!m || !listo.value) return;
  // Con una ruta se ocultan; con un trayecto siguen, para elegir otro destino (u origen) con clics.
  const ubicados = rutaId.value !== null ? [] : enMapa(lugares.value);
  fijarDatos(m, "tarifas-lugares", coleccion(ubicados.map((l) => punto([l.latitud!, l.longitud!], { id: l.id, nombre: l.nombre, origen: l.id === origenId.value }))));
  etiquetarse();
}

/** Los chips con el nombre: de los enclaves sin ruta o de las paradas con ella. */
function etiquetarse() {
  if (!etiquetas) return;
  if (!mostrarNombres.value) return etiquetas.limpiar();
  const lista = rutaId.value !== null ? enMapa(datos.value?.ruta.paradas ?? []).map((p) => ({ clave: `p${p.enclaveId}`, en: [p.latitud!, p.longitud!] as LatLng, texto: p.nombre })) : enMapa(lugares.value).map((l) => ({ clave: `l${l.id}`, en: [l.latitud!, l.longitud!] as LatLng, texto: l.nombre }));
  etiquetas.fijar(lista);
}

/**
 * Clic en un enclave sin ruta: el primero es el origen, el segundo el destino
 * (igual que elegirlos en los selectores). Clic en el origen lo quita.
 */
function elegirEnclave(id: number) {
  if (origenId.value === null) {
    origenId.value = id;
    destinoId.value = null;
  } else if (origenId.value === id) {
    // Quitar el origen; si hay destino, pasa a ser el origen.
    origenId.value = destinoId.value;
    destinoId.value = null;
  } else destinoId.value = id;
}

/** Trazo de la ruta con sus paradas (las que tienen ubicación; el punto, solo de las activas). */
function dibujarRuta() {
  const m = mapa.value;
  if (!m || !listo.value) return;
  const paradas = (datos.value?.ruta.paradas ?? []).filter((p) => p.latitud !== null && p.longitud !== null);
  const color = colorPrimario(mapaEl.value);
  fijarDatos(m, "tarifas-linea", lineas([linea(paradas.map((p) => [p.latitud!, p.longitud!]), { color })]));
  // Con una ruta, el trayecto origen→destino va por su mismo trazo, en otro color.
  const io = indiceParada(origenId.value ?? -1);
  const id = indiceParada(destinoId.value ?? -1);
  const tramo =
    datos.value && rutaId.value !== null && io !== -1 && id !== -1 && io < id
      ? datos.value.ruta.paradas
          .slice(io, id + 1)
          .filter((p) => p.latitud !== null && p.longitud !== null)
          .map((p) => [p.latitud!, p.longitud!] as LatLng)
      : [];
  fijarDatos(m, "tarifas-tramo", lineas([linea(tramo)]));
  fijarDatos(m, "tarifas-paradas", coleccion(paradas.flatMap((p, i) => (p.activo ? [punto([p.latitud!, p.longitud!], { enclaveId: p.enclaveId, nombre: p.nombre, color, punta: i === 0 || i === paradas.length - 1, origen: p.enclaveId === origenId.value })] : []))));
  etiquetarse();
}

/** Un indicador por trayecto con tarifas, a medio camino de su tramo de la ruta. */
function dibujarTarifas() {
  for (const i of indicadores) i.remove();
  indicadores = [];
  cerrarPopup();
  const m = mapa.value;
  if (!m || !listo.value || !mostrarTarifas.value || !datos.value) return;
  const paradas = datos.value.ruta.paradas;
  for (const t of trayectosVista.value) {
    const en = puntoMedio(paradas, t.desde, t.hasta);
    if (!en) continue;
    const etiqueta = `${t.origen} → ${t.destino}: ${t.tarifas.length} ${t.tarifas.length === 1 ? "tarifa" : "tarifas"}`;
    const boton = document.createElement("button");
    boton.type = "button";
    boton.className = "mapa-boton tarifa-indicador__boton";
    boton.textContent = String(t.tarifas.length);
    boton.title = etiqueta;
    boton.setAttribute("aria-label", etiqueta);
    // Encima de los chips con nombres.
    boton.style.zIndex = "3";
    // Al pulsar, el foco haría que el navegador desplace la página (y el clic se pierde): el ratón no enfoca el botón.
    boton.addEventListener("mousedown", (e) => e.preventDefault());
    boton.addEventListener("click", (e) => {
      // El clic no sigue al mapa: elegiría la parada que quede debajo.
      e.stopPropagation();
      abrirPopup(t, en);
    });
    indicadores.push(new maplibregl.Marker({ element: boton }).setLngLat(aLngLat(en)).addTo(m));
  }
}

function cerrarPopup() {
  popup?.remove();
  popup = null;
}

function abrirPopup(t: TrayectoMapa, en: [number, number]) {
  const m = mapa.value;
  if (!m) return;
  cerrarPopup();
  popup = new maplibregl.Popup({ maxWidth: "360px", className: "tarifa-popup", focusAfterOpen: false, offset: 14 }).setLngLat(aLngLat(en)).setDOMContent(contenidoPopup(t)).addTo(m);
}

/** Acordeón con las tarifas del trayecto; se arma con DOM para no interpolar texto sin escapar. */
function contenidoPopup(t: TrayectoMapa): HTMLElement {
  const raiz = document.createElement("div");
  const titulo = document.createElement("p");
  titulo.className = "tarifa-popup__titulo";
  titulo.textContent = `${t.origen} → ${t.destino}`;
  const acordeon = document.createElement("div");
  acordeon.className = "tarifa-popup__acordeon";
  for (const tarifa of t.tarifas) acordeon.append(itemTarifa(tarifa));
  raiz.append(titulo, acordeon);
  return raiz;
}

function itemTarifa(tarifa: TarifaMapa): HTMLElement {
  const item = document.createElement("details");
  item.className = "tarifa-item";
  const resumen = document.createElement("summary");
  resumen.textContent = `Clase ${tarifa.clase} · ${tarifa.importe ?? "—"} · ${tarifa.empresa ?? "Todas las empresas"}`;
  const detalle = document.createElement("dl");
  detalle.append(fila("Nombre", tarifa.nombre ?? "—"), fila("Bus", tarifa.bus ?? "Cualquiera"), fila("Clase de bus", tarifa.busClase ?? "Cualquiera"), fila("Horario", tarifa.horaDesde || tarifa.horaHasta ? `${tarifa.horaDesde ?? "…"} – ${tarifa.horaHasta ?? "…"}` : "Cualquier hora"), fila("Vigente desde", tarifa.vigenteDesde.replace(" ", " · ")), fila("Registró", tarifa.usuario ?? "—"));
  const editar = document.createElement("button");
  editar.type = "button";
  editar.className = "tarifa-item__editar";
  editar.textContent = "Editar";
  editar.addEventListener("click", () => {
    cerrarPopup();
    void router.push(`/form/BoletoTarifa/${tarifa.id}`);
  });
  item.append(resumen, detalle, editar);
  return item;
}

function fila(etiqueta: string, valor: string): DocumentFragment {
  const f = document.createDocumentFragment();
  const dt = document.createElement("dt");
  dt.textContent = etiqueta;
  const dd = document.createElement("dd");
  dd.textContent = valor;
  f.append(dt, dd);
  return f;
}

function enfocar(animar = true) {
  const m = mapa.value;
  if (!m || !listo.value) return;
  const puntos: LatLng[] = datos.value ? datos.value.ruta.paradas.filter((p) => p.latitud !== null && p.longitud !== null).map((p) => [p.latitud!, p.longitud!]) : enMapa(lugares.value).map((l) => [l.latitud!, l.longitud!]);
  encuadrar(m, puntos, { margen: 40, maxZoom: 10, animar });
}

/** Lo que se ve: una ruta, un trayecto origen→destino o los enclaves. */
let vistaActual = "";
async function cargarVista() {
  const origen = origenId.value;
  const destino = destinoId.value;
  let clave = "enclaves";
  if (rutaId.value !== null) clave = `ruta:${rutaId.value}`;
  else if (origen !== null && destino !== null && origen !== destino) clave = `trayecto:${origen}-${destino}`;
  if (clave === vistaActual) return;
  vistaActual = clave;

  const mia = ++carga;
  error.value = "";
  datos.value = null;
  if (clave !== "enclaves") {
    try {
      const r = rutaId.value !== null ? await fetchTarifasDeRuta(rutaId.value) : await fetchTarifasDeTrayecto(origen!, destino!);
      if (mia !== carga) return;
      datos.value = r;
    } catch (e) {
      if (mia !== carga) return;
      error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las tarifas (salida.ver)." : e instanceof Error ? e.message : String(e);
    }
  }
  dibujarTodo();
}

/** Elegir una ruta quita origen y destino; elegir origen o destino quita la ruta. */
watch(rutaId, (id) => {
  if (id !== null) {
    origenId.value = null;
    destinoId.value = null;
  }
  void cargarVista();
});
watch([origenId, destinoId], () => {
  // El origen se marca con su color, así que cada cambio redibuja los enclaves y la ruta.
  dibujarEnclaves();
  dibujarRuta();
  // Con una ruta, el trayecto sale de sus paradas: no hay nada que pedir al servidor.
  if (rutaId.value === null) void cargarVista();
});
watch(trayectosVista, dibujarTarifas);
watch(mostrarTarifas, dibujarTarifas);
watch(mostrarNombres, dibujarTodo);

onMounted(async () => {
  window.addEventListener("keydown", alTeclear);
  try {
    // Los enclaves activos con coordenadas se dibujan en el mapa (no son un filtro).
    lugares.value = (await fetchOpcionesRutas()).lugares;
    dibujarEnclaves();
    enfocar(false);
  } catch (e) {
    error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las tarifas (salida.ver)." : e instanceof Error ? e.message : String(e);
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", alTeclear);
  document.body.style.overflow = "";
  cerrarPopup();
  for (const i of indicadores) i.remove();
  indicadores = [];
});
</script>

<style>
.mapa-tarifas {
  height: clamp(24rem, 66dvh, 46rem);
  width: 100%;
  z-index: 0;
}
.mapa-maximizado .mapa-tarifas {
  height: 100%;
  min-height: 18rem;
}
.tarifa-tip {
  font-weight: 500;
}
.tarifa-indicador__boton {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  padding: 0;
  border-radius: 999px;
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  border: 2px solid #fff;
  box-shadow: 0 1px 6px rgb(0 0 0 / 0.4);
  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}
.tarifa-popup .maplibregl-popup-content {
  min-width: min(280px, 80vw);
  padding: 10px 12px;
  font-size: 13px;
}
.tarifa-popup__titulo {
  margin: 0 0 6px;
  font-weight: 600;
}
.tarifa-popup__acordeon {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 18rem;
  overflow-y: auto;
}
.tarifa-item {
  border: 1px solid var(--p-surface-200);
  border-radius: 6px;
  padding: 4px 8px;
}
.tarifa-item summary {
  cursor: pointer;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}
.tarifa-item dl {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 2px 10px;
  margin: 6px 0;
  font-size: 12px;
}
.tarifa-item dt {
  color: var(--p-text-muted-color);
}
.tarifa-item dd {
  margin: 0;
}
.tarifa-item__editar {
  margin: 2px 0 4px;
  color: var(--p-primary-color);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  background: none;
  border: 0;
  padding: 0;
}
</style>
