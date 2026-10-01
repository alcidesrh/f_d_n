<!--
  Origen, destino (agrupados por departamento), ida y vuelta y fechas. Edita
  `useViaje().busqueda`; la página busca al cambiar cualquier campo.
-->
<template>
  <form class="flex flex-col gap-3" @submit.prevent>
    <div class="grid grid-cols-[1fr_auto] items-end gap-x-2 gap-y-3 lg:grid-cols-[1fr_auto_1fr]">
      <label class="col-start-1 flex min-w-0 flex-col gap-1.5">
        <span class="text-sm font-medium">{{ t('buscador.origen') }}</span>
        <Select
          v-model="busqueda.origen"
          :options="gruposOrigen"
          option-label="nombre"
          option-value="id"
          option-group-label="departamento"
          option-group-children="estaciones"
          filter
          auto-filter-focus
          :filter-placeholder="t('buscador.buscarEstacion')"
          :empty-filter-message="t('buscador.sinResultados')"
          :placeholder="t('buscador.origenPlaceholder')"
          :loading="cargandoOrigenes"
          :input-id="`${id}-origen`"
          fluid
          scroll-height="18rem"
          :pt="ptSelect"
          @update:model-value="cambioOrigen"
        >
          <template #optiongroup="{ option }">
            <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-color"><icon name="map-pin" size="0.85rem" />{{ option.departamento }}</span>
          </template>
        </Select>
      </label>

      <Button
        type="button"
        severity="secondary"
        rounded
        outlined
        class="col-start-2 row-start-1 row-end-3 self-center !size-10 !p-0 lg:row-end-auto lg:mb-0.5 lg:self-end"
        :aria-label="t('buscador.invertir')"
        :title="t('buscador.invertir')"
        :disabled="!busqueda.origen || !busqueda.destino"
        @click="invertir"
      >
        <icon name="arrows-exchange" class="rotate-90 lg:rotate-0" />
      </Button>

      <label class="col-start-1 flex min-w-0 flex-col gap-1.5 lg:col-start-3 lg:row-start-1">
        <span class="text-sm font-medium">{{ t('buscador.destino') }}</span>
        <Select
          v-model="busqueda.destino"
          :options="gruposDestino"
          option-label="nombre"
          option-value="id"
          option-group-label="departamento"
          option-group-children="estaciones"
          filter
          auto-filter-focus
          :filter-placeholder="t('buscador.buscarEstacion')"
          :empty-filter-message="t('buscador.sinResultados')"
          :empty-message="t('buscador.sinDestinos')"
          :placeholder="t('buscador.destinoPlaceholder')"
          :disabled="!busqueda.origen"
          :loading="cargandoDestinos"
          :input-id="`${id}-destino`"
          fluid
          scroll-height="18rem"
          :pt="ptSelect"
        >
          <template #optiongroup="{ option }">
            <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-color"><icon name="map-pin" size="0.85rem" />{{ option.departamento }}</span>
          </template>
        </Select>
      </label>
    </div>

    <div class="grid grid-cols-2 items-end gap-3 lg:grid-cols-[auto_1fr_1fr]">
      <label class="col-span-2 flex cursor-pointer select-none items-center gap-2 py-1 lg:col-span-1 lg:mb-2.5 lg:pr-2">
        <ToggleSwitch v-model="busqueda.idaVuelta" :input-id="`${id}-iv`" />
        <span class="text-sm font-medium">{{ t('buscador.idaVuelta') }}</span>
      </label>
      <label class="flex min-w-0 flex-col gap-1.5" :class="{ 'col-span-2 lg:col-span-1': !busqueda.idaVuelta }">
        <span class="text-sm font-medium">{{ t('buscador.fechaIda') }}</span>
        <DatePicker
          v-model="fechaIda"
          :min-date="hoy"
          :max-date="limite"
          :manual-input="false"
          :placeholder="t('buscador.fechaPlaceholder')"
          show-icon
          icon-display="input"
          :input-id="`${id}-ida`"
          fluid
          :pt="{ pcInputText: { root: { class: '!bg-white' } } }"
        />
      </label>
      <label v-if="busqueda.idaVuelta" class="flex min-w-0 flex-col gap-1.5">
        <span class="text-sm font-medium">{{ t('buscador.fechaRegreso') }}</span>
        <DatePicker
          v-model="fechaRegreso"
          :min-date="fechaIda ?? hoy"
          :max-date="limite"
          :manual-input="false"
          :placeholder="t('buscador.fechaPlaceholder')"
          show-icon
          icon-display="input"
          :input-id="`${id}-regreso`"
          fluid
          :pt="{ pcInputText: { root: { class: '!bg-white' } } }"
        />
      </label>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import { desdeISO, diaISO, porDepartamento } from '@/modelo'
import type { Estacion } from '@/tipos'
import { useViaje } from '@/viaje'

const { t, locale } = useI18n()
const id = useId()
const viaje = useViaje()
const busqueda = viaje.busqueda

const hoy = new Date(new Date().setHours(0, 0, 0, 0))
/** La venta en línea se abre con este margen (no hay salidas programadas más lejos). */
const limite = new Date(new Date(hoy).setDate(hoy.getDate() + 120))

const origenes = ref<Estacion[]>([])
const destinos = ref<Estacion[]>([])
const cargandoOrigenes = ref(false)
const cargandoDestinos = ref(false)

const gruposOrigen = computed(() => porDepartamento(origenes.value, t('buscador.otros'), locale.value))
const gruposDestino = computed(() => porDepartamento(destinos.value, t('buscador.otros'), locale.value))

/** Panel con fondo sólido y ancho suficiente para nombres largos. */
const ptSelect = { overlay: { class: '!bg-white !min-w-[min(20rem,calc(100vw-2rem))]' } }

const fechaIda = computed<Date | null>({
  get: () => (busqueda.fecha ? desdeISO(busqueda.fecha) : null),
  set: (d) => {
    busqueda.fecha = d ? diaISO(d) : null
    if (busqueda.regreso && busqueda.fecha && busqueda.regreso < busqueda.fecha) busqueda.regreso = null
  },
})
const fechaRegreso = computed<Date | null>({
  get: () => (busqueda.regreso ? desdeISO(busqueda.regreso) : null),
  set: (d) => (busqueda.regreso = d ? diaISO(d) : null),
})

onMounted(async () => {
  if (!busqueda.fecha) busqueda.fecha = diaISO(hoy)
  cargandoOrigenes.value = true
  try {
    origenes.value = await api.estaciones()
    viaje.recordarNombres(origenes.value)
    if (busqueda.origen) await cargarDestinos(busqueda.origen)
  } catch {
    origenes.value = []
  } finally {
    cargandoOrigenes.value = false
  }
})

async function cargarDestinos(origen: number | null) {
  if (!origen) {
    destinos.value = []
    return
  }
  cargandoDestinos.value = true
  try {
    destinos.value = await api.destinos(origen)
    viaje.recordarNombres(destinos.value)
  } catch {
    destinos.value = []
  } finally {
    cargandoDestinos.value = false
  }
}

async function cambioOrigen(origen: number | null) {
  await cargarDestinos(origen)
  if (!destinos.value.some((d) => d.id === busqueda.destino)) busqueda.destino = null
}

async function invertir() {
  const [o, d] = [busqueda.destino, busqueda.origen]
  busqueda.origen = o
  await cargarDestinos(o)
  busqueda.destino = destinos.value.some((x) => x.id === d) ? d : null
}
</script>
