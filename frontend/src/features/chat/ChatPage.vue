<!--
  Chat interno (ADR-026) en modo centro: la ruta `/chat/:canal?`. Maximizado
  pasa a `body` (el contenedor `main` atrapa el `position: fixed`) y ocupa la
  pantalla. La raíz es un elemento, no el Teleport: la transición de rutas
  de `App.vue` (out-in) necesita uno para animar la salida. En los modos
  esquina y flotante la ruta no llega aquí: el router abre la ventana
  (`ChatVentana`). `?adjuntar=Tipo:id` deja ese registro listo para enviar.
-->
<template>
  <div class="chat-pagina">
    <Teleport to="body" :disabled="!ventana.maximizada">
      <div class="chat" :class="{ 'chat--maximizada': ventana.maximizada }">
        <BarraChat />
        <ChatVista class="chat__vista" :canal="destino.canal" :adjuntar="destino.adjuntar" @abrir="ir" />
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import BarraChat from './BarraChat.vue'
import ChatVista from './ChatVista.vue'
import { destinoDeRuta, useVentanaChat } from './ventana'

const route = useRoute()
const router = useRouter()
const ventana = useVentanaChat()

const destino = computed(() => destinoDeRuta(route.params, route.query))
watch(
  () => destino.value.canal,
  (canal) => (ventana.canal = canal),
  { immediate: true },
)

const ir = (canal: number | null) => void router.push({ name: 'chat', params: { canal: canal ?? undefined } })
</script>

<style scoped>
/* Alto fijo: la bandeja y los mensajes scrollean por dentro, no la página. */
.chat {
  display: flex;
  flex-direction: column;
  height: calc(100dvh - var(--header-h) - 2rem - max(var(--page-gutter), 3rem));
  min-height: 26rem;
  overflow: hidden;
  border-radius: 1rem;
  background: var(--p-content-background);
  box-shadow: var(--shadow);
}
.chat--maximizada {
  position: fixed;
  inset: 0;
  z-index: 990;
  height: auto;
  border-radius: 0;
}
.chat__vista {
  flex: 1;
  min-height: 0;
}
</style>
