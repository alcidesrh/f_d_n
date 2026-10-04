<!--
  "Ver" una salida: el croquis del bus con el estado de cada asiento (como en
  taquilla), cuánto se vendió por clase y canal, lo cobrado, la tripulación,
  las paradas y los dos manifiestos en PDF (interno y del piloto).
  Contenido angosto: una columna; desde 40rem: croquis a la izquierda.
-->
<template>
  <Dialog :visible="!!salida" modal :header="`Salida #${salida?.id ?? ''}`" class="w-[min(60rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="error" class="flex flex-col items-start gap-2">
      <Message severity="error" :closable="false">{{ error }}</Message>
      <Button label="Reintentar" size="small" text @click="cargar" />
    </div>
    <Skeleton v-else-if="!detalle" height="22rem" />
    <div v-else class="@container flex flex-col gap-4">
      <header class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <div class="text-lg font-semibold">{{ detalle.trayecto.origen.nombre }} → {{ detalle.trayecto.destino.nombre }}</div>
          <div class="text-sm text-muted-color">
            {{ fechaHora(detalle.salida) }}<template v-if="detalle.empresa"> · {{ detalle.empresa.nombre }}</template>
            <template v-if="detalle.bus"> · Bus {{ detalle.bus.codigo }}<template v-if="detalle.bus.gama"> ({{ detalle.bus.gama }})</template></template>
          </div>
        </div>
        <Tag :value="etiquetaEstado(detalle.estado).etiqueta" :severity="etiquetaEstado(detalle.estado).severidad" />
      </header>

      <div class="grid grid-cols-1 items-start gap-4 @2xl:grid-cols-[minmax(0,19rem)_minmax(0,1fr)]">
        <section class="flex min-w-0 flex-col gap-3">
          <div v-if="!detalle.croquis.length" class="panel text-center text-sm text-muted-color">La salida no tiene bus con croquis.</div>
          <template v-else>
            <div class="overflow-x-auto">
              <BusMap :elementos="detalle.croquis" :estado="estado" tamano="md" class="justify-center" />
            </div>
            <BusMapLegend :items="LEYENDA" />
          </template>
        </section>

        <section class="flex min-w-0 flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <div class="flex items-baseline justify-between">
              <span class="text-sm font-medium">Ocupación</span>
              <span class="text-sm tabular-nums"><b>{{ resumen.vendidos }}</b> vendidos<template v-if="resumen.reservados"> · {{ resumen.reservados }} en preventa</template> · {{ libres }} libres</span>
            </div>
            <ProgressBar :value="porcentaje" :show-value="false" class="!h-2" />
            <div class="text-right text-xs text-muted-color">{{ resumen.vendidos + resumen.reservados }} de {{ resumen.asientos }} asientos ({{ porcentaje }} %)</div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="panel !p-3"><div class="text-xs text-muted-color">Cobrado</div><div class="text-lg font-semibold tabular-nums">{{ resumen.ingresos?.texto ?? 'Q 0.00' }}</div></div>
            <div class="panel !p-3"><div class="text-xs text-muted-color">Boletos vigentes</div><div class="text-lg font-semibold tabular-nums">{{ totalBoletos }}</div></div>
          </div>

          <div class="flex flex-col gap-1">
            <span class="text-sm font-medium">Por clase</span>
            <table class="w-full text-sm">
              <thead><tr class="text-left text-xs text-muted-color"><th class="font-normal">Clase</th><th class="text-right font-normal">Asientos</th><th class="text-right font-normal">Vendidos</th><th class="text-right font-normal">Libres</th></tr></thead>
              <tbody>
                <tr v-for="c in resumen.clases" :key="c.clase" class="border-t border-surface">
                  <td class="py-1">Clase {{ c.clase }}</td>
                  <td class="text-right tabular-nums">{{ c.asientos }}</td>
                  <td class="text-right tabular-nums">{{ c.vendidos }}<span v-if="c.reservados" class="text-muted-color"> +{{ c.reservados }}</span></td>
                  <td class="text-right tabular-nums">{{ c.asientos - c.vendidos - c.reservados }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="canales.length" class="flex flex-col gap-1">
            <span class="text-sm font-medium">Por canal</span>
            <div class="flex flex-wrap gap-1.5">
              <Tag v-for="c in canales" :key="c.etiqueta" severity="secondary" :value="`${c.etiqueta}: ${c.n}`" />
            </div>
          </div>

          <div class="flex flex-col gap-1 text-sm">
            <span class="text-sm font-medium">Tripulación</span>
            <div class="flex justify-between gap-2"><span class="text-muted-color">Piloto 1</span><span class="text-right">{{ detalle.pilotos[0] }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-muted-color">Piloto 2</span><span class="text-right">{{ detalle.pilotos[1] }}</span></div>
          </div>

          <div v-if="detalle.paradas.length" class="flex flex-col gap-1">
            <span class="text-sm font-medium">Paradas</span>
            <ol class="m-0 flex list-none flex-col gap-0.5 p-0 text-sm">
              <li v-for="p in detalle.paradas" :key="p.id" class="flex justify-between gap-2">
                <span>{{ p.nombre }}</span><span class="tabular-nums text-muted-color">{{ hora(p.hora) }}</span>
              </li>
            </ol>
          </div>
        </section>
      </div>
    </div>

    <template #footer>
      <div class="flex flex-wrap items-center justify-end gap-2">
        <Button v-for="m in MANIFIESTOS" :key="m.tipo" :label="m.etiqueta" severity="secondary" outlined :loading="generando === m.tipo" :disabled="!salida" @click="generar(m.tipo)">
          <template #icon><icon :name="m.icono" class="mr-1" /></template>
        </Button>
        <Button label="Cerrar" @click="emit('cerrar')" />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import BusMap from '@/shared/bus-map/BusMap.vue'
import BusMapLegend, { type ItemLeyenda } from '@/shared/bus-map/BusMapLegend.vue'
import { etiquetaEstado } from '@/core/salida/filtro'
import { fetchDetalle } from '@/core/salida/api'
import type { DetalleSalida, SalidaFila, TipoManifiesto } from '@/core/salida/types'
import { hora } from '@/core/venta/modelo'
import { estadoEnMapa } from '@/core/venta/modelo'
import { abrirManifiesto, MANIFIESTOS } from './manifiesto'

const LEYENDA: ItemLeyenda[] = ['disponible', 'B', 'ocupado', 'ocupado-web', 'ocupado-agencia', 'reservado', 'cortesia', 'voucher']

const props = defineProps<{ salida: SalidaFila | null }>()
const emit = defineEmits<{ cerrar: [] }>()

const detalle = ref<DetalleSalida | null>(null)
const error = ref('')
const generando = ref<TipoManifiesto | null>(null)

const resumen = computed(() => detalle.value!.resumen)
const estado = computed(() => estadoEnMapa(detalle.value?.ocupados ?? [], []))
const libres = computed(() => Math.max(0, resumen.value.asientos - resumen.value.vendidos - resumen.value.reservados))
const porcentaje = computed(() => (resumen.value.asientos ? Math.round(((resumen.value.vendidos + resumen.value.reservados) / resumen.value.asientos) * 100) : 0))
const totalBoletos = computed(() => Object.values(resumen.value.boletosPorEstado).reduce<number>((a, n) => a + (n ?? 0), 0))
const canales = computed(() => {
  const c = resumen.value.canales
  return [
    { etiqueta: 'Estación', n: c.estacion },
    { etiqueta: 'Agencia', n: c.agencia },
    { etiqueta: 'Página web', n: c.web },
    { etiqueta: 'Cortesía', n: c.cortesia },
    { etiqueta: 'Voucher', n: c.voucher },
  ].filter((x) => x.n > 0)
})

const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

async function generar(tipo: TipoManifiesto) {
  if (!props.salida) return
  generando.value = tipo
  await abrirManifiesto(props.salida.id, tipo)
  generando.value = null
}

let pedido = 0
async function cargar() {
  const n = ++pedido
  detalle.value = null
  error.value = ''
  if (!props.salida) return
  try {
    const r = await fetchDetalle(props.salida.id)
    if (n === pedido) detalle.value = r
  } catch (e) {
    if (n === pedido) error.value = e instanceof Error ? e.message : String(e)
  }
}
watch(() => props.salida?.id, cargar, { immediate: true })
</script>
