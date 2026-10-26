<!--
  Anular boletos (antes de la hora de salida). Muestra los boletos, pide el
  motivo y avisa lo que implica: la factura electrónica de la venta se anula
  en la SAT y, en agencias, se devuelve el saldo. El asiento queda libre.
  Una factura conjunta (varios boletos) se anula entera: el diálogo lo dice,
  muestra los demás boletos que cubre para agregarlos y, si quedan algunos,
  se certifica otra factura solo con ellos.
  Lo que una regla impide pero admite autorización (sin permiso, fuera de
  hora, pasado el límite diario) se intenta igual: con una aprobada se usa
  sola; sin ella, ofrece pedirla (ADR-040).
-->
<template>
  <Dialog :visible="visible" modal header="Anular boletos" class="w-[min(36rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <div class="flex flex-col gap-3">
      <Skeleton v-if="cargando" height="8rem" />
      <Message v-else-if="errorCarga" severity="error" :closable="false">{{ errorCarga }}</Message>
      <template v-else>
        <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
          <li v-for="b in boletos" :key="b.id" class="boleto" :class="{ 'boleto-no': !b.operable && !b.conAutorizacion }">
            <div class="flex items-baseline justify-between gap-2">
              <span class="font-semibold">Asiento {{ b.asiento.numero }} <span class="font-normal text-muted-color">· {{ b.pasajero ?? 'sin pasajero' }}</span></span>
              <span class="tabular-nums">{{ b.precio?.texto ?? '—' }}</span>
            </div>
            <div class="text-xs text-muted-color">{{ b.trayecto.origen }} → {{ b.trayecto.destino }} · {{ fechaHora(b.salida.fecha) }} · venta {{ b.venta.id }}</div>
            <div v-if="!b.operable && b.conAutorizacion" class="text-xs text-amber-700">{{ b.motivo }} Se puede con una autorización.</div>
            <div v-else-if="!b.operable" class="text-xs text-red-600">{{ b.motivo }}</div>
          </li>
        </ul>

        <Message v-if="bloqueo" severity="warn" :closable="false">{{ bloqueo }}. Quítelo de la selección para continuar.</Message>
        <Message v-for="f in facturas" :key="f.venta" severity="warn" :closable="false">
          <template v-if="f.cubre > 1">
            <div class="font-semibold">Factura conjunta{{ f.factura ? ` ${f.factura}` : '' }} · venta {{ f.venta }}</div>
            <div>Cubre {{ f.cubre }} boletos. Se anulará en la SAT{{ f.quedan.length ? ` y se emitirá una nueva con ${f.quedan.length === 1 ? 'el boleto que queda' : `los ${f.quedan.length} boletos que quedan`}` : '' }}.</div>
            <ul v-if="f.quedan.length" class="m-0 mt-2 flex list-none flex-col gap-1 p-0">
              <li v-for="c in companerosDe(f.quedan)" :key="c.id" class="flex items-center justify-between gap-2">
                <span class="min-w-0 truncate">Asiento {{ c.asiento.numero }} · {{ c.pasajero ?? 'sin pasajero' }} · {{ c.precio?.texto ?? '—' }}</span>
                <Button label="Agregar" size="small" severity="secondary" :disabled="!agregable(c) || enviando || !!resultado" :title="agregable(c) ? 'Anularlo también' : (c.motivo ?? '')" @click="agregar([c.id])">
                  <template #icon><icon name="add" class="mr-1" /></template>
                </Button>
              </li>
              <li v-if="companerosDe(f.quedan).length < f.quedan.length" class="text-xs">{{ f.quedan.length - companerosDe(f.quedan).length }} boleto(s) que no puede ver.</li>
            </ul>
            <Button v-if="companerosDe(f.quedan).filter(agregable).length > 1" label="Agregar todos" size="small" severity="secondary" variant="outlined" class="mt-2" :disabled="enviando || !!resultado" @click="agregar(companerosDe(f.quedan).filter(agregable).map((c) => c.id))" />
          </template>
          <template v-else>La factura electrónica{{ f.factura ? ` ${f.factura}` : '' }} de la venta {{ f.venta }} se anulará en la SAT.</template>
        </Message>
        <Message v-if="deAgencia" severity="info" :closable="false">Se devolverá el precio al saldo de la agencia.</Message>

        <label class="flex flex-col gap-1">
          <span class="text-sm font-medium">Motivo de la anulación</span>
          <Textarea v-model="motivo" rows="2" auto-resize maxlength="200" placeholder="P. ej. el pasajero desistió del viaje" :disabled="enviando || !!resultado" fluid />
        </label>

        <Message v-if="error" :severity="autorizable ? 'warn' : 'error'" :closable="false">
          {{ error }}
          <Button v-if="autorizable" label="Pedir autorización" size="small" class="ml-2" @click="pedirAutorizacion">
            <template #icon><icon name="verified-user-outline" class="mr-1" /></template>
          </Button>
        </Message>
        <template v-if="resultado">
          <Message v-if="resultado.anulados.length" severity="success" :closable="false">{{ resultado.anulados.length }} boleto(s) anulado(s).</Message>
          <Message v-for="f in resultado.fallidos" :key="f.venta" severity="error" :closable="false">Venta {{ f.venta }}: {{ f.error }}</Message>
          <Message v-for="r in resultado.refacturadas ?? []" :key="`r${r.venta}`" :severity="r.error ? 'warn' : 'success'" :closable="false">{{ textoRefactura(r) }}</Message>
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
import { computed, ref, shallowRef } from 'vue'
import { anularBoletos, errorVenta, fetchBoletos } from '@/core/venta/api'
import { motivoNoOperable } from '@/core/venta/reasignacion'
import { facturasEnAnulacion } from '@/core/venta/anulacion'
import type { BoletoOperable, ResultadoAnulacion } from '@/core/venta/types'
import { notify } from '@/core/notify'
import { bloqueoAutorizable } from '@/core/autorizacion/reglas'
import { useAutorizacionesStore } from '@/core/autorizacion/store'

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
/** Boletos de una factura conjunta que el usuario sumó a la selección. */
const extra = ref<number[]>([])
/** Los demás boletos que cubren las facturas conjuntas (para ofrecer agregarlos). */
const companeros = ref<BoletoOperable[]>([])
/** El último bloqueo, si admite autorización (para ofrecer pedirla). */
const autorizable = shallowRef<unknown>(null)
const autorizaciones = useAutorizacionesStore()

/** Se cierra: la solicitud tiene su propio diálogo; aprobada, se vuelve a anular. */
function pedirAutorizacion() {
  autorizaciones.ofrecer(autorizable.value)
  emit('update:visible', false)
}
const todos = computed(() => [...new Set([...props.ids, ...extra.value])])

const facturas = computed(() => facturasEnAnulacion(boletos.value, todos.value))
const companerosDe = (ids: number[]) => companeros.value.filter((c) => ids.includes(c.id))
/** Se puede sumar si se puede anular (o se puede con una autorización). */
const agregable = (b: BoletoOperable) => b.operable || !!b.conAutorizacion
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
  companeros.value = []
  cargando.value = true
  try {
    boletos.value = await fetchBoletos(todos.value)
    await cargarCompaneros()
  } catch (e) {
    errorCarga.value = errorVenta(e)?.error ?? 'No se pudieron cargar los boletos.'
  } finally {
    cargando.value = false
  }
}

async function anular() {
  enviando.value = true
  error.value = ''
  autorizable.value = null
  try {
    const r = await anularBoletos(todos.value, motivo.value.trim())
    resultado.value = r
    if (r.anulados.length) {
      emit('listo', r)
      const refacturaFallida = r.refacturadas?.some((x) => x.error)
      if (!r.fallidos.length && !refacturaFallida) {
        const nuevas = r.refacturadas?.length ? ` · ${r.refacturadas.length === 1 ? 'factura nueva emitida' : `${r.refacturadas.length} facturas nuevas emitidas`}` : ''
        notify.success((r.anulados.length === 1 ? 'Boleto anulado' : `${r.anulados.length} boletos anulados`) + nuevas)
        emit('update:visible', false)
      }
    }
  } catch (e) {
    const err = errorVenta(e)
    error.value = err?.error ?? 'No se pudo anular.'
    autorizable.value = bloqueoAutorizable(e) ? e : null
  } finally {
    enviando.value = false
  }
}

/** Los boletos de las facturas conjuntas que no están en la selección; si no se pueden leer, solo se dice cuántos son. */
async function cargarCompaneros() {
  const ids = [...new Set(facturas.value.flatMap((f) => f.quedan))]
  if (!ids.length) return
  try {
    companeros.value = await fetchBoletos(ids)
  } catch {
    companeros.value = []
  }
}

/** Suma boletos de la factura conjunta a la selección. */
function agregar(ids: number[]) {
  const nuevos = companeros.value.filter((c) => ids.includes(c.id) && !todos.value.includes(c.id))
  extra.value = [...extra.value, ...nuevos.map((c) => c.id)]
  boletos.value = [...boletos.value, ...nuevos]
  error.value = ''
}

function textoRefactura(r: NonNullable<ResultadoAnulacion['refacturadas']>[number]) {
  if (r.error) return `Venta ${r.venta}: la factura de los boletos que quedan no se certificó (${r.error}). Quedó pendiente y se reintentará.`
  const numero = [r.factura?.serie, r.factura?.numero].filter((x) => x != null).join('-')
  return `Venta ${r.venta}: se emitió la factura ${numero} con los boletos que quedan.`
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
