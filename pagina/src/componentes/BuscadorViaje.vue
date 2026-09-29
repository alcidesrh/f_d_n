<!-- Origen, destino y fecha del viaje. Emite la búsqueda; la página navega. -->
<template>
  <form class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_1fr_12rem_auto] md:items-end" @submit.prevent="buscar">
    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Salgo de</span>
      <Select
        v-model="origen"
        :options="estaciones"
        option-label="nombre"
        option-value="id"
        filter
        placeholder="Origen"
        :loading="cargando"
        fluid
        @update:model-value="cargarDestinos"
      />
    </label>
    <Button
      type="button"
      severity="secondary"
      text
      rounded
      class="hidden self-end md:inline-flex"
      aria-label="Invertir origen y destino"
      :disabled="!origen || !destino"
      @click="invertir"
    >
      <icon name="arrows-exchange" />
    </Button>
    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Voy a</span>
      <Select
        v-model="destino"
        :options="destinos"
        option-label="nombre"
        option-value="id"
        filter
        placeholder="Destino"
        :disabled="!origen"
        fluid
      />
    </label>
    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">Fecha</span>
      <DatePicker v-model="fecha" :min-date="hoy" date-format="dd/mm/yy" show-icon fluid />
    </label>
    <Button type="submit" label="Buscar" :disabled="!origen || !destino || !fecha" class="md:self-end">
      <template #icon><icon name="arrow-right" class="order-last ml-1" /></template>
    </Button>
  </form>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import * as api from '@/api'
import type { Opcion } from '@/tipos'

const props = defineProps<{ origenInicial?: number | null; destinoInicial?: number | null; fechaInicial?: Date | null }>()
const emit = defineEmits<{ buscar: [b: { origen: number; destino: number; fecha: Date }] }>()

const hoy = new Date(new Date().setHours(0, 0, 0, 0))
const estaciones = ref<Opcion[]>([])
const destinos = ref<Opcion[]>([])
const origen = ref<number | null>(props.origenInicial ?? null)
const destino = ref<number | null>(props.destinoInicial ?? null)
const fecha = ref<Date | null>(props.fechaInicial ?? hoy)
const cargando = ref(false)

onMounted(async () => {
  cargando.value = true
  try {
    estaciones.value = await api.estaciones()
    if (origen.value) await cargarDestinos(origen.value, false)
  } finally {
    cargando.value = false
  }
})

async function cargarDestinos(id: number | null, limpiar = true) {
  if (limpiar) destino.value = null
  destinos.value = id ? await api.destinos(id) : []
}

async function invertir() {
  const [o, d] = [destino.value, origen.value]
  origen.value = o
  await cargarDestinos(o)
  destino.value = destinos.value.some((x) => x.id === d) ? d : null
}

function buscar() {
  if (origen.value && destino.value && fecha.value) {
    emit('buscar', { origen: origen.value, destino: destino.value, fecha: fecha.value })
  }
}
</script>
