<!--
  Desafío 3-D Secure: la página del banco (ACS) dentro de un iframe. Al
  terminar, el banco vuelve a /api/publico/pagos/retorno (mismo origen), que
  avisa por postMessage con lo que envió el banco.
-->
<template>
  <Dialog
    :visible="true"
    modal
    :closable="true"
    :draggable="false"
    :header="t('pago.bancoTitulo')"
    class="w-[calc(100vw-1rem)] max-w-[40rem]"
    :pt="{ content: { class: '!p-0 sm:!px-4 sm:!pb-4' } }"
    @update:visible="(v: boolean) => !v && emit('cancelado')"
  >
    <p class="m-0 mb-2 px-4 text-sm text-muted-color sm:px-0">{{ t('pago.bancoTexto') }}</p>
    <div class="flex justify-center overflow-auto">
      <iframe
        :name="nombre"
        :title="t('pago.bancoMarco')"
        class="block max-w-full border-0 bg-white"
        :style="{ width: ancho, height: alto === '100%' ? '70vh' : alto }"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { esRetorno3ds } from '@/modelo'
import { enviarFormulario } from '@/tresDs'

const props = defineProps<{ url: string; campos: Record<string, string>; ancho: string; alto: string }>()
const emit = defineEmits<{ completado: [datos: Record<string, string>]; cancelado: [] }>()
const { t } = useI18n()

const nombre = `acs-${Date.now()}`

function alRecibir(e: MessageEvent) {
  if (e.origin === window.location.origin && esRetorno3ds(e.data)) emit('completado', e.data.datos)
}

onMounted(async () => {
  window.addEventListener('message', alRecibir)
  await nextTick()
  enviarFormulario(props.url, props.campos, nombre)
})
onBeforeUnmount(() => window.removeEventListener('message', alRecibir))
</script>
