<template>
  <!-- Disparador del chat en el teléfono (el escritorio lo tiene en la cabecera). Discreto: no tapa la app. -->
  <button
    v-if="session.user && route.name !== 'chat'"
    type="button"
    class="tap-target fixed right-4 bottom-4 z-40 flex size-11 items-center justify-center rounded-full border border-surface-200 bg-surface-0 text-surface-600 opacity-80 shadow-md md:hidden"
    :aria-label="chat.noLeidos ? `Mensajes, ${chat.noLeidos} sin leer` : 'Mensajes'"
    @click="invocarChat"
  >
    <icon name="forum-outline" size="1.25rem" />
    <span v-if="chat.noLeidos" class="header-chat__badge">{{
      chat.noLeidos > 99 ? '99+' : chat.noLeidos
    }}</span>
  </button>
</template>

<script setup lang="ts">
import { router } from '@/app/router'
import { useSessionStore } from '@/core/auth/session'
import { useChatStore } from '@/core/chat/store'
import { useVentanaChat } from '@/features/chat/ventana'

const route = useRoute()
const session = useSessionStore()
const chat = useChatStore()
const ventanaChat = useVentanaChat()

/** Mismo criterio que la cabecera: la ventana si la hay, si no la página del chat. */
function invocarChat() {
  if (ventanaChat.esVentana) return ventanaChat.invocar()
  void router.push({ name: 'chat', params: { canal: ventanaChat.canal ?? undefined } })
}
</script>
