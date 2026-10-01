<!-- Asientos apartados (ida y regreso), total y tiempo que queda para pagar. -->
<template>
  <div class="flex flex-col gap-3">
    <div v-for="(v, i) in carrito.viajes" :key="`${v.salida.id}-${v.trayecto.id}`" class="flex flex-col gap-1.5" :class="{ 'border-t border-surface-200 pt-3': i > 0 }">
      <p class="m-0 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide" :class="i === 0 ? 'text-marca-700' : 'text-acento-700'">
        <icon :name="i === 0 ? 'arrow-right' : 'arrow-back-up'" size="0.95rem" />{{ t(i === 0 ? 'salidas.ida' : 'salidas.regreso') }}
      </p>
      <div class="font-semibold leading-snug">{{ v.trayecto.origen }} → {{ v.trayecto.destino }}</div>
      <div class="text-sm first-letter:uppercase text-muted-color">
        {{ fechaLarga(v.salida.salidaOrigen, region) }}, {{ hora(v.salida.salidaOrigen, region) }}<template v-if="v.salida.empresa"> · <span class="normal-case">{{ v.salida.empresa }}</span></template>
      </div>
      <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
        <li v-for="a in v.asientos" :key="a.asiento" class="flex items-center justify-between gap-2">
          <span>{{ t('pago.asiento', { numero: a.numero }) }} <span class="text-xs text-muted-color">{{ t('pago.clase', { clase: a.clase }) }}</span></span>
          <span class="tabular-nums">{{ a.precio.texto }}</span>
        </li>
      </ul>
      <div v-if="carrito.viajes.length > 1" class="flex justify-between text-sm text-muted-color">
        <span>{{ t('pago.subtotal') }}</span><span class="tabular-nums">{{ v.total.texto }}</span>
      </div>
    </div>
    <div class="flex items-baseline justify-between border-t border-surface-200 pt-3">
      <span class="font-medium">{{ t('pago.total') }}</span>
      <span class="text-2xl font-bold tabular-nums text-marca-900">{{ carrito.carrito?.total?.texto ?? '—' }}</span>
    </div>
    <Message v-if="!carrito.vacio" :severity="tiempo.segundos < 120 ? 'warn' : 'info'" :closable="false" size="small">
      <span class="flex items-center gap-1.5"><icon name="clock" size="1rem" />{{ t('pago.tiempo', { tiempo: tiempo.texto }) }}</span>
    </Message>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCarrito } from '@/carrito'
import { REGION, type Idioma } from '@/i18n'
import { fechaLarga, hora, restante } from '@/modelo'

const emit = defineEmits<{ vencio: [] }>()
const { t, locale } = useI18n()
const carrito = useCarrito()
const region = computed(() => REGION[locale.value as Idioma])
const ahora = ref(Date.now())
const reloj = setInterval(() => {
  ahora.value = Date.now()
  if (!carrito.vacio && tiempo.value.segundos === 0) emit('vencio')
}, 1000)
onBeforeUnmount(() => clearInterval(reloj))

const tiempo = computed(() => restante(carrito.carrito?.expira ?? null, ahora.value))
</script>
