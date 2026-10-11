<!--
  Vista previa de una ruta en el mapa (Leaflet, como el seguimiento): las
  paradas con coordenadas unidas en orden. Las que no tienen coordenadas se
  cuentan debajo; el trazo salta de la anterior a la siguiente que sí tiene.
-->
<template>
  <div class="flex flex-col gap-2">
    <div ref="mapaEl" class="mapa-ruta rounded-border" role="img" :aria-label="`Mapa de la ruta con ${conCoordenadas.length} paradas ubicadas`" />
    <p v-if="sinCoordenadas.length" class="m-0 text-xs text-muted-color">Sin ubicación en el mapa: {{ sinCoordenadas.join(", ") }}.</p>
  </div>
</template>

<script setup lang="ts">
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps<{ paradas: Array<{ nombre: string; latitud: number | null; longitud: number | null }> }>();

const CENTRO: L.LatLngExpression = [15.5, -90.3];
const mapaEl = ref<HTMLElement | null>(null);
let mapa: L.Map | null = null;
let capa: L.LayerGroup | null = null;
let observador: ResizeObserver | null = null;

const conCoordenadas = computed(() => props.paradas.map((p, i) => ({ ...p, n: i + 1 })).filter((p) => p.latitud !== null && p.longitud !== null));
const sinCoordenadas = computed(() => props.paradas.filter((p) => p.latitud === null || p.longitud === null).map((p) => p.nombre));

function dibujar() {
  if (!mapa || !capa) return;
  capa.clearLayers();
  // Leaflet pone el color como atributo SVG, donde `var()` no se resuelve.
  const color = getComputedStyle(mapaEl.value!).getPropertyValue("--p-primary-color").trim() || "#3b82f6";
  const puntos = conCoordenadas.value.map((p) => [p.latitud!, p.longitud!] as L.LatLngTuple);
  if (puntos.length > 1) L.polyline(puntos, { color, weight: 3, opacity: 0.85 }).addTo(capa);
  conCoordenadas.value.forEach((p, i) => {
    const punta = i === 0 || i === conCoordenadas.value.length - 1;
    L.circleMarker([p.latitud!, p.longitud!], { radius: punta ? 7 : 5, color, weight: 2, fillColor: punta ? color : "#fff", fillOpacity: 1 })
      .bindTooltip(`${p.n}. ${p.nombre}`, { direction: "right" })
      .addTo(capa!);
  });
  if (puntos.length) mapa.fitBounds(L.latLngBounds(puntos), { padding: [24, 24], maxZoom: 11 });
}

onMounted(() => {
  if (!mapaEl.value) return;
  mapa = L.map(mapaEl.value, { center: CENTRO, zoom: 7 });
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(mapa);
  capa = L.layerGroup().addTo(mapa);
  observador = new ResizeObserver(() => mapa?.invalidateSize());
  observador.observe(mapaEl.value);
  dibujar();
});

watch(() => props.paradas, dibujar, { deep: true });

onBeforeUnmount(() => {
  observador?.disconnect();
  mapa?.remove();
});
</script>

<style scoped>
.mapa-ruta {
  height: clamp(16rem, 45vh, 32rem);
  border: 1px solid var(--p-content-border-color);
}
</style>
