<!-- Viajes de una compra web (ida y, si hay, regreso) en una línea cada uno. -->
<template>
  <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
    <li v-for="(v, i) in compra.ventas" :key="v.id">
      <span v-if="compra.ventas.length > 1" class="mr-1 text-xs font-semibold uppercase text-muted-color">{{ i === 0 ? 'Ida' : 'Reg.' }}</span>
      <span class="font-medium">{{ v.origen }} → {{ v.destino }}</span>
      <span class="text-muted-color"> · {{ fecha(v.salida) }} · as. {{ v.asientos }}</span>
      <icon v-if="v.facturacion === 'pendiente'" name="warning-outline" class="ml-1 text-orange-500" v-tooltip="'Factura pendiente'" />
    </li>
    <li v-if="!compra.ventas.length" class="text-muted-color">Sin venta registrada</li>
  </ul>
</template>

<script setup lang="ts">
import type { CompraWeb } from '@/core/pagina-web/types'

defineProps<{ compra: CompraWeb }>()
const fecha = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>
