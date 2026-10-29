<!--
  Días del programador: el primero y cómo se repite (solo ese día, N días o
  hasta una fecha, cada X días). Debajo, cuántos días y salidas resultan.
-->
<template>
  <section class="flex flex-col gap-3" aria-labelledby="pg-dias">
    <h2 id="pg-dias" class="pg-titulo"><span class="pg-paso">3</span>Días</h2>
    <div class="grid gap-3 @xl:grid-cols-[minmax(0,1fr)_auto] @xl:items-end">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Primer día</span>
        <DatePicker v-model="desde" date-format="dd/mm/yy" show-icon fluid :min-date="hoy" />
      </label>
      <div class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Repetir</span>
        <SelectButton v-model="store.form.repeticion" :options="REPETICIONES" option-label="l" option-value="v" :allow-empty="false" class="flex-wrap" aria-label="Repetir" />
      </div>
    </div>
    <div v-if="store.form.repeticion !== 'no'" class="grid grid-cols-2 gap-3">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Cada</span>
        <InputNumber v-model="intervalo" :min="1" :max="MAX_INTERVALO" show-buttons suffix=" día(s)" fluid />
      </label>
      <label v-if="store.form.repeticion === 'veces'" class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Cuántos días</span>
        <InputNumber v-model="veces" :min="1" :max="MAX_DIAS" show-buttons fluid />
      </label>
      <label v-else class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Hasta (incluido)</span>
        <DatePicker v-model="hasta" date-format="dd/mm/yy" show-icon fluid :min-date="desde ?? hoy" />
      </label>
    </div>
    <p v-if="dias.length" class="m-0 text-sm">
      <b>{{ dias.length }}</b> día(s){{ dias.length > 1 ? `, del ${diaCorto(dias[0]!)} al ${diaCorto(dias[dias.length - 1]!)}` : `: ${diaLargo(dias[0]!)}` }} ·
      <b>{{ totalSalidas(store.form) }}</b> salida(s) en total
    </p>
  </section>
</template>

<script setup lang="ts">
import { aDia, deDia } from '@/core/salida/filtro'
import { MAX_DIAS, MAX_INTERVALO, totalSalidas } from '@/core/salida/programacion'
import { diaCorto, diaLargo } from './formato'
import { useProgramadorStore } from './store'

const REPETICIONES = [
  { v: 'no', l: 'Solo ese día' },
  { v: 'veces', l: 'N días' },
  { v: 'hasta', l: 'Hasta una fecha' },
]

const store = useProgramadorStore()
const dias = computed(() => store.dias)

const hoy = new Date()
hoy.setHours(0, 0, 0, 0)

/** Campos numéricos/fecha de PrimeVue ↔ el formulario. */
const intervalo = computed<number | null>({ get: () => store.form.intervaloDias, set: (v) => (store.form.intervaloDias = v ?? 1) })
const veces = computed<number | null>({ get: () => store.form.veces, set: (v) => (store.form.veces = v ?? 1) })
const desde = computed<Date | null>({ get: () => (store.form.desde ? deDia(store.form.desde) : null), set: (d) => (store.form.desde = d ? aDia(d) : null) })
const hasta = computed<Date | null>({ get: () => (store.form.hasta ? deDia(store.form.hasta) : null), set: (d) => (store.form.hasta = d ? aDia(d) : null) })
</script>
