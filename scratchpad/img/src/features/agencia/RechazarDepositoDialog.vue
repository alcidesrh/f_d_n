<!--
  Rechazar un depósito registrado: el motivo es obligatorio (como el legado)
  y le llega a la agencia por el chat. El saldo no cambia.
-->
<template>
  <Dialog :visible="visible" modal header="Rechazar depósito" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <div v-if="deposito" class="flex flex-col gap-3">
      <div class="deposito">
        <div class="flex items-baseline justify-between gap-2">
          <span class="font-semibold">Boleta {{ deposito.numeroBoleta }} · {{ deposito.agencia.nombre }}</span>
          <span class="tabular-nums">{{ deposito.importe.texto }}</span>
        </div>
        <div class="text-xs text-muted-color">Registrado por {{ deposito.creadoPor ?? '—' }}</div>
      </div>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Motivo del rechazo</span>
        <Textarea v-model="motivo" rows="2" auto-resize maxlength="200" placeholder="P. ej. la boleta no aparece en el estado de cuenta" :disabled="enviando" :invalid="intentado && !motivo.trim()" fluid />
      </label>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Rechazar" severity="danger" :loading="enviando" :disabled="!deposito" @click="rechazar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { rechazarDeposito } from '@/core/agencia/api'
import type { DepositoFila } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; deposito: DepositoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [deposito: DepositoFila] }>()

const motivo = ref('')
const enviando = ref(false)
const intentado = ref(false)
const error = ref('')

function abrir() {
  motivo.value = ''
  intentado.value = false
  error.value = ''
}

async function rechazar() {
  if (!props.deposito) return
  intentado.value = true
  if (!motivo.value.trim()) {
    error.value = 'Indique el motivo del rechazo.'
    return
  }
  enviando.value = true
  error.value = ''
  try {
    const d = await rechazarDeposito(props.deposito.id, motivo.value.trim())
    notify.success(`Depósito ${d.numeroBoleta} rechazado`)
    emit('listo', d)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo rechazar el depósito.'
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.deposito {
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-border-radius-md);
  padding: 0.5rem 0.75rem;
}
</style>
