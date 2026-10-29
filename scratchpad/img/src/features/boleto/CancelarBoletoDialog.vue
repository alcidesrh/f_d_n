<!--
  Cancelar un boleto desde el listado (el pasajero no viaja, el "Cancelar"
  del legado): el asiento queda libre, pero la venta, su factura y su importe
  se conservan; no es una anulación. Motivo opcional, va a la bitácora.
-->
<template>
  <Dialog :visible="visible" modal header="Cancelar boleto" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <div v-if="boleto" class="flex flex-col gap-3">
      <div class="boleto">
        <div class="flex items-baseline justify-between gap-2">
          <span class="font-semibold">Boleto {{ boleto.id }} · asiento {{ boleto.asiento }}</span>
          <span class="tabular-nums">{{ boleto.precio?.texto ?? '—' }}</span>
        </div>
        <div class="text-xs text-muted-color">{{ boleto.sube }} → {{ boleto.baja }} · {{ fecha(boleto.fecha) }} {{ hora(boleto.fecha) }} · {{ boleto.clienteBoleto?.nombre ?? 'sin pasajero' }}</div>
      </div>
      <Message severity="info" :closable="false">El asiento queda libre para venderse otra vez. La venta, su factura y su importe se conservan: no es una anulación y no se devuelve dinero.</Message>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Motivo <span class="font-normal text-muted-color">(opcional)</span></span>
        <Textarea v-model="motivo" rows="2" auto-resize maxlength="200" placeholder="P. ej. el pasajero avisó que no viaja" :disabled="enviando" fluid />
      </label>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Cancelar boleto" severity="warn" :loading="enviando" :disabled="!boleto" @click="cancelar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { cancelarBoleto } from '@/core/boleto/api'
import type { BoletoFila } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import { fecha, hora } from '@/core/venta/modelo'

const props = defineProps<{ visible: boolean; boleto: BoletoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [] }>()

const motivo = ref('')
const enviando = ref(false)
const error = ref('')

function abrir() {
  motivo.value = ''
  error.value = ''
}

async function cancelar() {
  if (!props.boleto) return
  enviando.value = true
  error.value = ''
  try {
    await cancelarBoleto(props.boleto.id, motivo.value.trim())
    notify.success(`Boleto ${props.boleto.id} cancelado`)
    emit('listo')
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo cancelar el boleto.'
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.boleto {
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-border-radius-md);
  padding: 0.5rem 0.75rem;
}
</style>
