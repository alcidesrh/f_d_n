<!--
  Croquis de muestra para ocupar el lugar del mapa real cuando no hay nada que
  mostrar (p. ej. ninguna salida elegida). Misma carrocería que `BusMap`, con
  asientos y señales en tono de esqueleto (como el `Skeleton` de carga). No
  tiene datos ni interacción.
-->
<template>
  <div class="croquis-muestra" aria-hidden="true">
    <BusMap :elementos="maqueta" :rejilla="rejilla" tamano="md" class="justify-center">
      <template #celda="{ elemento }">
        <span v-if="elemento?.tipo === 'asiento'" class="croquis-muestra__asiento" />
        <span v-else-if="elemento" class="croquis-muestra__senal" />
      </template>
    </BusMap>
  </div>
</template>

<script setup lang="ts">
import BusMap from "./BusMap.vue";
import type { ElementoCroquis, Rejilla } from "@/core/croquis/types";

const rejilla: Record<number, Rejilla> = { 1: { filas: 14, columnas: 5 } };

/** Chofer en la primera fila y dos asientos a cada lado del pasillo (columna 3). */
const maqueta: ElementoCroquis[] = [{ tipo: "chofer", id: null, planta: 1, fila: 1, columna: 1 }];
let numero = 1;
for (let fila = 2; fila <= 14; fila++) {
  for (const columna of [1, 2, 4, 5]) {
    maqueta.push({
      tipo: "asiento",
      id: null,
      numero: numero++,
      clase: "A",
      planta: 1,
      fila,
      columna,
    });
  }
}
</script>

<style scoped>
.croquis-muestra {
  --cm-tono: color-mix(in srgb, var(--p-text-color) 12%, var(--p-content-background));
  display: flex;
  justify-content: center;
}
.croquis-muestra__asiento,
.croquis-muestra__senal {
  display: block;
  width: 100%;
  height: 100%;
  background: var(--cm-tono);
  animation: croquis-muestra-pulso 1.6s ease-in-out infinite;
}
.croquis-muestra__asiento {
  border-radius: 30%;
}
.croquis-muestra__senal {
  border-radius: 0.25rem;
}
@keyframes croquis-muestra-pulso {
  50% {
    opacity: 0.45;
  }
}
@media (prefers-reduced-motion: reduce) {
  .croquis-muestra__asiento,
  .croquis-muestra__senal {
    animation: none;
  }
}
</style>
