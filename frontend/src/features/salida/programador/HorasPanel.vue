<!--
  Horas de salida del programador (ADR-031): cada hora con su distribución
  de asientos (molde) y un bus que la tiene; debajo, a qué hora llega a su
  destino según la clase de ese bus. Horas de 5 en 5 minutos.
-->
<template>
  <section class="flex flex-col gap-2" aria-labelledby="pg-horas">
    <h2 id="pg-horas" class="pg-titulo"><span class="pg-paso">2</span>Horas de salida</h2>
    <p class="m-0 text-xs text-muted-color">
      Elija la distribución de asientos y luego un bus que la tenga. Si después cambia de bus por otro con la misma distribución, los boletos vendidos pasan solos al mismo número de
      asiento.
    </p>
    <ol class="m-0 flex list-none flex-col gap-3 p-0">
      <li v-for="(m, i) in store.form.momentos" :key="i" class="momento">
        <InputText
          v-model="m.hora"
          type="time"
          :step="PASO_MINUTOS * 60"
          :aria-label="`Hora n.º ${i + 1}`"
          class="momento__hora tabular-nums"
          @change="m.hora = redondearHora(m.hora)"
        />
        <CroquisSelector
          :model-value="m.croquisId"
          :croquis="store.croquisVisibles"
          :buses="store.busesVisibles"
          :aria-label="`Distribución de la hora n.º ${i + 1}`"
          class="momento__croquis"
          @update:model-value="(id) => store.elegirCroquis(m, id)"
        />
        <Select
          :model-value="m.busId"
          :options="busesPara(store.busesVisibles, { croquisId: m.croquisId }, m.busId)"
          :option-label="etiquetaBus"
          option-value="id"
          filter
          :filter-fields="['codigo', 'matricula']"
          fluid
          placeholder="Bus"
          :empty-message="m.croquisId ? 'Ningún bus con esta distribución' : 'Sin buses'"
          :aria-label="`Bus de la hora n.º ${i + 1}`"
          class="momento__bus"
          @update:model-value="(id: number | null) => store.elegirBus(m, id)"
        >
          <template #option="{ option }"><BusOpcionItem :bus="option" :empresas="store.empresas" :clases="store.clases" /></template>
        </Select>
        <Button
          severity="secondary"
          text
          rounded
          class="tap-target momento__quitar"
          :aria-label="`Quitar la hora n.º ${i + 1}`"
          :disabled="store.form.momentos.length === 1"
          @click="store.quitarHora(i)"
        >
          <template #icon><icon name="close" /></template>
        </Button>
        <p v-if="llegada(m)" class="momento__detalle m-0 text-xs text-muted-color">{{ llegada(m) }}</p>
      </li>
    </ol>
    <div class="flex flex-wrap gap-2">
      <Button label="Agregar hora" size="small" severity="secondary" outlined :disabled="store.form.momentos.length >= MAX_MOMENTOS" @click="store.agregarHora">
        <template #icon><icon name="add" class="mr-1" /></template>
      </Button>
      <Button v-if="store.form.momentos.length > 1" label="Ordenar por hora" size="small" text @click="store.ordenarHoras">
        <template #icon><icon name="sort" class="mr-1" /></template>
      </Button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { busesPara, duracionConClase, sumarMinutos } from '@/core/salida/buses'
import { horaValida, MAX_MOMENTOS, normalizarHora, PASO_MINUTOS, redondearHora } from '@/core/salida/programacion'
import type { BusOpcion, Momento } from '@/core/salida/types'
import BusOpcionItem from '../BusOpcionItem.vue'
import CroquisSelector from '../CroquisSelector.vue'
import { useProgramadorStore } from './store'

const store = useProgramadorStore()

const etiquetaBus = (b: BusOpcion) => [b.codigo, b.matricula, b.empresaId ? store.empresas.get(b.empresaId) : null].filter(Boolean).join(' · ')

/** «Llega a Flores a las 16:30, en Platino.» */
function llegada(m: Momento): string | null {
  if (!m.busId || !store.ruta || !horaValida(m.hora)) return null
  const bus = store.busDe(m.busId)
  const minutos = duracionConClase(store.ruta, bus?.claseId ?? null)
  if (minutos === null) return null
  const l = sumarMinutos(normalizarHora(m.hora), minutos)
  const clase = bus?.claseId ? store.clases.get(bus.claseId) : null
  return `Llega a ${store.ruta.destino ?? 'su destino'} a las ${l.hora}${l.dias ? ` (+${l.dias} d)` : ''}${clase ? `, en ${clase}` : ''}.`
}
</script>

<style scoped>
/* Angosto: hora, distribución y quitar; el bus debajo. Ancho: todo en una línea. */
.momento {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  grid-template-areas:
    'hora croquis quitar'
    'bus bus bus'
    'detalle detalle detalle';
  align-items: center;
  gap: 0.5rem;
}
/* La hora mide lo que el navegador dibuja (en el teléfono, «06:00 a. m.» y
   su reloj no caben en 6.5rem y se montaban bajo la distribución). */
.momento__hora {
  grid-area: hora;
  width: 6.5rem;
}
@supports (field-sizing: content) {
  .momento__hora {
    width: auto;
    min-width: 6.5rem;
    field-sizing: content;
  }
}
.momento__croquis {
  grid-area: croquis;
}
.momento__bus {
  grid-area: bus;
  min-width: 0;
}
.momento__quitar {
  grid-area: quitar;
}
.momento__detalle {
  grid-area: detalle;
}
@container (min-width: 40rem) {
  .momento {
    grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr) auto;
    grid-template-areas:
      'hora croquis bus quitar'
      '. detalle detalle .';
    row-gap: 0.25rem;
  }
}
</style>
