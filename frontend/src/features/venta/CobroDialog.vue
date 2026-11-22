<!--
  Cobro de la venta: resumen, total según tarifa, forma y moneda de pago y
  envío de la factura por correo. El cobro con tarjeta se hace en el POS
  (fuera del sistema); aquí solo se registra la forma de pago. Con asientos
  de varias salidas, una venta (y una factura) por cada una.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    :header="cortesia ? 'Emitir cortesía' : 'Facturar'"
    :style="{ width: '32rem' }"
    @update:visible="emit('update:visible', $event)"
  >
    <FormKit v-model="datos" type="form" :actions="false" @submit="confirmar">
      <dl class="mb-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted-color">Cliente</dt>
        <dd>{{ store.cliente?.label }}</dd>
        <template v-for="v in ventas" :key="v.clave">
          <dt class="text-muted-color">Viaje</dt>
          <dd>
            {{ v.ruta }}
            <span v-if="store.variasSalidas" class="text-muted-color">· {{ v.fecha }}</span>
          </dd>
          <dt class="text-muted-color">Asientos</dt>
          <dd class="flex justify-between gap-2">
            <span>{{ v.numeros }}</span>
            <span v-if="store.variasSalidas" class="tabular-nums">{{ v.total }}</span>
          </dd>
        </template>
      </dl>
      <Message v-if="store.variasSalidas" severity="info" :closable="false" class="mb-3">
        {{ cortesia ? `Se emiten ${ventas.length} cortesías` : `Se registran ${ventas.length} ventas` }}, una por salida{{ !cortesia && facturaElectronica ? ', cada una con su factura' : '' }}, y se imprime un ticket por cada una.
      </Message>

      <template v-if="!cortesia">
        <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
          <FormKit type="Select" name="tipoPago" label="Tipo de pago" :options="tiposPago" fluid />
          <FormKit type="Select" name="moneda" label="Moneda de pago" :options="monedas" fluid />
        </div>
        <label
          v-if="facturaElectronica"
          class="mb-4 flex items-center gap-2 text-sm"
          :class="correo ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
        >
          <Checkbox v-model="datos.enviarCorreo" binary :disabled="!correo" />
          <span>{{ correo ? `Enviar factura a ${correo}` : 'Enviar factura al correo del cliente' }}</span>
        </label>
      </template>
      <Message v-else severity="info" :closable="false" class="mb-3">
        Los asientos se emiten sin cobro y sin factura.
      </Message>

      <label v-if="store.totalAsientos > 1" class="mb-4 flex cursor-pointer items-center gap-2 text-sm">
        <Checkbox v-model="datos.imprimirPorSeparado" binary />
        <span>Imprimir por separado (un ticket por asiento)</span>
      </label>

      <div class="my-4 flex items-baseline justify-between rounded-lg bg-emphasis p-3">
        <span class="text-muted-color">Total</span>
        <span class="text-2xl font-semibold tabular-nums">{{ total }}</span>
      </div>
      <Message v-if="store.errorCotizacion" severity="error" :closable="false" class="mb-3">
        {{ store.errorCotizacion }}
      </Message>

      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
        <Button
          type="submit"
          :label="cortesia ? 'Emitir' : 'Aceptar'"
          :loading="store.vendiendo"
          :disabled="!store.puedeVender"
        />
      </div>
    </FormKit>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { OpcionesCobro } from './store'
import { fechaCorta, useVentaStore } from './store'

const props = defineProps<{ visible: boolean; cortesia: boolean }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  confirmar: [opciones: OpcionesCobro]
}>()

const store = useVentaStore()
const datos = ref<{
  tipoPago: number | null
  moneda: number | null
  enviarCorreo: boolean
  imprimirPorSeparado: boolean
}>({
  tipoPago: null,
  moneda: null,
  enviarCorreo: false,
  imprimirPorSeparado: false,
})

const tiposPago = computed(() =>
  (store.contexto?.tiposPago ?? []).map((t) => ({ label: t.nombre, value: t.id })),
)
const monedas = computed(() =>
  (store.contexto?.monedas ?? []).map((m) => ({ label: `${m.sigla} · ${m.nombre}`, value: m.id })),
)
const correo = computed(() => store.cliente?.email ?? null)
const facturaElectronica = computed(() => store.contexto?.canal === 'estacion')
const total = computed(() => store.total?.texto ?? '—')
/** Lo que se cobra en cada salida (una venta por cada una). */
const ventas = computed(() =>
  store.grupos.map((g) => ({
    clave: g.clave,
    ruta: g.ruta,
    fecha: fechaCorta(g.fecha),
    numeros: g.asientos
      .map((a) => g.numeros[a] ?? a)
      .sort((a, b) => a - b)
      .join(', '),
    total: g.cotizacion?.total.texto ?? '…',
  })),
)

watch(
  () => props.visible,
  (visible) => {
    // Al cerrar una cortesía sin emitirla, los precios vuelven a los de la tarifa.
    if (!visible) {
      if (props.cortesia) void store.recotizar()
      return
    }
    void store.recotizar(props.cortesia)
    const efectivo = store.contexto?.tiposPago.find((t) => /efectivo/i.test(t.nombre))
    const gtq = store.contexto?.monedas.find((m) => m.sigla === 'GTQ')
    datos.value = {
      tipoPago: datos.value.tipoPago ?? efectivo?.id ?? store.contexto?.tiposPago[0]?.id ?? null,
      moneda: datos.value.moneda ?? gtq?.id ?? store.contexto?.monedas[0]?.id ?? null,
      enviarCorreo: !!correo.value,
      imprimirPorSeparado: false,
    }
  },
)

function confirmar() {
  emit('confirmar', {
    tipoPago: props.cortesia ? null : datos.value.tipoPago,
    moneda: props.cortesia ? null : datos.value.moneda,
    enviarCorreo:
      !props.cortesia && facturaElectronica.value && datos.value.enviarCorreo && !!correo.value,
    cortesia: props.cortesia,
    imprimirPorSeparado: store.totalAsientos > 1 && datos.value.imprimirPorSeparado,
  })
}
</script>
