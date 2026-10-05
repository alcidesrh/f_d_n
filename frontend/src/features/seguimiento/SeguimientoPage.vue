<!--
  Mapa en tiempo real de buses en recorrido. Muestra las salidas iniciadas del
  sistema legado; mientras no haya GPS reales, la posición se infiere del
  cronograma (distancia, hora y velocidad media) y el navegador anima los
  buses con el reloj; la lista se refresca cada 30 s. Un bus con lectura GPS
  vigente se pinta tal cual la entrega el servidor. Filtros por empresa y
  salida (que enfoca el bus y su ruta).
  Contenido angosto: mapa y lista apilados; desde 56rem de ancho: lado a lado.
  Interruptor "Nombres de enclaves": chip con el nombre junto a cada punto (si no, solo al pasar el cursor).
  Maximizar (o Esc para salir): filtros, mapa y lista cubren toda la pantalla.
-->
<template>
  <div class="@container flex flex-col gap-4" :class="maximizado ? 'fixed inset-0 z-[900] overflow-auto bg-surface-0 p-3 dark:bg-surface-900' : ''">
    <Toolbar>
      <template #start><PageHead /></template>
      <template #end>
        <span class="flex items-center gap-2 text-xs text-muted-color" aria-live="polite">
          <span class="inline-block size-2 rounded-full" :class="error ? 'bg-red-500' : 'bg-green-500'" />
          <template v-if="actualizado">Actualizado {{ horaDe(actualizado) }}</template>
          <template v-else>Cargando…</template>
          <Button size="small" text rounded class="tap-target" aria-label="Actualizar ahora" :loading="cargando" @click="cargar(false)"><icon name="refresh" /></Button>
          <Button size="small" text rounded class="tap-target" :aria-label="maximizado ? 'Salir de pantalla completa' : 'Maximizar mapa'" :title="maximizado ? 'Salir de pantalla completa (Esc)' : 'Maximizar'" :aria-pressed="maximizado" @click="alternarMaximizado">
            <icon :name="maximizado ? 'fullscreen-exit' : 'fullscreen'" />
          </Button>
        </span>
      </template>
    </Toolbar>

    <section class="panel grid grid-cols-1 gap-3 @xl:grid-cols-[1fr_1.4fr_auto] @xl:items-end">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Empresa</span>
        <Select v-model="filtro.empresaId" :options="datos?.empresas ?? []" option-label="nombre" option-value="id" show-clear placeholder="Todas" fluid @change="alCambiarEmpresa" />
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Salida</span>
        <Select v-model="filtro.salidaId" :options="opcionesSalida" option-label="etiqueta" option-value="id" show-clear filter placeholder="Todas" fluid :disabled="opcionesSalida.length === 0" @change="alCambiarSalida" />
      </label>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-color">
        <label class="flex cursor-pointer items-center gap-2">
          <ToggleSwitch v-model="mostrarNombres" input-id="nombres-enclaves" />
          <span>Nombres de enclaves</span>
        </label>
        <span class="flex items-center gap-2">
          <icon name="directions-bus-outline" />
          {{ visibles.length }} {{ visibles.length === 1 ? 'bus en recorrido' : 'buses en recorrido' }}
        </span>
      </div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-else-if="datos && datos.sinTrazado > 0" severity="warn" :closable="false">
      {{ datos.sinTrazado }} {{ datos.sinTrazado === 1 ? 'salida no se muestra' : 'salidas no se muestran' }} porque las estaciones de su ruta no tienen ubicación.
    </Message>

    <div class="grid grid-cols-1 gap-4 @4xl:grid-cols-[minmax(0,1fr)_22rem]" :class="maximizado ? 'min-h-0 flex-1 grid-rows-[minmax(18rem,1fr)_auto] @4xl:grid-rows-1' : ''">
      <!-- La clase dinámica va en el padre: Vue reescribiría el class del contenedor de Leaflet y le borraría las clases que él añade. -->
      <div class="relative min-h-0" :class="{ 'mapa-maximizado': maximizado }">
        <div ref="mapaEl" class="mapa-buses rounded-border border border-surface-200" role="application" aria-label="Mapa de buses en recorrido" />
        <p v-if="datos && !cargando && visibles.length === 0" class="pointer-events-none absolute inset-x-0 top-1/2 z-[500] mx-auto w-fit -translate-y-1/2 rounded-border bg-surface-0/90 px-3 py-2 text-sm shadow dark:bg-surface-900/90">
          No hay buses en recorrido{{ hayFiltro ? ' con este filtro' : ' en este momento' }}.
        </p>
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
          <p v-if="!seleccionado.marcadaIniciada" class="m-0 text-xs text-muted-color">
            En el sistema figura «{{ seleccionado.estadoSistema }}»; se asume que ya salió por su hora y sus boletos vendidos.
          </p>
          <ProgressBar :value="Math.round((seleccionadoPos?.progreso ?? 0) * 100)" :show-value="false" style="height: 0.5rem" />
          <dl class="m-0 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <dt class="text-muted-color">Estado</dt>
            <dd class="m-0">{{ ETIQUETA_ESTADO[seleccionadoPos?.estado ?? 'en_ruta'] }}</dd>
            <dt class="text-muted-color">Recorrido</dt>
            <dd class="m-0">{{ (seleccionadoPos?.km ?? 0).toFixed(0) }} de {{ seleccionado.kilometros.toFixed(0) }} km</dd>
            <dt class="text-muted-color">Velocidad</dt>
            <dd class="m-0">{{ (seleccionadoPos?.velocidadKmh ?? 0).toFixed(0) }} km/h</dd>
            <dt class="text-muted-color">Próxima estación</dt>
            <dd class="m-0">{{ seleccionadoProxima ? `${seleccionadoProxima.nombre} (${horaDe(seleccionadoProxima.llegada)}, ${duracion(seleccionadoProxima.llegada - ahora)})` : '—' }}</dd>
            <dt class="text-muted-color">Llegada estimada</dt>
            <dd class="m-0">{{ horaDe(seleccionado.llegadaEstimada) }}</dd>
            <dt class="text-muted-color">Bus</dt>
            <dd class="m-0">{{ seleccionado.bus }}<template v-if="seleccionado.placa"> · {{ seleccionado.placa }}</template></dd>
            <dt class="text-muted-color">Piloto</dt>
            <dd class="m-0">{{ seleccionado.piloto ?? 'Sin asignar' }}</dd>
            <dt class="text-muted-color">Ruta</dt>
            <dd class="m-0">{{ seleccionado.rutaNombre }}</dd>
          </dl>
        </article>

        <ul class="m-0 flex list-none flex-col gap-1.5 p-0" :class="maximizado ? '' : 'max-h-[28rem] overflow-y-auto'" aria-label="Buses en recorrido">
          <li v-for="b in visibles" :key="b.salidaId">
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-border border bg-transparent p-2 text-left text-sm"
              :class="b.salidaId === seleccionadoId ? 'border-primary' : 'border-surface-200'"
              :aria-pressed="b.salidaId === seleccionadoId"
              @click="elegir(b.salidaId, true)"
            >
              <span class="inline-block size-3 shrink-0 rounded-full" :style="{ background: colorEmpresa(b.empresaId, b.empresa) }" />
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">{{ b.origen }} → {{ b.destino }}</span>
                <span class="block truncate text-xs text-muted-color">{{ b.empresa }} · bus {{ b.bus }} · {{ horaDe(b.partida) }}</span>
              </span>
              <span class="shrink-0 text-xs tabular-nums text-muted-color">{{ Math.round((estimadas.get(b.salidaId)?.progreso ?? 0) * 100) }}%</span>
            </button>
          </li>
        </ul>
        <p class="m-0 text-xs text-muted-color">
          Las posiciones son una simulación a partir de la hora de salida, la distancia de la ruta y una velocidad media. Se reemplazan por la lectura del GPS cuando el bus la envíe.
        </p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { fetchBusesEnRecorrido } from '@/core/seguimiento/api'
import { FILTRO_VACIO, filtrarBuses, filtroVigente, salidasDisponibles } from '@/core/seguimiento/filtro'
import { estilosPorRuta, posicionEnArco, trazadoArqueado } from '@/core/seguimiento/arco'
import { posicionEn, proximaParada } from '@/core/seguimiento/posicion'
import type { BusEnRecorrido, FiltroSeguimiento, PosicionEstimada, RespuestaSeguimiento } from '@/core/seguimiento/types'
import { HttpError } from '@/core/http'
import { colorEmpresa } from './colores'
import { ETIQUETA_ESTADO, ORIGEN_COORDENADA, duracion, horaDe } from './formato'

const REFRESCO_MS = 30_000
const ANIMACION_MS = 1_000
/** Centro aproximado de Guatemala. */
const CENTRO: L.LatLngTuple = [15.4, -90.2]

const mapaEl = ref<HTMLElement | null>(null)
const datos = ref<RespuestaSeguimiento | null>(null)
const cargando = ref(false)
const error = ref('')
const actualizado = ref<number | null>(null)
const filtro = reactive<FiltroSeguimiento>({ ...FILTRO_VACIO })
const seleccionadoId = ref<number | null>(null)
/** Reloj del servidor en segundos: el del navegador corregido con el desfase de la última respuesta. */
const ahora = ref(Math.floor(Date.now() / 1000))
const estimadas = ref(new Map<number, PosicionEstimada>())

let mapa: L.Map | null = null
let capaRutas: L.LayerGroup | null = null
let capaBuses: L.LayerGroup | null = null
let desfaseMs = 0
let temporizador: ReturnType<typeof setInterval> | undefined
let animacion: ReturnType<typeof setInterval> | undefined
let carga = 0
let observador: ResizeObserver | undefined
const maximizado = ref(false)
const mostrarNombres = ref(false)
const marcadores = new Map<number, L.Marker>()

/** Curvatura y fase de guiones de cada ruta, fijas aunque el filtro cambie. */
const estilos = computed(() => estilosPorRuta((datos.value?.buses ?? []).map((b) => b.ruta)))
const arcoDe = (b: BusEnRecorrido) => estilos.value.get(b.ruta)?.k ?? 0
const visibles = computed(() => filtrarBuses(datos.value?.buses ?? [], filtro))
const hayFiltro = computed(() => filtro.empresaId !== null || filtro.salidaId !== null)
const opcionesSalida = computed(() =>
  salidasDisponibles(datos.value?.buses ?? [], filtro.empresaId).map((b) => ({ id: b.salidaId, etiqueta: `${horaDe(b.partida)} · ${b.origen} → ${b.destino} · bus ${b.bus}` })),
)
const seleccionado = computed<BusEnRecorrido | null>(() => visibles.value.find((b) => b.salidaId === seleccionadoId.value) ?? null)
const seleccionadoPos = computed(() => (seleccionado.value ? (estimadas.value.get(seleccionado.value.salidaId) ?? null) : null))
const seleccionadoProxima = computed(() => (seleccionado.value ? proximaParada(seleccionado.value, ahora.value) : null))

function iconoBus(b: BusEnRecorrido, p: PosicionEstimada, activo: boolean): L.DivIcon {
  const color = colorEmpresa(b.empresaId, b.empresa)
  const quieto = p.estado !== 'en_ruta'
  return L.divIcon({
    className: 'bus-marker',
    iconSize: [34, 34],
    iconAnchor: [17, 17],
    html: `<div class="bus-marker-body${activo ? ' is-active' : ''}${quieto ? ' is-still' : ''}" style="--c:${color}"><svg viewBox="0 0 24 24" width="22" height="22" style="transform:rotate(${p.rumbo.toFixed(0)}deg)" aria-hidden="true"><path d="M12 2 19 21 12 17 5 21Z" fill="#fff"/></svg></div>`,
  })
}

const escapar = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)

function dibujarRutas() {
  if (!capaRutas) return
  capaRutas.clearLayers()
  const vistas = new Set<string>()
  const estaciones = new Set<string>()
  // Con un bus elegido solo se dibuja su ruta; si no, la de cada ruta una vez.
  const buses = seleccionado.value ? [seleccionado.value] : visibles.value
  for (const b of buses) {
    if (vistas.has(b.ruta)) continue
    vistas.add(b.ruta)
    const color = colorEmpresa(b.empresaId, b.empresa)
    const estilo = estilos.value.get(b.ruta)
    L.polyline(trazadoArqueado(b.trazado, estilo?.k ?? 0), { color, weight: 3, opacity: 0.85, dashArray: '6 6', dashOffset: String(estilo?.fase ?? 0) }).addTo(capaRutas)
    for (const e of b.estaciones) {
      const exacta = e.origen === 'gps'
      const punto = L.circleMarker([e.lat, e.lng], { radius: 4, color, weight: 2, fillColor: exacta ? color : '#fff', fillOpacity: 1 })
      if (mostrarNombres.value) {
        // Una etiqueta por lugar, aunque lo compartan varias rutas.
        const clave = `${e.nombre}|${e.lat}|${e.lng}`
        if (!estaciones.has(clave)) {
          estaciones.add(clave)
          punto.bindTooltip(escapar(e.nombre), { permanent: true, direction: 'right', offset: [8, 0], className: 'enclave-chip', interactive: false })
        }
      } else {
        punto.bindTooltip(`${escapar(e.nombre)}${exacta ? '' : `<br><small>${escapar(ORIGEN_COORDENADA[e.origen] ?? '')}</small>`}`, { className: 'enclave-tip' })
      }
      punto.addTo(capaRutas)
    }
  }
}

function sincronizarBuses() {
  if (!capaBuses) return
  const ids = new Set(visibles.value.map((b) => b.salidaId))
  for (const [id, m] of marcadores) {
    if (!ids.has(id)) {
      capaBuses.removeLayer(m)
      marcadores.delete(id)
    }
  }
  for (const b of visibles.value) {
    if (marcadores.has(b.salidaId)) continue
    const p = estimadas.value.get(b.salidaId) ?? posicionEn(b, ahora.value)
    const m = L.marker(posicionEnArco(b, p, arcoDe(b)), { icon: iconoBus(b, p, false), keyboard: true, title: `${b.origen} → ${b.destino} (${b.empresa})`, zIndexOffset: 100 })
    m.on('click', () => elegir(b.salidaId, false))
    m.addTo(capaBuses)
    marcadores.set(b.salidaId, m)
  }
}

/** Recalcula la posición de cada bus con el reloj y mueve su marcador. */
function animar() {
  ahora.value = Math.floor((Date.now() + desfaseMs) / 1000)
  const nuevas = new Map<number, PosicionEstimada>()
  for (const b of visibles.value) {
    const p = posicionEn(b, ahora.value)
    nuevas.set(b.salidaId, p)
    const m = marcadores.get(b.salidaId)
    if (m) {
      m.setLatLng(posicionEnArco(b, p, arcoDe(b)))
      m.setIcon(iconoBus(b, p, b.salidaId === seleccionadoId.value))
    }
  }
  estimadas.value = nuevas
}

function enfocar(buses: BusEnRecorrido[]) {
  if (!mapa || buses.length === 0) return
  const puntos = buses.flatMap((b) => b.trazado)
  if (puntos.length) mapa.fitBounds(L.latLngBounds(puntos), { padding: [40, 40], maxZoom: 11 })
}

watch(mostrarNombres, dibujarRutas)

function alternarMaximizado() {
  maximizado.value = !maximizado.value
  document.body.style.overflow = maximizado.value ? 'hidden' : ''
}

const alTeclear = (ev: KeyboardEvent) => {
  if (ev.key === 'Escape' && maximizado.value) alternarMaximizado()
}

function refrescarVista() {
  dibujarRutas()
  sincronizarBuses()
  animar()
}

function elegir(id: number | null, centrar: boolean) {
  seleccionadoId.value = id === seleccionadoId.value && centrar ? null : id
  refrescarVista()
  const b = seleccionado.value
  const p = b ? estimadas.value.get(b.salidaId) : null
  if (centrar && b && p && mapa) mapa.flyTo(posicionEnArco(b, p, arcoDe(b)), Math.max(mapa.getZoom(), 9), { duration: 0.6 })
}

function alCambiarEmpresa() {
  filtro.salidaId = null
  seleccionadoId.value = null
  refrescarVista()
  enfocar(visibles.value)
}

function alCambiarSalida() {
  seleccionadoId.value = filtro.salidaId
  refrescarVista()
  enfocar(visibles.value)
}

async function cargar(silencioso: boolean) {
  const mia = ++carga
  cargando.value = !silencioso
  try {
    const r = await fetchBusesEnRecorrido(silencioso)
    if (mia !== carga) return
    const primera = datos.value === null
    datos.value = r
    desfaseMs = r.generado * 1000 - Date.now()
    actualizado.value = r.generado
    error.value = ''
    Object.assign(filtro, filtroVigente(r.buses, filtro))
    if (seleccionadoId.value !== null && !r.buses.some((b) => b.salidaId === seleccionadoId.value)) seleccionadoId.value = null
    refrescarVista()
    if (primera) enfocar(visibles.value)
  } catch (e) {
    if (mia !== carga) return
    error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver las salidas (salida.ver).' : e instanceof Error ? e.message : String(e)
  } finally {
    if (mia === carga) cargando.value = false
  }
}

onMounted(() => {
  if (!mapaEl.value) return
  mapa = L.map(mapaEl.value, { center: CENTRO, zoom: 7, zoomControl: true })
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(mapa)
  capaRutas = L.layerGroup().addTo(mapa)
  capaBuses = L.layerGroup().addTo(mapa)
  // El contenedor cambia de ancho al abrir/cerrar sidebars o al cambiar de breakpoint.
  observador = new ResizeObserver(() => mapa?.invalidateSize())
  observador.observe(mapaEl.value)
  window.addEventListener('keydown', alTeclear)
  void cargar(false)
  temporizador = setInterval(() => void cargar(true), REFRESCO_MS)
  animacion = setInterval(animar, ANIMACION_MS)
})

onBeforeUnmount(() => {
  clearInterval(temporizador)
  clearInterval(animacion)
  window.removeEventListener('keydown', alTeclear)
  document.body.style.overflow = ''
  observador?.disconnect()
  mapa?.remove()
  mapa = null
  marcadores.clear()
})
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
  color: var(--p-surface-800);
  background: #fff;
  border: 1px solid var(--p-surface-400);
  border-radius: 4px;
  box-shadow: none;
  white-space: nowrap;
}
/* Saeta hacia el punto: ::before es el borde y ::after el relleno blanco. */
.leaflet-tooltip.enclave-chip.leaflet-tooltip-right::before {
  border-right-color: var(--p-surface-400);
}
.leaflet-tooltip.enclave-chip.leaflet-tooltip-right::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 0;
  margin-top: -5px;
  margin-left: -9px;
  border: 5px solid transparent;
  border-right-color: #fff;
  pointer-events: none;
}
.mapa-maximizado .mapa-buses {
  height: 100%;
  min-height: 18rem;
}
.bus-marker-body {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--c);
  border: 2px solid #fff;
  box-shadow: 0 1px 6px rgb(0 0 0 / 0.45);
}
.bus-marker-body.is-still {
  opacity: 0.8;
}
.bus-marker-body.is-active {
  width: 40px;
  height: 40px;
  margin: -3px;
  outline: 3px solid color-mix(in srgb, var(--c) 45%, transparent);
}
</style>
