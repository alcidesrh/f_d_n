<!--
  Revertir un boleto cancelado (el pasajero sí viaja, el "Revertir" del
  legado): vuelve a emitido y ocupa otra vez su asiento. El backend rechaza si
  el asiento se volvió a vender o está apartado en ese tramo.
-->
<template>
  <Dialog :visible="visible" modal header="Revertir cancelación" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="error = ''">
    <div v-if="boleto" class="flex flex-col gap-3">
      <div class="boleto">
        <div class="flex items-baseline justify-between gap-2">
          <span class="font-semibold">Boleto {{ boleto.id }} · asiento {{ boleto.asiento }}</span>
          <span class="tabular-nums">{{ boleto.precio?.texto ?? '—' }}</span>
        </div>
        <div class="text-xs text-muted-color">{{ boleto.sube }} → {{ boleto.baja }} · {{ fecha(boleto.fecha) }} {{ hora(boleto.fecha) }} · {{ boleto.clienteBoleto?.nombre ?? 'sin pasajero' }}</div>
      </div>
      <Message severity="info" :closable="false">El boleto vuelve a quedar emitido y ocupa otra vez el asiento {{ boleto.asiento }}, si nadie lo compró o apartó mientras estuvo libre. Luego se chequea como cualquier otro.</Message>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Revertir" :loading="enviando" :disabled="!boleto" @click="revertir" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { revertirBoleto } from '@/core/boleto/api'
import type { BoletoFila } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import { fecha, hora } from '@/core/venta/modelo'

const props = defineProps<{ visible: boolean; boleto: BoletoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [] }>()

const enviando = ref(false)
const error = ref('')

async function revertir() {
  if (!props.boleto) return
  enviando.value = true
  error.value = ''
  try {
    await revertirBoleto(props.boleto.id)
    notify.success(`Boleto ${props.boleto.id} emitido otra vez`)
    emit('listo')
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo revertir el boleto.'
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
