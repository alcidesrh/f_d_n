<!--
  Chat interno (ADR-026) en modo centro: la ruta `/chat/:canal?`. Maximizado
  pasa a `body` (el contenedor `main` atrapa el `position: fixed`) y ocupa la
  pantalla. La raíz es un elemento, no el Teleport: la transición de rutas
  de `App.vue` (out-in) necesita uno para animar la salida. En los modos
  esquina y flotante la ruta no llega aquí: el router abre la ventana
  (`ChatVentana`). `?adjuntar=Tipo:id` deja ese registro listo para enviar.
  La ruta `/correo/:correo?` es la misma página con el correo (ADR-048).
  En el teléfono va siempre a pantalla completa, como una app de mensajería:
  con una conversación abierta solo se ve su cabecera (su flecha vuelve a la
  bandeja y la de la barra, a la app), y se ajusta al teclado (`altoVisible`).
-->
<template>
  <div class="chat-pagina">
    <Teleport to="body" :disabled="!completa">
      <div class="chat" :class="{ 'chat--maximizada': completa }" :style="alto">
        <BarraChat v-if="!(ventana.movil && (esCorreo ? correoId || ventana.redactando : destino.canal))" />
        <CorreoVista v-if="esCorreo" class="chat__vista" :correo="correoId" @abrir="irCorreo" />
        <ChatVista v-else class="chat__vista" :canal="destino.canal" :adjuntar="destino.adjuntar" @abrir="ir" />
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { useAltoVisible } from "./altoVisible";
import BarraChat from "./BarraChat.vue";
import ChatVista from "./ChatVista.vue";
import CorreoVista from "@/features/correo/CorreoVista.vue";
import { correoDeRuta, destinoDeRuta, useVentanaChat } from "./ventana";

const route = useRoute();
const router = useRouter();
const ventana = useVentanaChat();

const completa = computed(() => ventana.maximizada || ventana.movil);
const alto = useAltoVisible(computed(() => ventana.movil));

const esCorreo = computed(() => route.name === "correo");
const correoId = computed(() => correoDeRuta(route.params));
const destino = computed(() => destinoDeRuta(route.params, route.query));
watch(
  () => [esCorreo.value, destino.value.canal, correoId.value] as const,
  ([correo, canal, id]) => {
    ventana.seccion = correo ? "correo" : "chat";
    if (correo) ventana.correo = id;
    else ventana.canal = canal;
  },
  { immediate: true },
);

function irCorreo(id: number | null) {
  void router.push({ name: "correo", params: { correo: id ?? undefined } });
}

function ir(canal: number | null) {
  // Volver a la bandeja de la que se vino es "atrás": así el botón atrás del teléfono no reabre la conversación.
  if (canal === null && vieneDeLaBandeja()) return router.back();
  void router.push({ name: "chat", params: { canal: canal ?? undefined } });
}

function vieneDeLaBandeja(): boolean {
  const atras: unknown = window.history.state?.back;
  if (typeof atras !== "string") return false;
  const anterior = router.resolve(atras);
  return anterior.name === "chat" && destinoDeRuta(anterior.params, anterior.query).canal === null;
}
</script>

<style scoped>
/* Alto fijo: la bandeja y los mensajes scrollean por dentro, no la página. */
.chat {
  display: flex;
  flex-direction: column;
  height: calc(100dvh - var(--header-h) - var(--page-top) - var(--page-bottom));
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
  min-height: 0;
  padding-top: env(safe-area-inset-top);
  border-radius: 0;
}
.chat__vista {
  flex: 1;
  min-height: 0;
}
</style>
