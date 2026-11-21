<!--
  Lo elegido para cobrar, encima de «Cortesía» y «Facturar»: un chip por
  asiento con su precio y la ✕ para quitarlo. Con asientos de varias salidas,
  cada chip dice en su tooltip la fecha y el viaje, y los de la salida abierta
  se resaltan.
-->
<template>
  <ul v-if="store.totalAsientos" class="m-0 flex list-none flex-wrap gap-1.5 p-0" aria-label="Asientos elegidos">
    <template v-for="g in store.grupos" :key="g.clave">
      <li
        v-for="a in ordenados(g)"
        :key="`${g.clave}-${a}`"
        v-tooltip.top="store.variasSalidas ? `${fechaCorta(g.fecha)} · ${g.ruta}${g.empresa ? ` · ${g.empresa}` : ''}` : undefined"
        class="elegido"
        :class="{ 'elegido--otra': store.variasSalidas && g.clave !== store.claveActual }"
      >
        <span class="font-mono font-semibold">#{{ g.numeros[a] ?? a }}</span>
        <span class="tabular-nums text-muted-color">{{ precio(g, a) }}</span>
        <button type="button" class="elegido__quitar tap-target" :aria-label="`Quitar el asiento ${g.numeros[a] ?? a} (${g.ruta})`" :disabled="store.vendiendo" @click="store.quitarAsiento(g.clave, a)">
          <icon name="close" size="0.85rem" />
        </button>
      </li>
    </template>
  </ul>
</template>

<script setup lang="ts">
import { fechaCorta, type GrupoVenta, useVentaStore } from './store'

const store = useVentaStore()

const ordenados = (g: GrupoVenta) => [...g.asientos].sort((a, b) => (g.numeros[a] ?? a) - (g.numeros[b] ?? b))
const precio = (g: GrupoVenta, asiento: number) =>
  g.cotizacion?.asientos.find((x) => x.asiento === asiento)?.precio.texto ?? '…'
</script>

<style scoped>
.elegido {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.15rem 0.2rem 0.15rem 0.6rem;
  border: 1px solid var(--p-primary-color);
  border-radius: 999px;
  background: var(--p-primary-50);
  font-size: 0.8rem;
}
/* De otra salida que la abierta: mismo chip, sin el resalte. */
.elegido--otra {
  border-color: var(--p-surface-300);
  background: transparent;
}
.elegido__quitar {
  display: inline-grid;
  place-items: center;
  width: 1.4rem;
  height: 1.4rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.elegido__quitar:hover:not(:disabled) {
  background: var(--p-surface-200);
  color: var(--p-text-color);
}
.elegido__quitar:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
