<!--
  Chequear un boleto desde el listado (ADR-032): el pasajero se presentó
  (chequeado, o en tránsito si la salida ya inició). Usa el chequeo de la
  salida; lo valida la estación donde sube y la ventana de la salida. Para
  marcar "no se presentó" está Cancelar; para toda la salida, el chequeo por
  salida.
-->
<template>
  <Dialog :visible="visible" modal header="Chequear boleto" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="error = ''">
    <div v-if="boleto" class="flex flex-col gap-3">
      <p class="m-0">
        ¿El pasajero <strong>{{ boleto.clienteBoleto?.nombre ?? 'del boleto' }}</strong> (asiento {{ boleto.asiento }}, {{ boleto.sube }} → {{ boleto.baja }}) se presentó a abordar?
      </p>
      <p class="m-0 text-sm text-muted-color">Queda {{ boleto.salidaEstado === 'iniciada' ? 'en tránsito' : 'chequeado' }}.</p>
      <RouterLink :to="{ name: 'salidas-chequeo', query: { salida: boleto.salidaId } }" class="text-sm">Chequear toda la salida</RouterLink>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Se presentó" :loading="enviando" :disabled="!boleto" @click="chequear"><template #icon><icon name="how-to-reg-outline" class="mr-1" /></template></Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { chequearBoletos } from '@/core/abordaje/api'
import type { BoletoFila } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; boleto: BoletoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [] }>()

const enviando = ref(false)
const error = ref('')

async function chequear() {
  if (!props.boleto) return
  enviando.value = true
  error.value = ''
  try {
    await chequearBoletos(props.boleto.salidaId, { si: [props.boleto.id], no: [] })
    notify.success(`Boleto ${props.boleto.id} chequeado`)
    emit('listo')
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo chequear el boleto.'
  } finally {
    enviando.value = false
  }
}
</script>
