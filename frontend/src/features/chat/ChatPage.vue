<!--
  Chat interno (ADR-026), ruta `/chat/:canal?`. Desde 48rem de contenido:
  bandeja y conversación lado a lado; debajo, una u otra según la ruta.
  `?adjuntar=Tipo:id` deja ese registro listo para enviar.
-->
<template>
  <div class="chat" :class="{ 'chat--abierto': canalId }">
    <Bandeja class="chat__bandeja" :activo="canalId" @abrir="ir" />
    <Conversacion v-if="canalId" :key="canalId" :canal-id="canalId" :adjuntar="adjuntar" class="chat__conversacion" @volver="ir(null)" />
    <div v-else class="chat__portada">
      <icon name="forum-outline" size="3rem" color="text-current" />
      <h2>Comunicación interna</h2>
      <p>Converse con la administración y las estaciones, y comparta boletos, salidas o cualquier registro: quien lo recibe lo ve al día y puede abrirlo con un clic.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Referencia } from '@/core/chat/types'
import Bandeja from './Bandeja.vue'
import Conversacion from './Conversacion.vue'

const route = useRoute()
const router = useRouter()

const canalId = computed(() => {
  const id = Number(route.params.canal)
  return Number.isInteger(id) && id > 0 ? id : null
})
const adjuntar = computed<Referencia | null>(() => {
  const [tipo, id] = String(route.query.adjuntar ?? '').split(':')
  return tipo && Number(id) > 0 ? { tipo, id: Number(id) } : null
})

const ir = (canal: number | null) => void router.push({ name: 'chat', params: { canal: canal ?? undefined } })
</script>

<style scoped>
/* Alto fijo: la bandeja y los mensajes scrollean por dentro, no la página. */
.chat {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  height: calc(100dvh - var(--header-h) - 2rem - max(var(--page-gutter), 3rem));
  min-height: 26rem;
  overflow: hidden;
  border-radius: 1rem;
  background: var(--p-content-background);
  box-shadow: var(--shadow);
}
.chat__conversacion,
.chat__portada {
  display: none;
}
.chat--abierto .chat__bandeja {
  display: none;
}
.chat--abierto .chat__conversacion {
  display: flex;
}
@container main (min-width: 48rem) {
  .chat {
    grid-template-columns: minmax(17rem, 22rem) minmax(0, 1fr);
  }
  .chat__bandeja,
  .chat--abierto .chat__bandeja {
    display: flex;
    border-right: 1px solid var(--p-surface-200);
  }
  .chat__portada {
    display: flex;
  }
}
.chat__portada {
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
  color: var(--p-surface-500);
  background: var(--p-surface-50);
  h2 {
    margin: 0;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.25rem;
    color: var(--p-surface-700);
  }
  p {
    max-width: 26rem;
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.55;
  }
}
</style>
