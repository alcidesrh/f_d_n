<!--
  Vista previa de una ruta en el mapa (MapLibre, `shared/mapa`, ADR-055): las
  paradas con coordenadas unidas en orden. Las que no tienen coordenadas se
  cuentan debajo; el trazo salta de la anterior a la siguiente que sí tiene.
  De `enclaves` (todos los lugares) solo se muestran, con su nombre, los activos que
  quedan sobre el trayecto (`core/ruta/trayecto.ts`). Una parada en un enclave inactivo
  no lleva punto, pero el trazo pasa por ella.
-->
<template>
  <div class="flex flex-col gap-2">
    <div ref="mapaEl" class="mapa-ruta rounded-border" role="img" :aria-label="`Mapa de la ruta con ${conCoordenadas.length} paradas ubicadas`" />
    <p v-if="sinCoordenadas.length" class="m-0 text-xs text-muted-color">Sin ubicación en el mapa: {{ sinCoordenadas.join(", ") }}.</p>
  </div>
</template>

<script setup lang="ts">
import type { Map as MapaLibre } from "maplibre-gl";
import { sobreElTrayecto, type PuntoGeo } from "@/core/ruta/trayecto";
import { BLANCO, Etiquetas, GRIS_LUGAR, asegurarCapas, colorPrimario, encuadrar, fijarDatos } from "@/shared/mapa/capas";
import { coleccion, linea, lineas, punto, type LatLng } from "@/shared/mapa/geo";
import { useMapa } from "@/shared/mapa/useMapa";

const props = defineProps<{
  paradas: Array<{ nombre: string; latitud: number | null; longitud: number | null; activo: boolean }>;
  enclaves?: Array<{ nombre: string; latitud: number | null; longitud: number | null; activo: boolean }>;
}>();

const mapaEl = ref<HTMLElement | null>(null);
let etiquetas: Etiquetas | null = null;
const { mapa, listo } = useMapa(mapaEl, (m) => {
  etiquetas = new Etiquetas(m);
  dibujar();
});

const conCoordenadas = computed(() => props.paradas.map((p, i) => ({ ...p, n: i + 1 })).filter((p) => p.latitud !== null && p.longitud !== null));
const sinCoordenadas = computed(() => props.paradas.filter((p) => p.activo && (p.latitud === null || p.longitud === null)).map((p) => p.nombre));

/** Capas (sus fuentes ya tienen datos): los lugares abajo, la ruta encima y sus paradas arriba. */
function capas(m: MapaLibre) {
  asegurarCapas(m, [
    { id: "ruta-lugares", type: "circle", source: "ruta-lugares", paint: { "circle-radius": 3, "circle-color": BLANCO, "circle-stroke-color": GRIS_LUGAR, "circle-stroke-width": 2 } },
    { id: "ruta-linea", type: "line", source: "ruta-linea", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": ["get", "color"], "line-width": 3, "line-opacity": 0.85 } },
    {
      id: "ruta-paradas",
      type: "circle",
      source: "ruta-paradas",
      paint: {
        "circle-radius": ["case", ["get", "punta"], 6, 4],
        "circle-color": ["case", ["get", "punta"], ["get", "color"], BLANCO],
        "circle-stroke-color": ["get", "color"],
        "circle-stroke-width": 2,
      },
    },
  ]);
}

function dibujar() {
  const m = mapa.value;
  if (!m || !listo.value) return;
  const color = colorPrimario(mapaEl.value);
  const puntos = conCoordenadas.value.map((p) => [p.latitud!, p.longitud!] as LatLng);
  // Solo los lugares sobre el trayecto, con su nombre; la ruta se dibuja encima.
  const lugares = sobreElTrayecto((props.enclaves ?? []).filter((e) => e.activo), puntos as PuntoGeo[]);
  fijarDatos(m, "ruta-lugares", coleccion(lugares.map((e) => punto([e.latitud!, e.longitud!]))));
  etiquetas?.fijar(lugares.map((e, i) => ({ clave: `${i}|${e.nombre}`, en: [e.latitud!, e.longitud!], texto: e.nombre })));
  fijarDatos(m, "ruta-linea", lineas([linea(puntos, { color })]));
  fijarDatos(m, "ruta-paradas", coleccion(puntos.flatMap((p, i) => (conCoordenadas.value[i]!.activo ? [punto(p, { color, punta: i === 0 || i === puntos.length - 1 })] : []))));
  capas(m);
  encuadrar(m, puntos, { margen: 24, maxZoom: 10, animar: false });
}

watch(() => [props.paradas, props.enclaves], dibujar, { deep: true });
</script>

<style scoped>
.mapa-ruta {
  height: clamp(16rem, 45vh, 32rem);
  border: 1px solid var(--p-content-border-color);
}
</style>
