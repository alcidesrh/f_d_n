<!--
  Cobro de la venta: resumen, total según tarifa, forma y moneda de pago y
  envío de la factura por correo. El cobro con tarjeta se hace en el POS
  (fuera del sistema); aquí solo se registra la forma de pago.
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
      <dl class="mb-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted-color">Cliente</dt>
        <dd>{{ store.cliente?.label }}</dd>
        <dt class="text-muted-color">Viaje</dt>
        <dd>{{ viaje }}</dd>
        <dt class="text-muted-color">Asientos</dt>
        <dd>{{ numeros }}</dd>
      </dl>

      <template v-if="!cortesia">
        <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
          <FormKit type="Select" name="tipoPago" label="Tipo de pago" :options="tiposPago" fluid />
          <FormKit type="Select" name="moneda" label="Moneda de pago" :options="monedas" fluid />
        </div>
        <FormKit
          v-if="facturaElectronica"
          type="Checkbox"
          name="enviarCorreo"
          :label="correo ? `Enviar factura a ${correo}` : 'Enviar factura al correo del cliente'"
          :disabled="!correo"
          binary
        />
      </template>
      <Message v-else severity="info" :closable="false" class="mb-3">
        Los asientos se emiten sin cobro y sin factura.
      </Message>

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
          :disabled="!store.cotizacion"
        />
      </div>
    </FormKit>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { OpcionesCobro } from './store'
import { useVentaStore } from './store'

const props = defineProps<{ visible: boolean; cortesia: boolean }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  confirmar: [opciones: OpcionesCobro]
}>()

const store = useVentaStore()
const datos = ref<{ tipoPago: number | null; moneda: number | null; enviarCorreo: boolean }>({
  tipoPago: null,
  moneda: null,
  enviarCorreo: false,
})

const tiposPago = computed(() =>
  (store.contexto?.tiposPago ?? []).map((t) => ({ label: t.nombre, value: t.id })),
)
const monedas = computed(() =>
  (store.contexto?.monedas ?? []).map((m) => ({ label: `${m.sigla} · ${m.nombre}`, value: m.id })),
)
const correo = computed(() => store.cliente?.email ?? null)
const facturaElectronica = computed(() => store.contexto?.canal === 'estacion')
const total = computed(() => store.cotizacion?.total.texto ?? '—')
const numeros = computed(() =>
  (store.cotizacion?.asientos ?? [])
    .map((a) => a.numero)
    .sort((a, b) => a - b)
    .join(', '),
)
const viaje = computed(() => {
  const nombre = (id: number | null) =>
    store.detalle?.paradas.find((p) => p.id === id)?.nombre ?? '—'
  return `${nombre(store.sube)} → ${nombre(store.baja)}`
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    void store.recotizar(props.cortesia)
    const efectivo = store.contexto?.tiposPago.find((t) => /efectivo/i.test(t.nombre))
    const gtq = store.contexto?.monedas.find((m) => m.sigla === 'GTQ')
    datos.value = {
      tipoPago: datos.value.tipoPago ?? efectivo?.id ?? store.contexto?.tiposPago[0]?.id ?? null,
      moneda: datos.value.moneda ?? gtq?.id ?? store.contexto?.monedas[0]?.id ?? null,
      enviarCorreo: !!correo.value,
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
  })
}
</script>
