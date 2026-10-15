<!--
  Posponer una compra web: los boletos emitidos que todavía no salen liberan
  su asiento; la venta, la factura y el importe siguen igual. Se reanuda con
  «Reasignar» (a otra salida de la misma empresa).
-->
<template>
  <Dialog :visible="visible" modal :header="compra ? `Posponer compra #${compra.id}` : ''" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <div v-if="compra" class="flex flex-col gap-3">
      <ViajesCompra :compra="compra" />
      <Message severity="warn" :closable="false">Los asientos quedan libres para la venta. La venta, la factura y el importe no cambian. Para reanudar el viaje, reasígnelo a otra salida de la misma empresa.</Message>
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">Motivo</span>
        <Textarea v-model="motivo" rows="2" auto-resize maxlength="200" placeholder="P. ej. el cliente cambiará la fecha más adelante" :disabled="enviando" fluid />
      </label>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Posponer" severity="warn" :loading="enviando" :disabled="!motivo.trim()" @click="posponer" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { posponerCompra } from '@/core/pagina-web/api'
import type { CompraWeb } from '@/core/pagina-web/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import ViajesCompra from './ViajesCompra.vue'

const props = defineProps<{ visible: boolean; compra: CompraWeb | null }>()
const emit = defineEmits<{ 'update:visible': [visible: boolean]; listo: [compra: CompraWeb | null] }>()

const motivo = ref('')
const enviando = ref(false)
const error = ref('')

function abrir() {
  motivo.value = ''
  error.value = ''
}

async function posponer() {
  if (!props.compra || !motivo.value.trim()) return
  enviando.value = true
  error.value = ''
  try {
    const r = await posponerCompra(props.compra.id, motivo.value.trim())
    notify.success(`${r.resultado.length} boleto(s) pospuesto(s): sus asientos quedaron libres`)
    emit('listo', r.compra)
    emit('update:visible', false)
  } catch (e) {
    error.value = e instanceof HttpError ? e.message : 'No se pudo posponer la compra.'
  } finally {
    enviando.value = false
  }
}
</script>
