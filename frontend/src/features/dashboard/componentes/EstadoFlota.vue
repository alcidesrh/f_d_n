<!--
  Flota en uso (buses con salidas del último mes o de la próxima semana):
  cuántos trabajaron en el periodo y los que tienen la tarjeta de
  operaciones vencida o por vencer (riesgo de multa o de bus detenido).
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="grid grid-cols-3 gap-2 text-center">
      <div class="cifra">
        <span class="text-lg font-semibold tabular-nums">{{ flota.enServicio }}<span class="text-sm text-muted-color">/{{ flota.buses }}</span></span>
        <span class="text-xs text-muted-color">trabajaron en el periodo</span>
      </div>
      <div class="cifra">
        <span class="text-lg font-semibold tabular-nums" :class="{ 'text-red-700 dark:text-red-400': flota.vencidas.length }">{{ flota.vencidas.length }}</span>
        <span class="text-xs text-muted-color">tarjeta vencida</span>
      </div>
      <div class="cifra">
        <span class="text-lg font-semibold tabular-nums" :class="{ 'text-amber-700 dark:text-amber-400': flota.porVencer.length }">{{ flota.porVencer.length }}</span>
        <span class="text-xs text-muted-color">vence en 30 días</span>
      </div>
    </div>
    <div v-if="flota.buses - flota.enServicio > 0" class="text-sm text-muted-color">
      <icon name="garage-outline" class="mr-1 align-middle" />{{ flota.buses - flota.enServicio }} buses de la flota en uso no hicieron ninguna salida en el periodo.
    </div>
    <div v-if="lista.length" class="flex flex-col">
      <div v-for="b in lista" :key="b.id" class="bus">
        <icon name="directions-bus-outline" class="text-muted-color" />
        <span class="font-medium">Bus {{ b.codigo }}</span>
        <span class="ml-auto text-xs tabular-nums" :class="b.vencida ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'">
          {{ b.vencida ? 'venció' : 'vence' }} {{ fechaCorta(b.vence) }}{{ anio(b.vence) !== anioActual ? ` ${anio(b.vence)}` : '' }}
        </span>
      </div>
      <p v-if="total > lista.length" class="pt-1 text-xs text-muted-color">y {{ total - lista.length }} más.</p>
    </div>
    <p v-else class="text-sm text-green-700 dark:text-green-400"><icon name="verified-outline" class="mr-1 align-middle" />Todas las tarjetas de operación al día.</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { fechaCorta } from '@/core/tablero/formato'
import type { Flota } from '@/core/tablero/types'

const props = defineProps<{ flota: Flota }>()
const anioActual = new Date().getFullYear()
const anio = (f: string) => Number(f.slice(0, 4))
const total = computed(() => props.flota.vencidas.length + props.flota.porVencer.length)
const lista = computed(() => [...props.flota.vencidas.map((b) => ({ ...b, vencida: true })), ...props.flota.porVencer.map((b) => ({ ...b, vencida: false }))].slice(0, 6))
</script>

<style scoped>
.cifra {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.5rem;
  border-radius: 0.5rem;
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.06));
}
.bus {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  border-bottom: 1px solid var(--p-content-border-color);
  font-size: 0.875rem;
}
</style>
