<!--
  Elegir a dónde pasa un asiento (o dónde se agrega uno) al modificar una
  compra: ruta (origen y destino), fecha, salida (la hora) y asiento en el
  croquis en vivo. Cambiar de clase es elegir un asiento de la otra clase.
  Emite el asiento elegido; no aparta nada (eso pasa al confirmar).
-->
<template>
  <div class="flex flex-col gap-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">{{ t('buscador.origen') }}</span>
        <SelectorEstacion v-model="origen" :grupos="gruposOrigen" :placeholder="t('buscador.origenPlaceholder')" input-id="mod-origen" :cargando="!estaciones.length" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">{{ t('buscador.destino') }}</span>
        <SelectorEstacion v-model="destino" :grupos="gruposDestino" :placeholder="t('buscador.destinoPlaceholder')" input-id="mod-destino" :disabled="!origen" :vacio="t('buscador.sinDestinos')" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">{{ t('modificar.fecha') }}</span>
        <DatePicker v-model="fecha" :min-date="hoy" :manual-input="false" show-icon icon-display="input" input-id="mod-fecha" date-format="dd/mm/yy" fluid />
      </label>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="buscando" height="6rem" border-radius="1rem" />
    <p v-else-if="origen && destino && !salidas.length && !error" class="m-0 rounded-xl bg-surface-50 p-4 text-sm text-muted-color">{{ t('modificar.sinSalidas') }}</p>

    <ul v-if="salidas.length" class="m-0 flex list-none flex-col gap-2 p-0" :aria-label="t('modificar.elijaSalida')">
      <li v-for="s in salidas" :key="s.id">
        <button
          type="button"
          class="flex w-full cursor-pointer flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl border bg-white px-4 py-3 text-left"
          :class="elegida?.id === s.id ? 'border-marca-700 ring-2 ring-marca-700/20' : 'border-surface-200 hover:border-marca-700/50'"
          :disabled="s.disponibles === 0"
          :aria-pressed="elegida?.id === s.id"
          @click="elegirSalida(s)"
        >
          <span class="flex flex-col">
            <span class="text-lg font-semibold">{{ s.salida ? hora(s.salida, region) : hora(s.salidaInicio, region) }}<span v-if="!s.subeEnOrigen" class="ml-1 text-xs font-normal text-muted-color">{{ t('salidas.aprox') }}</span></span>
            <span class="text-xs text-muted-color">{{ s.empresa }} · {{ s.bus }} · {{ s.ruta }}</span>
          </span>
          <span class="flex flex-col items-end text-sm">
            <span class="font-semibold text-acento-700">{{ s.clases.map((c) => `${c.clase} ${c.precio.texto}`).join(' · ') }}</span>
            <span class="text-xs" :class="s.disponibles ? 'text-muted-color' : 'text-red-700'">{{ t('modificar.libres', s.disponibles) }}</span>
          </span>
        </button>
      </li>
    </ul>

    <template v-if="elegida">
      <Skeleton v-if="!detalle && !errorCroquis" height="18rem" border-radius="1rem" />
      <Message v-if="errorCroquis" severity="error" :closable="false">{{ errorCroquis }}</Message>
      <div v-if="detalle" class="flex flex-col gap-2">
        <p class="m-0 text-sm font-medium">{{ t('modificar.elijaAsiento') }}</p>
        <div class="overflow-x-auto">
          <BusMap :elementos="detalle.croquis" :estado="estado" interactivo class="mx-auto justify-center" @asiento="tocar" />
        </div>
        <p class="m-0 text-xs text-muted-color">{{ detalle.precios.map((p) => `${t('modificar.clase', { clase: p.clase })}: ${p.precio.texto}`).join(' · ') }}</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BusMap from '@/shared/bus-map/BusMap.vue'
import type { AsientoCroquis } from '@/core/croquis/types'
import * as api from '@/api'
import SelectorEstacion from '@/componentes/compra/SelectorEstacion.vue'
import { mensajeDeError } from '@/errores'
import { REGION, type Idioma } from '@/i18n'
import { desdeISO, diaISO, estadoEnMapa, hora, porDepartamento } from '@/modelo'
import type { AsientoDestino } from '@/modificacion'
import { suscribir } from '@/tiempoReal'
import type { Estacion, Salida, SalidaPublico } from '@/tipos'

const props = defineProps<{
  /** Ruta y día de partida: los del asiento que se cambia (o los del viaje). */
  origen: number
  destino: number
  dia: string
  /** `salida-asiento` ya elegidos en esta modificación: no se eligen dos veces. */
  tomados: ReadonlySet<string>
}>()
const emit = defineEmits<{ elegido: [asiento: AsientoDestino] }>()
const { t, te, locale } = useI18n()
const region = computed(() => REGION[locale.value as Idioma])

const hoy = new Date()
const origen = ref<number | null>(props.origen)
const destino = ref<number | null>(props.destino)
const inicial = desdeISO(props.dia)
const fecha = ref<Date | null>(inicial && inicial >= new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate()) ? inicial : hoy)

const estaciones = ref<Estacion[]>([])
const destinos = ref<Estacion[]>([])
const gruposOrigen = computed(() => porDepartamento(estaciones.value, t('buscador.otros'), locale.value))
const gruposDestino = computed(() => porDepartamento(destinos.value, t('buscador.otros'), locale.value))

const salidas = ref<Salida[]>([])
const buscando = ref(false)
const error = ref('')
const elegida = ref<Salida | null>(null)
const detalle = ref<SalidaPublico | null>(null)
const errorCroquis = ref('')
let desuscribir: (() => void) | null = null
let pedido = 0

onMounted(async () => {
  estaciones.value = await api.estaciones().catch(() => [])
})
onBeforeUnmount(() => desuscribir?.())

watch(
  origen,
  async (o, anterior) => {
    destinos.value = o ? await api.destinos(o).catch(() => []) : []
    // Al cambiar el origen, el destino solo se conserva si sigue siendo posible.
    if (anterior !== undefined && destino.value && !destinos.value.some((d) => d.id === destino.value)) destino.value = null
  },
  { immediate: true },
)

watch(
  [origen, destino, fecha],
  async () => {
    const n = ++pedido
    soltarSalida()
    salidas.value = []
    error.value = ''
    if (!origen.value || !destino.value || !fecha.value) return
    buscando.value = true
    try {
      const lista = await api.salidas(origen.value, destino.value, diaISO(fecha.value))
      // Solo las que todavía se venden en línea.
      if (n === pedido) salidas.value = lista.filter((s) => new Date(s.cierre).getTime() > Date.now())
    } catch (e) {
      if (n === pedido) error.value = mensajeDeError(e, t, te).titulo
    } finally {
      if (n === pedido) buscando.value = false
    }
  },
  { immediate: true },
)

function soltarSalida() {
  desuscribir?.()
  desuscribir = null
  elegida.value = null
  detalle.value = null
  errorCroquis.value = ''
}

async function elegirSalida(s: Salida) {
  soltarSalida()
  elegida.value = s
  await cargarCroquis()
  if (detalle.value && elegida.value?.id === s.id) desuscribir = suscribir((detalle.value as SalidaPublico).topico, () => void cargarCroquis())
}

async function cargarCroquis() {
  const s = elegida.value
  if (!s) return
  try {
    const d = await api.salida(s.id, s.trayecto, null)
    if (elegida.value?.id === s.id) detalle.value = d
  } catch (e) {
    errorCroquis.value = mensajeDeError(e, t, te).titulo
  }
}

const precios = computed(() => new Map((detalle.value?.precios ?? []).map((p) => [p.clase, p.precio.centavos])))
const estado = computed(() => {
  const s = elegida.value
  const ocupacion = [
    ...(detalle.value?.ocupacion ?? []),
    // Los que ya eligió en esta modificación se ven ocupados.
    ...(detalle.value?.croquis ?? [])
      .filter((e): e is AsientoCroquis => e.tipo === 'asiento' && e.id != null && !!s && props.tomados.has(`${s.id}-${e.id}`))
      .map((e) => ({ asiento: e.id as number, estado: 'reservado' as const })),
  ]
  return estadoEnMapa(ocupacion, new Set(), new Set(precios.value.keys()))
})

function tocar(a: AsientoCroquis) {
  const s = elegida.value
  const d = detalle.value
  const centavos = precios.value.get(a.clase)
  if (!s || !d || a.id == null || centavos == null || props.tomados.has(`${s.id}-${a.id}`)) return
  emit('elegido', {
    salida: s.id,
    trayecto: s.trayecto,
    asiento: a.id,
    numero: a.numero,
    clase: a.clase,
    centavos,
    hora: s.salida ?? s.salidaInicio,
    origen: d.trayecto.origen,
    destino: d.trayecto.destino,
  })
}
</script>
