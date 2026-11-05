<template>
  <!-- Disparador del chat en el teléfono (el escritorio lo tiene en la cabecera). Relleno con el primario para destacar sobre la app. -->
  <button v-if="session.user && route.name !== 'chat' && route.name !== 'correo'" type="button" class="chat-flotante tap-target fixed right-4 bottom-4 z-40 flex size-11 items-center justify-center rounded-full md:hidden" :aria-label="sinLeer ? `Mensajes y correo, ${sinLeer} sin leer` : 'Mensajes y correo'" @click="invocarChat">
    <icon name="forum-outline" size="1.25rem" />
    <span v-if="sinLeer" class="header-chat__badge">{{ sinLeer > 99 ? "99+" : sinLeer }}</span>
  </button>
</template>

<script setup lang="ts">
import { router } from "@/app/router";
import { useSessionStore } from "@/core/auth/session";
import { useChatStore } from "@/core/chat/store";
import { useCorreoStore } from "@/core/correo/store";
import { useVentanaChat } from "@/features/chat/ventana";

const route = useRoute();
const session = useSessionStore();
const chat = useChatStore();
const correo = useCorreoStore();
/** Lo sin leer del chat y del correo (ADR-048): cohabitan en la misma ventana. */
const sinLeer = computed(() => chat.noLeidos + correo.noLeidos);
const ventanaChat = useVentanaChat();

/** Mismo criterio que la cabecera: la ventana si la hay, si no la página del chat. */
function invocarChat() {
  if (ventanaChat.esVentana) return ventanaChat.invocar();
  if (ventanaChat.seccion === "correo") return void router.push({ name: "correo", params: { correo: ventanaChat.correo ?? undefined } });
  void router.push({ name: "chat", params: { canal: ventanaChat.canal ?? undefined } });
}
</script>

<style>
.chat-flotante {
  background: var(--p-surface-0);
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
