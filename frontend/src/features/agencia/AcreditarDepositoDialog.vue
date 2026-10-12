<!--
  Acreditar un depósito registrado (como el legado: confirma a qué agencia,
  con cuánto de saldo y cuánto de bono). Suma ambos al saldo de la agencia y
  le avisa por el chat.
-->
<template>
  <Dialog :visible="visible" modal header="Acreditar depósito" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="error = ''">
    <div v-if="deposito" class="flex flex-col gap-3">
      <p>¿Acreditar a <strong>{{ deposito.agencia.nombre }}</strong> el depósito de la boleta <strong>{{ deposito.numeroBoleta }}</strong> ({{ fechaCorta(deposito.fecha) }})?</p>
      <dl class="resumen">
        <dt>Saldo principal</dt>
        <dd>{{ deposito.importe.texto }}</dd>
        <dt>Bono</dt>
        <dd>{{ deposito.aplicaBono ? deposito.bono.texto : 'No aplica' }}</dd>
        <dt class="font-semibold">Total a acreditar</dt>
        <dd class="font-semibold">{{ quetzales(deposito.importe.centavos + (deposito.aplicaBono ? deposito.bono.centavos : 0), deposito.importe.moneda) }}</dd>
      </dl>
      <p v-if="deposito.observacion" class="text-sm text-muted-color">{{ deposito.observacion }}</p>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Acreditar" severity="success" :loading="enviando" :disabled="!deposito" @click="acreditar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { acreditarDeposito } from '@/core/agencia/api'
import { quetzales } from '@/core/agencia/depositos'
import type { DepositoFila } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; deposito: DepositoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [deposito: DepositoFila] }>()

const enviando = ref(false)
const error = ref('')
const fechaCorta = (dia: string) => dia.split('-').reverse().join('/')

async function acreditar() {
  if (!props.deposito) return
  enviando.value = true
  error.value = ''
  try {
    const d = await acreditarDeposito(props.deposito.id)
    notify.success(`Depósito ${d.numeroBoleta} acreditado a ${d.agencia.nombre}`)
    emit('listo', d)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo acreditar el depósito.'
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.resumen {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.25rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-border-radius-md);
  padding: 0.5rem 0.75rem;
}
.resumen dd {
  text-align: end;
  font-variant-numeric: tabular-nums;
}
</style>
