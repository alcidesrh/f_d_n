<!--
  "Ver" una salida: el croquis del bus con el estado de cada asiento (como en
  taquilla), cuánto se vendió por clase y canal, lo cobrado, la tripulación,
  quién la programó y los dos manifiestos en PDF (interno y del piloto).
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
      <header class="flex flex-col gap-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="text-lg font-semibold">{{ detalle.trayecto.origen.nombre }} → {{ detalle.trayecto.destino.nombre }}</div>
          <Tag :value="etiquetaEstado(detalle.estado).etiqueta" :severity="etiquetaEstado(detalle.estado).severidad" />
        </div>
        <dl class="panel !p-3 m-0 grid grid-cols-2 gap-x-4 gap-y-2 text-sm @2xl:grid-cols-4">
          <div class="col-span-2"><dt class="text-xs text-muted-color">Salida</dt><dd class="m-0 first-letter:uppercase">{{ fechaHora(detalle.salida) }}</dd></div>
          <div><dt class="text-xs text-muted-color">Empresa</dt><dd class="m-0">{{ detalle.empresa?.nombre ?? '—' }}</dd></div>
          <div><dt class="text-xs text-muted-color">Bus</dt><dd class="m-0">{{ detalle.bus ? detalle.bus.codigo : 'Sin bus' }}<span v-if="detalle.bus?.gama" class="text-muted-color"> · {{ detalle.bus.gama }}</span></dd></div>
          <div><dt class="text-xs text-muted-color">Piloto</dt><dd class="m-0">{{ detalle.pilotos[0] }}</dd></div>
          <div><dt class="text-xs text-muted-color">Copiloto</dt><dd class="m-0">{{ detalle.pilotos[1] }}</dd></div>
          <div class="col-span-2"><dt class="text-xs text-muted-color">Programada por</dt><dd class="m-0">{{ detalle.creadaPor ?? 'Sistema anterior' }}<span v-if="detalle.creadaEn" class="text-muted-color"> · {{ fechaCorta(detalle.creadaEn) }}</span></dd></div>
        </dl>
      </header>

      <div class="grid grid-cols-1 items-start gap-4 @2xl:grid-cols-[minmax(0,19rem)_minmax(0,1fr)]">
        <section class="flex min-w-0 flex-col gap-3">
          <div v-if="!detalle.croquis.length" class="panel text-center text-sm text-muted-color">La salida no tiene bus con croquis.</div>
          <template v-else>
            <div class="overflow-x-auto">
              <BusMap :elementos="detalle.croquis" :estado="estado" inspeccionable tamano="md" class="justify-center" @ocupado="verOcupado" />
              <AsientoOcupadoPopover ref="detalleAsiento" @anular="(b) => (anular = [b.id])" @reasignar="reasignar" />
            </div>
            <BusMapLegend :items="LEYENDA" :conteos="conteos" />
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
  <AnularBoletosDialog :visible="anular.length > 0" :ids="anular" @update:visible="(v) => !v && (anular = [])" @listo="cargar" />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AnularBoletosDialog from '@/shared/boleto/AnularBoletosDialog.vue'
import AsientoOcupadoPopover from '@/shared/bus-map/AsientoOcupadoPopover.vue'
import BusMap from '@/shared/bus-map/BusMap.vue'
import BusMapLegend, { type ItemLeyenda } from '@/shared/bus-map/BusMapLegend.vue'
import { etiquetaEstado } from '@/core/salida/filtro'
import { fetchDetalle } from '@/core/salida/api'
import type { DetalleSalida, SalidaFila, TipoManifiesto } from '@/core/salida/types'
import type { AsientoCroquis } from '@/core/croquis/types'
import { conteosPorEstado, estadoEnMapa } from '@/core/venta/modelo'
import { abrirManifiesto, MANIFIESTOS } from './manifiesto'

const LEYENDA: ItemLeyenda[] = ['disponible', 'B', 'ocupado', 'ocupado-web', 'ocupado-agencia', 'reservado', 'cortesia', 'voucher']

const props = defineProps<{ salida: Pick<SalidaFila, 'id'> | null }>()
const emit = defineEmits<{ cerrar: [] }>()

const router = useRouter()
/** Boletos que se están anulando (desde el detalle del asiento). */
const anular = ref<number[]>([])
/** La reasignación se hace en la pantalla de venta, con la salida y el asiento nuevos. */
function reasignar(boleto: { id: number }) {
  emit('cerrar')
  void router.push({ name: 'venta', query: { reasignar: String(boleto.id) } })
}

const detalleAsiento = ref<InstanceType<typeof AsientoOcupadoPopover> | null>(null)
const verOcupado = (a: AsientoCroquis, e: MouseEvent) => {
  if (props.salida && a.id != null) void detalleAsiento.value?.abrir(e, props.salida.id, a.id)
}
const detalle = ref<DetalleSalida | null>(null)
const error = ref('')
const generando = ref<TipoManifiesto | null>(null)

const resumen = computed(() => detalle.value!.resumen)
const estado = computed(() => estadoEnMapa(detalle.value?.ocupados ?? [], []))
const conteos = computed(() => conteosPorEstado(detalle.value?.croquis ?? [], estado.value))
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

const fechaCorta = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

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
