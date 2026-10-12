<!--
  Consultar saldos de agencia (ADR-034): estado, saldo disponible, total
  depositado y bonificado (depósitos acreditados) y lo que espera
  acreditación, de todas las agencias (el legado mostraba una por vez). Un
  usuario de agencia ve solo la suya. Cada agencia abre sus movimientos.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <IconField v-if="filas.length > 1" class="min-w-0 flex-1 @xl:max-w-sm">
        <InputIcon><icon name="search" /></InputIcon>
        <InputText v-model="busqueda" placeholder="Buscar agencia" aria-label="Buscar agencia" fluid />
      </IconField>
      <label v-if="filas.length > 1" class="flex items-center gap-2 text-sm">
        <Checkbox v-model="soloActivas" binary />
        <span>Solo activas</span>
      </label>
      <Button class="ml-auto" severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar">
        <template #icon><icon name="refresh" /></template>
      </Button>
    </div>

    <section v-if="filas.length > 1" class="totales panel" aria-label="Totales">
      <div><span>Agencias</span><strong>{{ visibles.length }}</strong></div>
      <div><span>Saldo disponible</span><strong>{{ suma('saldo') }}</strong></div>
      <div><span>Total depositado</span><strong>{{ suma('totalDepositado') }}</strong></div>
      <div><span>Por acreditar</span><strong>{{ suma('importePendiente') }}</strong></div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="cargando && !filas.length" height="10rem" />

    <div class="tarjetas">
      <button v-for="a in visibles" :key="a.id" type="button" class="tarjeta panel" :class="{ inactiva: !a.activo }" @click="detalle = a">
        <div class="flex items-center justify-between gap-2">
          <span class="truncate font-semibold">{{ a.nombre }}</span>
          <Tag :value="a.activo ? 'Activa' : 'Bloqueada'" :severity="a.activo ? 'success' : 'secondary'" class="!text-xs" />
        </div>
        <div class="flex items-baseline justify-between gap-2">
          <span class="text-xs text-muted-color">Saldo disponible</span>
          <span class="text-2xl font-semibold tabular-nums">{{ a.saldo.texto }}</span>
        </div>
        <dl class="cifras">
          <dt>Total depositado</dt>
          <dd>{{ a.totalDepositado.texto }}</dd>
          <dt>Bono acreditado</dt>
          <dd>{{ a.totalBonificado.texto }}<span v-if="a.porcentajeBonificacion" class="text-muted-color"> ({{ a.porcentajeBonificacion }}%)</span></dd>
          <template v-if="a.pendientes">
            <dt class="text-amber-700 dark:text-amber-400">Por acreditar ({{ a.pendientes }})</dt>
            <dd class="text-amber-700 dark:text-amber-400">{{ a.importePendiente.texto }}</dd>
          </template>
        </dl>
      </button>
    </div>
    <div v-if="!cargando && !error && !visibles.length" class="flex flex-col items-center gap-2 p-6 text-muted-color">
      <icon name="storefront-outline" size="2rem" />
      <span>{{ filas.length ? 'Ninguna agencia coincide.' : 'No hay agencias.' }}</span>
    </div>

    <Dialog :visible="detalle !== null" modal :header="detalle?.nombre ?? ''" class="w-[min(56rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && (detalle = null)">
      <div v-if="detalle" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <div>
            <div class="text-sm text-muted-color">Saldo disponible</div>
            <div class="text-3xl font-semibold tabular-nums">{{ movimientos?.saldo.texto ?? detalle.saldo.texto }}</div>
          </div>
          <Button v-if="detalle.pendientes" severity="warn" outlined size="small" :label="`${detalle.pendientes} por acreditar`" @click="irADepositos(detalle.id)" />
        </div>
        <Skeleton v-if="cargandoMovimientos" height="8rem" />
        <Message v-else-if="errorMovimientos" severity="error" :closable="false">{{ errorMovimientos }}</Message>
        <DataTable v-else-if="movimientos" :value="movimientos.movimientos" size="small" scrollable scroll-height="50dvh" data-key="id">
          <template #empty><div class="p-3 text-center text-muted-color">Sin movimientos.</div></template>
          <Column header="Fecha" class="whitespace-nowrap">
            <template #body="{ data }">{{ fecha(data.fecha) }} {{ hora(data.fecha) }}</template>
          </Column>
          <Column header="Tipo">
            <template #body="{ data }">{{ TIPOS[data.tipo] ?? data.tipo }}</template>
          </Column>
          <Column header="Monto" class="text-right tabular-nums">
            <template #body="{ data }">{{ data.monto.texto }}</template>
          </Column>
          <Column header="Saldo" class="text-right tabular-nums">
            <template #body="{ data }">{{ data.saldo.texto }}</template>
          </Column>
          <Column header="Detalle" style="min-width: 12rem">
            <template #body="{ data }">
              <span v-if="data.venta">Venta {{ data.venta }} </span>{{ [data.referencia, data.observacion].filter(Boolean).join(' · ') }}
            </template>
          </Column>
          <Column header="Usuario" field="usuario" />
        </DataTable>
        <p class="text-xs text-muted-color">Últimos 50 movimientos.</p>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import { fetchSaldosAgencias } from '@/core/agencia/api'
import { quetzales } from '@/core/agencia/depositos'
import type { SaldoAgenciaFila } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { errorVenta, fetchSaldoAgencia } from '@/core/venta/api'
import { fecha, hora } from '@/core/venta/modelo'
import type { EstadoAgencia } from '@/core/venta/types'

const TIPOS: Record<string, string> = {
  deposito: 'Depósito',
  bonificacion: 'Bonificación',
  venta: 'Venta',
  anulacion: 'Anulación',
  ajuste: 'Ajuste',
}

const router = useRouter()
const filas = shallowRef<SaldoAgenciaFila[]>([])
const cargando = ref(false)
const error = ref('')
const busqueda = ref('')
const soloActivas = ref(true)
const detalle = ref<SaldoAgenciaFila | null>(null)
const movimientos = shallowRef<EstadoAgencia | null>(null)
const cargandoMovimientos = ref(false)
const errorMovimientos = ref('')

const sinAcentos = (s: string) => s.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
const visibles = computed(() => {
  const q = sinAcentos(busqueda.value.trim())
  return filas.value.filter((a) => (!soloActivas.value || a.activo || filas.value.length === 1) && (!q || sinAcentos(a.nombre).includes(q)))
})
/** Total por moneda (hay agencias en USD): «Q 1,000.00 · USD 50.00». */
function suma(campo: 'saldo' | 'totalDepositado' | 'importePendiente'): string {
  const porMoneda = new Map<string, number>()
  for (const a of visibles.value) porMoneda.set(a[campo].moneda, (porMoneda.get(a[campo].moneda) ?? 0) + a[campo].centavos)
  return [...porMoneda].map(([moneda, centavos]) => quetzales(centavos, moneda)).join(' · ') || quetzales(0)
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    filas.value = await fetchSaldosAgencias()
  } catch (e) {
    error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para consultar saldos de agencia (agencia.depositar).' : 'No se pudieron cargar los saldos.'
  } finally {
    cargando.value = false
  }
}

watch(detalle, async (a) => {
  movimientos.value = null
  errorMovimientos.value = ''
  if (!a) return
  cargandoMovimientos.value = true
  try {
    movimientos.value = await fetchSaldoAgencia(a.id)
  } catch (e) {
    errorMovimientos.value = errorVenta(e)?.error ?? 'No se pudieron cargar los movimientos.'
  } finally {
    cargandoMovimientos.value = false
  }
})

function irADepositos(agencia: number) {
  void router.push({ name: 'agencia-depositos', query: { agencia: String(agencia), estado: 'registrado' } })
}

onMounted(cargar)
</script>

<style scoped>
.panel {
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
}
.totales {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem 1rem;
}
@container (min-width: 48rem) {
  .totales {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
.totales div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.totales span {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.totales strong {
  font-variant-numeric: tabular-nums;
}
.tarjetas {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}
@container (min-width: 40rem) {
  .tarjetas {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@container (min-width: 64rem) {
  .tarjetas {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.tarjeta {
  border: 1px solid var(--p-content-border-color);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  text-align: start;
  color: inherit;
  font: inherit;
  cursor: pointer;
  transition: border-color 0.15s;
}
.tarjeta:hover,
.tarjeta:focus-visible {
  border-color: var(--p-primary-color);
}
.tarjeta.inactiva {
  opacity: 0.7;
}
.cifras {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.125rem 1rem;
  font-size: 0.875rem;
}
.cifras dt {
  color: var(--p-text-muted-color);
}
.cifras dd {
  text-align: end;
  font-variant-numeric: tabular-nums;
}
</style>
