<!--
  "Correo" del listado de boletos: manda el boleto en PDF (con los datos de
  la factura; todos los boletos de la venta) a la dirección que se escriba,
  por defecto la del pasajero o la del cliente del documento.
-->
<template>
  <Dialog :visible="visible" modal header="Enviar boleto por correo" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <form v-if="boleto" class="flex flex-col gap-3" @submit.prevent="enviar">
      <p class="m-0 text-sm text-muted-color">Se envía el PDF de la venta {{ boleto.ventaId }} (boletos y datos de la factura).</p>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Correo</span>
        <InputText v-model="correo" type="email" inputmode="email" autocomplete="email" required placeholder="cliente@correo.com" :disabled="enviando" fluid />
      </label>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <button type="submit" hidden />
    </form>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Enviar" :loading="enviando" :disabled="!boleto || !correo.trim()" @click="enviar"><template #icon><icon name="send-outline" class="mr-1" /></template></Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { enviarBoletoPorCorreo } from '@/core/boleto/api'
import type { BoletoFila } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; boleto: BoletoFila | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

const correo = ref('')
const enviando = ref(false)
const error = ref('')

function abrir() {
  correo.value = props.boleto?.clienteBoleto?.email || props.boleto?.clienteDocumento?.email || ''
  error.value = ''
}

async function enviar() {
  if (!props.boleto || !correo.value.trim()) return
  enviando.value = true
  error.value = ''
  try {
    const r = await enviarBoletoPorCorreo(props.boleto.id, correo.value.trim())
    notify.success(`El boleto se enviará a ${r.correo}`)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo enviar el correo.'
  } finally {
    enviando.value = false
  }
}
</script>
