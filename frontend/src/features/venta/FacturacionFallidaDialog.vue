<!--
  El certificador no emitió la factura y la venta NO se registró. Opciones:
  cancelar, reintentar o (con permiso y si el fallo es de comunicación)
  reintentar sin factura electrónica: la venta se registra y la factura
  queda pendiente de certificar.
-->
<template>
  <Dialog
    :visible="!!fallo"
    modal
    header="No se pudo emitir la factura"
    :style="{ width: '32rem' }"
    :closable="!ocupado"
    @update:visible="!$event && emit('cancelar')"
  >
    <div class="flex flex-col gap-3">
      <Message severity="error" :closable="false">{{ fallo?.error }}</Message>
      <p class="text-sm text-muted-color">
        La venta no quedó registrada y los asientos siguen libres.
        <template v-if="fallo?.recuperable">Puede intentarlo de nuevo.</template>
        <template v-else>Revise los datos del cliente (NIT) antes de reintentar.</template>
      </p>
    </div>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <Button
          label="Cancelar"
          severity="secondary"
          text
          :disabled="ocupado"
          @click="emit('cancelar')"
        />
        <Button
          v-if="fallo?.permiteSinFactura"
          label="Reintentar sin factura electrónica"
          severity="warn"
          outlined
          :loading="ocupado"
          @click="emit('reintentar', true)"
        />
        <Button label="Reintentar" :loading="ocupado" @click="emit('reintentar', false)" />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import type { ErrorVenta } from '@/core/venta/types'

defineProps<{ fallo: ErrorVenta | null; ocupado: boolean }>()
const emit = defineEmits<{ cancelar: []; reintentar: [sinFactura: boolean] }>()
</script>
