<!--
  Anular boletos (antes de la hora de salida). Muestra los boletos, pide el
  motivo y avisa lo que implica: la factura electrónica de la venta se anula
  en la SAT y, en agencias, se devuelve el saldo. El asiento queda libre.
-->
<template>
  <Dialog :visible="visible" modal header="Anular boletos" class="w-[min(36rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <div class="flex flex-col gap-3">
      <Skeleton v-if="cargando" height="8rem" />
      <Message v-else-if="errorCarga" severity="error" :closable="false">{{ errorCarga }}</Message>
      <template v-else>
        <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
          <li v-for="b in boletos" :key="b.id" class="boleto" :class="{ 'boleto-no': !b.operable }">
            <div class="flex items-baseline justify-between gap-2">
              <span class="font-semibold">Asiento {{ b.asiento.numero }} <span class="font-normal text-muted-color">· {{ b.pasajero ?? 'sin pasajero' }}</span></span>
              <span class="tabular-nums">{{ b.precio?.texto ?? '—' }}</span>
            </div>
            <div class="text-xs text-muted-color">{{ b.trayecto.origen }} → {{ b.trayecto.destino }} · {{ fechaHora(b.salida.fecha) }} · venta {{ b.venta.id }}</div>
            <div v-if="!b.operable" class="text-xs text-red-600">{{ b.motivo }}</div>
          </li>
        </ul>

        <Message v-if="bloqueo" severity="warn" :closable="false">{{ bloqueo }}. Quítelo de la selección para continuar.</Message>
        <Message v-if="conFactura" severity="warn" :closable="false">La factura electrónica de {{ ventasConFactura.length > 1 ? 'las ventas' : 'la venta' }} {{ ventasConFactura.join(', ') }} se anulará en la SAT. Una factura cubre todos los boletos de su venta: se anulan juntos.</Message>
        <Message v-if="deAgencia" severity="info" :closable="false">Se devolverá el precio al saldo de la agencia.</Message>

        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Motivo de la anulación</span>
          <Textarea v-model="motivo" rows="2" auto-resize maxlength="200" placeholder="P. ej. el pasajero desistió del viaje" :disabled="enviando || !!resultado" fluid />
        </label>

        <Message v-if="error" severity="error" :closable="false">
          {{ error }}
          <Button v-if="faltantes.length" :label="`Incluir ${faltantes.length === 1 ? 'el boleto que falta' : `los ${faltantes.length} boletos que faltan`}`" size="small" severity="secondary" class="ml-2" @click="incluirFaltantes" />
        </Message>
        <template v-if="resultado">
          <Message v-if="resultado.anulados.length" severity="success" :closable="false">{{ resultado.anulados.length }} boleto(s) anulado(s).</Message>
          <Message v-for="f in resultado.fallidos" :key="f.venta" severity="error" :closable="false">Venta {{ f.venta }}: {{ f.error }}</Message>
        </template>
      </template>
    </div>
    <template #footer>
      <Button :label="resultado ? 'Cerrar' : 'Cancelar'" severity="secondary" text @click="emit('update:visible', false)" />
      <Button v-if="!resultado" label="Anular boletos" severity="danger" :loading="enviando" :disabled="!puedeAnular" @click="anular" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { anularBoletos, errorVenta, fetchBoletos } from '@/core/venta/api'
import { motivoNoOperable } from '@/core/venta/reasignacion'
import type { BoletoOperable, ResultadoAnulacion } from '@/core/venta/types'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; ids: number[] }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  /** Se anuló al menos un boleto (para refrescar lo que se está viendo). */
  listo: [resultado: ResultadoAnulacion]
}>()

const boletos = ref<BoletoOperable[]>([])
const cargando = ref(false)
const errorCarga = ref('')
const motivo = ref('')
const enviando = ref(false)
const error = ref('')
const resultado = ref<ResultadoAnulacion | null>(null)
/** Boletos de la misma venta que hay que sumar para anular su factura completa. */
const extra = ref<number[]>([])
const faltantes = ref<number[]>([])
const todos = computed(() => [...new Set([...props.ids, ...extra.value])])

const ventasConFactura = computed(() => [...new Set(boletos.value.filter((b) => b.venta.estadoFacturacion === 'certificada').map((b) => b.venta.id))])
const conFactura = computed(() => ventasConFactura.value.length > 0)
const deAgencia = computed(() => boletos.value.some((b) => b.venta.canal === 'agencia'))
const bloqueo = computed(() => motivoNoOperable(boletos.value))
const puedeAnular = computed(() => !cargando.value && boletos.value.length > 0 && !bloqueo.value && motivo.value.trim().length > 0 && !enviando.value)

async function cargar() {
  boletos.value = []
  motivo.value = ''
  error.value = ''
  errorCarga.value = ''
  resultado.value = null
  extra.value = []
  faltantes.value = []
  cargando.value = true
  try {
    boletos.value = await fetchBoletos(todos.value)
  } catch (e) {
    errorCarga.value = errorVenta(e)?.error ?? 'No se pudieron cargar los boletos.'
  } finally {
    cargando.value = false
  }
}

async function anular() {
  enviando.value = true
  error.value = ''
  try {
    const r = await anularBoletos(todos.value, motivo.value.trim())
    resultado.value = r
    if (r.anulados.length) {
      emit('listo', r)
      if (!r.fallidos.length) {
        notify.success(r.anulados.length === 1 ? 'Boleto anulado' : `${r.anulados.length} boletos anulados`)
        emit('update:visible', false)
      }
    }
  } catch (e) {
    const err = errorVenta(e)
    error.value = err?.error ?? 'No se pudo anular.'
    faltantes.value = err?.codigo === 'venta_incompleta' ? (err.boletos ?? []).filter((id) => !todos.value.includes(id)) : []
  } finally {
    enviando.value = false
  }
}

/** Suma los boletos que faltan de la venta y vuelve a mostrar la lista completa. */
async function incluirFaltantes() {
  extra.value = [...extra.value, ...faltantes.value]
  faltantes.value = []
  error.value = ''
  try {
    boletos.value = await fetchBoletos(todos.value)
  } catch (e) {
    error.value = errorVenta(e)?.error ?? 'No se pudieron cargar los boletos.'
  }
}

const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>

<style scoped>
.boleto {
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.boleto-no {
  border-color: var(--p-red-300, #fca5a5);
}
</style>
