<!--
  Configuración de la venta en la página: recargo sobre la tarifa (el
  `compra_porciento` del legado), venta en línea activa y cierre antes de
  cada salida. Los cambios rigen para los carritos nuevos: un pago ya
  iniciado se cobra con el recargo que tenía. También las reglas con las que
  el cliente modifica su compra desde «Mis compras»: reasignaciones por
  asiento, tiempo mínimo antes de la salida y penalización.
-->
<template>
  <div class="grid grid-cols-1 gap-4 @4xl:grid-cols-[minmax(0,32rem)_minmax(0,1fr)]">
    <section class="flex flex-col gap-5">
      <Skeleton v-if="!config" height="16rem" />
      <template v-else>
        <div class="flex items-center justify-between gap-3">
          <div>
            <label for="pw-venta" class="font-medium">Venta en línea activa</label>
            <p class="m-0 text-xs text-muted-color">Apagada, la página muestra los horarios pero no vende.</p>
          </div>
          <ToggleSwitch v-model="form.ventaEnLinea" input-id="pw-venta" />
        </div>

        <label class="flex flex-col gap-1.5">
          <span class="font-medium">Recargo sobre la tarifa (%)</span>
          <InputNumber v-model="form.recargo" :min="0" :max="config.limites.recargoMaximo" :min-fraction-digits="0" :max-fraction-digits="2" suffix=" %" fluid />
          <span class="text-xs text-muted-color">Se suma al precio de cada asiento vendido en la página (no en taquilla). Ej.: una tarifa de Q 100.00 se vende en <b>{{ ejemplo }}</b>.</span>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="font-medium">Cierre de la venta en línea (minutos antes de la salida)</span>
          <InputNumber v-model="form.cierre" :min="config.limites.cierreMinimo" :max="config.limites.cierreMaximo" suffix=" min" fluid />
          <span class="text-xs text-muted-color">Mínimo {{ config.limites.cierreMinimo }}: los asientos apartados se liberan a más tardar 30 minutos antes de salir.</span>
        </label>

        <fieldset class="m-0 flex flex-col gap-5 rounded-xl border border-surface-200 p-4 dark:border-surface-700">
          <legend class="px-1 font-semibold">Modificación de compras por el cliente</legend>

          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Reasignaciones de un mismo asiento</span>
            <InputNumber v-model="form.porAsiento" :min="0" :max="config.limites.modificacionesMaximo" fluid />
            <span class="text-xs text-muted-color">Veces que el cliente puede cambiar la hora, la ruta, el asiento o la clase de un mismo asiento comprado. Con 0, el cliente no puede modificar sus compras.</span>
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Tiempo mínimo antes de la salida para modificar (minutos)</span>
            <InputNumber v-model="form.minutos" :min="0" :max="config.limites.modificacionMinutosMaximo" suffix=" min" fluid />
            <span class="text-xs text-muted-color">Ej.: con {{ form.minutos ?? 0 }} minutos, una salida de las 8:30 se puede modificar hasta las <b>{{ ejemploLimite }}</b>.</span>
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="font-medium">Penalización por modificar (Q)</span>
            <InputNumber v-model="form.penalizacion" :min="0" :max="config.limites.penalizacionMaxima" :min-fraction-digits="2" :max-fraction-digits="2" prefix="Q " fluid />
            <span class="text-xs text-muted-color">Con 0 no hay penalización. Se suma a la diferencia de precio y va en la factura. Agregar asientos no se penaliza.</span>
          </label>

          <div class="flex items-center justify-between gap-3" :class="{ 'opacity-60': !form.penalizacion }">
            <div>
              <label for="pw-penalizacion-asiento" class="font-medium">Cobrar la penalización por cada asiento cambiado</label>
              <p class="m-0 text-xs text-muted-color">Apagado, se cobra una sola vez por modificación.</p>
            </div>
            <ToggleSwitch v-model="form.penalizacionPorAsiento" input-id="pw-penalizacion-asiento" :disabled="!form.penalizacion" />
          </div>
        </fieldset>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span v-if="config.actualizadaEn" class="text-xs text-muted-color">Último cambio: {{ fechaHora(config.actualizadaEn) }}<template v-if="config.actualizadaPor"> por {{ config.actualizadaPor }}</template></span>
          <Button label="Guardar" :loading="guardando" :disabled="!cambio" @click="guardar"><template #icon><icon name="save-outline" class="mr-1" /></template></Button>
        </div>
      </template>
    </section>
    <aside class="text-sm text-muted-color">
      <h3 class="m-0 mb-2 text-sm font-semibold text-color">Cómo funciona</h3>
      <ul class="m-0 flex flex-col gap-1.5 pl-4">
        <li>El cliente elige asientos; al pulsar «Pagar asientos» se apartan (en taquilla se ven ocupados) por 15 minutos, extensibles mientras paga.</li>
        <li>Si no termina, se liberan solos; <code>app:venta:purgar</code> borra las filas y avisa a los croquis abiertos.</li>
        <li>Ida y vuelta: un cobro con el comercio de la empresa de la ida y una venta (y factura) por viaje.</li>
        <li>«Mis compras»: el cliente entra sin contraseña (con el navegador donde compró o el enlace del correo del boleto), descarga la factura de sus viajes pasados y modifica los próximos. No puede anular ni cancelar.</li>
        <li>Al modificar, el asiento más caro paga la diferencia y el más barato no se reembolsa. Si cambia lo que lleva la factura, se anula y se emite otra.</li>
      </ul>
    </aside>
  </div>
</template>

<script setup lang="ts">
import { fetchConfiguracion, guardarConfiguracion } from '@/core/pagina-web/api'
import { quetzales } from '@/core/pagina-web/filtro'
import type { ConfiguracionPagina } from '@/core/pagina-web/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const config = ref<ConfiguracionPagina | null>(null)
const form = reactive({
  ventaEnLinea: true,
  recargo: 0 as number | null,
  cierre: 60 as number | null,
  porAsiento: 2 as number | null,
  minutos: 60 as number | null,
  penalizacion: 0 as number | null,
  penalizacionPorAsiento: false,
})
const guardando = ref(false)
const error = ref('')

const ejemplo = computed(() => quetzales(Math.round(10000 * (1 + (form.recargo ?? 0) / 100))))
const cambio = computed(
  () =>
    !!config.value &&
    (form.ventaEnLinea !== config.value.ventaEnLinea ||
      Number(form.recargo ?? 0) !== Number(config.value.recargoPorciento) ||
      form.cierre !== config.value.cierreMinutos ||
      form.porAsiento !== config.value.modificaciones.porAsiento ||
      form.minutos !== config.value.modificaciones.minutos ||
      Number(form.penalizacion ?? 0) !== Number(config.value.modificaciones.penalizacion) ||
      form.penalizacionPorAsiento !== config.value.modificaciones.penalizacionPorAsiento),
)
/** Hasta qué hora se modifica una salida de las 8:30 con los minutos del formulario. */
const ejemploLimite = computed(() => {
  const minutos = 8 * 60 + 30 - (form.minutos ?? 0)
  const dia = ((minutos % 1440) + 1440) % 1440
  const hora = `${Math.floor(dia / 60)}:${String(dia % 60).padStart(2, '0')}`
  return minutos < 0 ? `${hora} del día anterior` : hora
})
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))

function aplicar(c: ConfiguracionPagina) {
  config.value = c
  form.ventaEnLinea = c.ventaEnLinea
  form.recargo = Number(c.recargoPorciento)
  form.cierre = c.cierreMinutos
  form.porAsiento = c.modificaciones.porAsiento
  form.minutos = c.modificaciones.minutos
  form.penalizacion = Number(c.modificaciones.penalizacion)
  form.penalizacionPorAsiento = c.modificaciones.penalizacionPorAsiento
}

onMounted(async () => {
  try {
    aplicar(await fetchConfiguracion())
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
})

async function guardar() {
  guardando.value = true
  error.value = ''
  try {
    aplicar(
      await guardarConfiguracion({
        ventaEnLinea: form.ventaEnLinea,
        recargoPorciento: String(form.recargo ?? 0),
        cierreMinutos: form.cierre ?? 60,
        modificaciones: {
          porAsiento: form.porAsiento ?? 0,
          minutos: form.minutos ?? 0,
          penalizacion: String(form.penalizacion ?? 0),
          penalizacionPorAsiento: form.penalizacionPorAsiento,
        },
      }),
    )
    notify.success('Configuración de la página guardada')
  } catch (e) {
    const cuerpo = e instanceof HttpError ? (e.body as { error?: string } | null) : null
    error.value = cuerpo?.error ?? (e instanceof Error ? e.message : String(e))
  } finally {
    guardando.value = false
  }
}
</script>
