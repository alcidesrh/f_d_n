<!-- Asientos apartados, total y tiempo que queda para pagar. -->
<template>
  <div class="flex flex-col gap-3">
    <div v-if="carrito.carrito?.trayecto" class="text-sm">
      <div class="font-semibold">{{ carrito.carrito.trayecto.origen }} → {{ carrito.carrito.trayecto.destino }}</div>
      <div class="text-muted-color">{{ fechaLarga(carrito.carrito.recorrido?.salidaOrigen) }}, {{ hora(carrito.carrito.recorrido?.salidaOrigen) }} · {{ carrito.carrito.recorrido?.empresa }}</div>
    </div>
    <ul v-if="!carrito.vacio" class="m-0 flex list-none flex-col gap-1 p-0">
      <li v-for="a in carrito.asientos" :key="a.asiento" class="flex items-center justify-between gap-2">
        <span>Asiento <b>{{ a.numero }}</b> <span class="text-xs text-muted-color">clase {{ a.clase }}</span></span>
        <span class="tabular-nums">{{ a.precio.texto }}</span>
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-muted-color">Toque un asiento libre en el croquis para apartarlo.</p>
    <div class="flex items-baseline justify-between border-t border-surface-200 pt-2">
      <span class="text-muted-color">Total</span>
      <span class="text-xl font-bold tabular-nums">{{ carrito.carrito?.total?.texto ?? 'Q 0.00' }}</span>
    </div>
    <Message v-if="!carrito.vacio" :severity="tiempo.segundos < 120 ? 'warn' : 'secondary'" :closable="false" size="small">
      <span class="flex items-center gap-1"><icon name="clock" size="1rem" /> Sus asientos quedan apartados {{ tiempo.texto }} min.</span>
    </Message>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useCarrito } from '@/carrito'
import { fechaLarga, hora, restante } from '@/modelo'

const emit = defineEmits<{ vencio: [] }>()
const carrito = useCarrito()
const ahora = ref(Date.now())
const reloj = setInterval(() => {
  ahora.value = Date.now()
  if (!carrito.vacio && tiempo.value.segundos === 0) emit('vencio')
}, 1000)
onBeforeUnmount(() => clearInterval(reloj))

const tiempo = computed(() => restante(carrito.carrito?.expira ?? null, ahora.value))
</script>
