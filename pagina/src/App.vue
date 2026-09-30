<template>
  <div class="flex min-h-dvh flex-col">
    <header class="sticky top-0 z-20 border-b border-surface-200 bg-white/90 backdrop-blur">
      <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4">
        <RouterLink :to="{ name: 'inicio' }" class="flex items-center gap-2 font-bold text-primary no-underline">
          <icon name="bus" size="1.6rem" />
          <span class="leading-tight">Fuente del Norte<span class="block text-xs font-normal text-muted-color">Boletos en línea</span></span>
        </RouterLink>
        <RouterLink
          v-if="!carrito.vacio && $route.name !== 'pago'"
          :to="{ name: 'pago' }"
          class="flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 text-sm font-medium text-primary-contrast no-underline"
        >
          <icon name="armchair" size="1rem" />
          {{ carrito.asientos.length }} · {{ carrito.carrito?.total?.texto }}
        </RouterLink>
      </div>
    </header>

    <main class="flex-1">
      <RouterView />
    </main>

    <footer class="border-t border-surface-200 bg-white">
      <div class="mx-auto grid max-w-6xl gap-2 px-4 py-6 text-sm text-muted-color md:grid-cols-3">
        <p class="m-0">Pago seguro con Visa y Mastercard (3-D Secure).</p>
        <p class="m-0">El boleto llega a su correo y puede descargarlo al terminar.</p>
        <p class="m-0">La venta en línea cierra 30 minutos antes de cada salida.</p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useCarrito } from './carrito'

const carrito = useCarrito()
onMounted(() => void carrito.refrescar())
</script>
