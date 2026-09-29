<!--
  Croquis del bus con los asientos libres en vivo. Tocar un asiento lo
  aparta (reserva de 15 min) o lo suelta; en las taquillas se ve como
  reservado mientras tanto.
-->
<template>
  <div class="mx-auto max-w-6xl px-4 py-6 pb-28 lg:pb-6">
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
      <RouterLink :to="{ name: 'inicio' }" class="ml-2 underline">Volver a buscar</RouterLink>
    </Message>
    <Message v-if="aviso" severity="warn" class="mb-4" @close="aviso = ''">{{ aviso }}</Message>
    <Skeleton v-if="!recorrido && !error" height="24rem" border-radius="1rem" />

    <div v-if="recorrido" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <section class="panel flex flex-col gap-4">
        <header>
          <h1 class="m-0 text-xl font-semibold">{{ recorrido.trayecto.origen }} → {{ recorrido.trayecto.destino }}</h1>
          <p class="m-0 text-sm text-muted-color">
            {{ fechaLarga(salidaOrigen) }} · sale {{ hora(salidaOrigen) }} · {{ recorrido.empresa }}<template v-if="recorrido.bus"> · {{ recorrido.bus }}</template>
          </p>
          <div class="mt-2 flex flex-wrap gap-2">
            <Tag v-for="p in recorrido.precios" :key="p.clase" severity="secondary" :value="`Clase ${p.clase}: ${p.precio.texto}`" />
          </div>
        </header>
        <div class="overflow-x-auto">
          <BusMap
            :elementos="recorrido.croquis"
            :estado="estado"
            interactivo
            tamano="lg"
            class="justify-center"
            @asiento="tocar"
          />
        </div>
        <BusMapLegend :items="['disponible', 'seleccionado', 'ocupado', 'B']" />
      </section>

      <aside class="panel hidden lg:sticky lg:top-20 lg:flex lg:flex-col lg:gap-3">
        <ResumenCarrito @vencio="vencio" />
        <Button label="Continuar al pago" :disabled="carrito.vacio || carrito.ocupado" @click="pagar" />
      </aside>
    </div>

    <!-- Móvil: resumen fijo abajo -->
    <div
      v-if="recorrido"
      class="fixed inset-x-0 bottom-0 z-10 flex items-center justify-between gap-3 border-t border-surface-200 bg-white px-4 py-3 shadow-[0_-4px_12px_rgb(0_0_0/0.06)] lg:hidden"
    >
      <div class="text-sm">
        <div class="font-semibold">{{ carrito.asientos.length }} asiento(s) · {{ carrito.carrito?.total?.texto ?? 'Q 0.00' }}</div>
        <div v-if="!carrito.vacio" class="text-xs text-muted-color">{{ carrito.asientos.map((a) => a.numero).join(', ') }}</div>
      </div>
      <Button label="Continuar" :disabled="carrito.vacio || carrito.ocupado" @click="pagar" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BusMap from '@/shared/bus-map/BusMap.vue'
import BusMapLegend from '@/shared/bus-map/BusMapLegend.vue'
import type { AsientoCroquis } from '@/core/croquis/types'
import * as api from '@/api'
import { useCarrito } from '@/carrito'
import ResumenCarrito from '@/componentes/ResumenCarrito.vue'
import { estadoEnMapa, fechaLarga, hora } from '@/modelo'
import { suscribir } from '@/tiempoReal'
import type { RecorridoPublico } from '@/tipos'

const props = defineProps<{ id: number; trayecto: number }>()
const router = useRouter()
const carrito = useCarrito()
const recorrido = ref<RecorridoPublico | null>(null)
const error = ref('')
const aviso = ref('')
let desuscribir: (() => void) | null = null

const estado = computed(() => estadoEnMapa(recorrido.value?.ocupacion ?? []))
const salidaOrigen = computed(() => {
  const r = recorrido.value
  if (!r) return null
  const origen = r.paradas.find((p) => p.nombre === r.trayecto.origen)
  return origen?.hora ?? r.salida
})

async function cargar() {
  try {
    recorrido.value = await api.recorrido(props.id, props.trayecto, carrito.token)
    error.value = ''
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}

watch(
  () => [props.id, props.trayecto],
  async () => {
    desuscribir?.()
    await cargar()
    if (recorrido.value) desuscribir = suscribir(recorrido.value.topico, () => void cargar())
  },
  { immediate: true },
)
onBeforeUnmount(() => desuscribir?.())

async function tocar(asiento: AsientoCroquis) {
  if (asiento.id == null || carrito.ocupado) return
  try {
    await carrito.alternar(props.id, props.trayecto, asiento.id)
    aviso.value = ''
  } catch (e) {
    aviso.value = e instanceof Error ? e.message : String(e)
  }
  await cargar()
}

async function vencio() {
  await carrito.refrescar()
  await cargar()
}

function pagar() {
  void router.push({ name: 'pago' })
}
</script>
