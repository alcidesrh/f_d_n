<!-- Viajes de una compra web (ida y, si hay, regreso): el trayecto y, debajo, fecha, hora y asientos. -->
<template>
  <ul class="m-0 flex list-none flex-col gap-1.5 p-0 text-sm">
    <li v-for="(v, i) in compra.ventas" :key="v.id">
      <div>
        <span v-if="compra.ventas.length > 1" class="mr-1 text-xs font-semibold uppercase text-muted-color">{{ i === 0 ? 'Ida' : 'Reg.' }}</span>
        <span class="font-medium">{{ v.origen }} → {{ v.destino }}</span>
        <icon v-if="v.facturacion === 'pendiente'" name="warning-outline" class="ml-1 text-orange-500" v-tooltip="'Factura pendiente'" />
      </div>
      <div class="text-xs text-muted-color">{{ fecha(v.salida) }} · {{ hora(v.salida) }} · as. {{ v.asientos }}</div>
    </li>
    <li v-if="!compra.ventas.length" class="text-muted-color">Sin venta registrada</li>
  </ul>
</template>

<script setup lang="ts">
import type { CompraWeb } from '@/core/pagina-web/types'

defineProps<{ compra: CompraWeb }>()
const fecha = (iso: string) => new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(iso))
const hora = (iso: string) => new Intl.DateTimeFormat('es-GT', { hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>
