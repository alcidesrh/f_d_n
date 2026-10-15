<!-- Reenviar el boleto de una compra web (todos sus viajes en un correo), por defecto al comprador. -->
<template>
  <Dialog :visible="visible" modal header="Reenviar boleto" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <form v-if="compra" class="flex flex-col gap-3" @submit.prevent="enviar">
      <p class="m-0 text-sm text-muted-color">Se envía el PDF con los boletos y los datos de la factura de {{ compra.ventas.length > 1 ? 'la ida y el regreso' : 'la compra' }}.</p>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Correo</span>
        <InputText v-model="correo" type="email" inputmode="email" autocomplete="email" required placeholder="cliente@correo.com" :disabled="enviando" fluid />
      </label>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <button type="submit" hidden />
    </form>
    <template #footer>
      <Button label="Volver" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Enviar" :loading="enviando" :disabled="!compra || !correo.trim()" @click="enviar"><template #icon><icon name="send-outline" class="mr-1" /></template></Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { reenviarBoleto } from '@/core/pagina-web/api'
import type { CompraWeb } from '@/core/pagina-web/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'

const props = defineProps<{ visible: boolean; compra: CompraWeb | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean] }>()

const correo = ref('')
const enviando = ref(false)
const error = ref('')

function abrir() {
  correo.value = props.compra?.comprador.email ?? ''
  error.value = ''
}

async function enviar() {
  if (!props.compra || !correo.value.trim()) return
  enviando.value = true
  error.value = ''
  try {
    const r = await reenviarBoleto(props.compra.id, correo.value.trim())
    notify.success(`El boleto se enviará a ${r.resultado}`)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo enviar el correo.'
  } finally {
    enviando.value = false
  }
}
</script>
