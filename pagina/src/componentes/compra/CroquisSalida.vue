<!--
  Croquis de una salida (el mismo mapa del bus de la taquilla) con la
  ocupación en vivo (Mercure). Tocar un asiento libre lo elige o lo suelta
  en la elección local; se aparta en el backend al pulsar "Pagar asientos".
  Si otro ocupa un asiento elegido, se quita de la elección y se avisa.
-->
<template>
  <div class="flex flex-col gap-3 p-3 md:p-4">
    <div v-if="!detalle && !error" class="flex flex-col items-center gap-3 py-6 text-sm text-muted-color">
      <Skeleton width="15rem" height="20rem" border-radius="1.5rem" />
      {{ t('croquis.cargando') }}
    </div>
    <Message v-if="error" severity="error" :closable="false">
      {{ error }}
      <Button :label="t('comun.reintentar')" size="small" text class="ml-2" @click="cargar" />
    </Message>

    <template v-if="detalle">
      <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
        <ul class="m-0 flex list-none flex-wrap items-center gap-x-3 gap-y-1 p-0 text-xs text-muted-color" :aria-label="t('salidas.ocupacion')">
          <li v-for="l in leyenda" :key="`${l.estado}-${l.clase}`" class="flex items-center gap-1">
            <span class="inline-block size-7 shrink-0"><SeatGlyph :clase="l.clase" :estado="l.estado" /></span>{{ l.texto }}
          </li>
        </ul>
        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
          <span class="size-1.5 animate-pulse rounded-full bg-emerald-500" />{{ t('croquis.enVivo') }}
        </span>
      </div>

      <Message v-if="aviso" severity="warn" class="!m-0" @close="aviso = ''">{{ aviso }}</Message>

      <div class="-mx-3 overflow-x-auto px-3 md:mx-0 md:px-0">
        <BusMap
          :elementos="detalle.croquis"
          :estado="estado"
          interactivo
          class="mx-auto justify-center"
          @asiento="tocar"
        >
          <template #planta-cabecera="{ planta }">
            <span class="text-sm font-semibold">{{ nombrePlanta(planta) }}</span>
            <span v-if="precioPlanta(planta)" class="text-sm font-semibold text-acento-700">{{ precioPlanta(planta) }}</span>
          </template>
        </BusMap>
      </div>

      <div v-if="mios.length" class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-3 text-sm ring-1 ring-surface-200">
        <span class="font-medium">{{ t('barra.asientos', mios.length) }}:</span>
        <button
          v-for="a in mios"
          :key="a.id"
          type="button"
          class="inline-flex items-center gap-1 rounded-full border-0 bg-acento-100 px-2.5 py-1 text-sm font-medium text-acento-700"
          :aria-label="`${t('pago.asiento', { numero: a.numero })} ✕`"
          @click="quitar(a)"
        >
          {{ a.numero }}<span v-if="a.clase === 'B'" class="text-xs">B</span> · {{ quetzales(a.centavos, region) }}
          <icon name="x" size="0.85rem" />
        </button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BusMap from '@/shared/bus-map/BusMap.vue'
import SeatGlyph from '@/shared/bus-map/SeatGlyph.vue'
import type { AsientoCroquis, ClaseAsiento, EstadoAsiento } from '@/core/croquis/types'
import * as api from '@/api'
import { mensajeDeError } from '@/errores'
import { REGION, type Idioma } from '@/i18n'
import { estadoEnMapa, preciosDePlanta, quetzales, type AsientoElegido } from '@/modelo'
import { suscribir } from '@/tiempoReal'
import type { Salida, SalidaPublico } from '@/tipos'
import { useViaje, type Sentido } from '@/viaje'

const props = defineProps<{ sentido: Sentido; salida: Salida }>()
const { t, te, locale } = useI18n()
const viaje = useViaje()
const detalle = ref<SalidaPublico | null>(null)
const error = ref('')
const aviso = ref('')
let desuscribir: (() => void) | null = null
let pendiente: ReturnType<typeof setTimeout> | null = null

const region = computed(() => REGION[locale.value as Idioma])
const mios = computed(() => (viaje.elegida[props.sentido]?.salida.id === props.salida.id ? viaje.elegida[props.sentido]!.asientos : []))
const precios = computed(() => new Map((detalle.value?.precios ?? []).map((p) => [p.clase, p.precio.centavos])))
const estado = computed(() => estadoEnMapa(detalle.value?.ocupacion ?? [], new Set(mios.value.map((a) => a.id)), new Set(precios.value.keys())))
const plantas = computed(() => new Set((detalle.value?.croquis ?? []).map((e) => e.planta)))

const leyenda = computed((): Array<{ estado: EstadoAsiento; clase: ClaseAsiento; texto: string }> => [
  { estado: 'disponible', clase: 'A', texto: t('croquis.disponible') },
  { estado: 'seleccionado', clase: 'A', texto: t('croquis.seleccionado') },
  { estado: 'ocupado', clase: 'A', texto: t('croquis.ocupado') },
])

function nombrePlanta(planta: number) {
  if (plantas.value.size <= 1) return t('croquis.plantaUnica')
  return planta === 1 ? t('croquis.plantaBaja') : t('croquis.plantaAlta')
}
const precioPlanta = (planta: number) =>
  preciosDePlanta(detalle.value?.croquis ?? [], planta, precios.value)
    .map((c) => quetzales(c, region.value))
    .join(' · ')

async function cargar() {
  try {
    detalle.value = await api.salida(props.salida.id, props.salida.trayecto, null)
    error.value = ''
    const quitados = viaje.depurar(props.sentido, detalle.value)
    if (quitados.length) {
      aviso.value = quitados.length === 1 ? t('croquis.tomado', { numero: quitados[0] }) : t('croquis.tomados', { numeros: quitados.join(', ') })
    }
  } catch (e) {
    error.value = mensajeDeError(e, t, te).titulo
  }
}

/** Varios avisos seguidos (p. ej. una venta de 5 asientos) recargan una sola vez. */
function alCambiar() {
  if (pendiente) clearTimeout(pendiente)
  pendiente = setTimeout(() => void cargar(), 250)
}

watch(
  () => [props.salida.id, props.salida.trayecto] as const,
  async () => {
    desuscribir?.()
    detalle.value = null
    await cargar()
    const d = detalle.value as SalidaPublico | null
    if (d) desuscribir = suscribir(d.topico, alCambiar)
  },
  { immediate: true },
)
watch(() => viaje.version, () => void cargar())
onBeforeUnmount(() => {
  desuscribir?.()
  if (pendiente) clearTimeout(pendiente)
})

function tocar(a: AsientoCroquis) {
  const centavos = precios.value.get(a.clase)
  if (a.id == null || centavos == null) return
  const elegido: AsientoElegido = { id: a.id, numero: a.numero, clase: a.clase, centavos }
  const lleno = viaje.tocar(props.sentido, props.salida, elegido)
  aviso.value = lleno ? t('croquis.maximo', { max: viaje.maxAsientos }) : ''
}

function quitar(a: AsientoElegido) {
  viaje.tocar(props.sentido, props.salida, a)
}
</script>
