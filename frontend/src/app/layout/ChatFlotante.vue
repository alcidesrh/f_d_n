<template>
  <!-- Disparador del chat en el teléfono (el escritorio lo tiene en la cabecera). Relleno con el primario para destacar sobre la app. -->
  <button v-if="session.user && route.name !== 'chat'" type="button" class="chat-flotante tap-target fixed right-4 bottom-4 z-40 flex size-11 items-center justify-center rounded-full md:hidden" :aria-label="chat.noLeidos ? `Mensajes, ${chat.noLeidos} sin leer` : 'Mensajes'" @click="invocarChat">
    <icon name="forum-outline" size="1.25rem" />
    <span v-if="chat.noLeidos" class="header-chat__badge">{{ chat.noLeidos > 99 ? "99+" : chat.noLeidos }}</span>
  </button>
</template>

<script setup lang="ts">
import { router } from "@/app/router";
import { useSessionStore } from "@/core/auth/session";
import { useChatStore } from "@/core/chat/store";
import { useVentanaChat } from "@/features/chat/ventana";

const route = useRoute();
const session = useSessionStore();
const chat = useChatStore();
const ventanaChat = useVentanaChat();

/** Mismo criterio que la cabecera: la ventana si la hay, si no la página del chat. */
function invocarChat() {
  if (ventanaChat.esVentana) return ventanaChat.invocar();
  void router.push({ name: "chat", params: { canal: ventanaChat.canal ?? undefined } });
}
</script>

<style>
.chat-flotante {
  background: var(--p-surface-0);
  /*border: 1px solid var(--p-surface-300);*/
  color: var(--p-primary-contrast-color);
  box-shadow: 1px 1px 7px 1px var(--p-surface-500);
}

/* Sobre la barra fija de abajo de una pantalla (p. ej. la venta), no encima de sus botones. */
@media (width < 48rem) {
  body:has(.barra-movil) .chat-flotante {
    bottom: calc(5rem + env(safe-area-inset-bottom));
  }
}
</style>
