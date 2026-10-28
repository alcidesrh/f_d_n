<!--
  Contenido del chat, el mismo en la página (modo centro) y en la ventana
  (esquina, flotante): bandeja y conversación lado a lado si el propio chat
  mide 40rem o más (contenedor `chat`, no la página); si no, una u otra,
  deslizándose al pasar de una a otra.
-->
<template>
  <div class="vista">
    <div class="vista__rejilla" :class="{ 'vista--abierta': canal }">
      <Bandeja class="vista__bandeja" :activo="canal" @abrir="emit('abrir', $event)" />
      <Conversacion v-if="canal" :key="canal" :canal-id="canal" :adjuntar="adjuntar" :activa="activa" class="vista__conversacion" @volver="emit('abrir', null)" @abrir="emit('abrir', $event)" @adjuntado="emit('adjuntado')" />
      <div v-else class="vista__portada">
        <icon name="forum-outline" size="3rem" color="text-current" />
        <h2>Comunicación interna</h2>
        <p>Converse con la administración y las estaciones, y comparta boletos, salidas o cualquier registro: quien lo recibe lo ve al día y puede abrirlo con un clic.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Referencia } from "@/core/chat/types";
import Bandeja from "./Bandeja.vue";
import Conversacion from "./Conversacion.vue";

withDefaults(defineProps<{ canal: number | null; adjuntar?: Referencia | null; activa?: boolean }>(), { adjuntar: null, activa: true });
const emit = defineEmits<{ abrir: [canal: number | null]; adjuntado: [] }>();
</script>

<style scoped>
.vista {
  container: chat / inline-size;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.vista__rejilla {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-height: 0;
  overflow: hidden;
  background: var(--p-content-background);
}
.vista__conversacion,
.vista__portada {
  display: none;
}
.vista--abierta .vista__bandeja {
  display: none;
}
.vista--abierta .vista__conversacion {
  display: flex;
}
/* Una sola columna (teléfono): la conversación entra desde la derecha y la bandeja, al volver, desde la izquierda. */
@media (prefers-reduced-motion: no-preference) {
  .vista--abierta .vista__conversacion {
    animation: vista-entra-derecha 0.22s ease-out;
  }
  .vista__rejilla:not(.vista--abierta) .vista__bandeja {
    animation: vista-entra-izquierda 0.22s ease-out;
  }
}
@keyframes vista-entra-derecha {
  from {
    transform: translateX(100%);
  }
}
@keyframes vista-entra-izquierda {
  from {
    opacity: 0;
    transform: translateX(-25%);
  }
}
@container chat (min-width: 40rem) {
  .vista--abierta .vista__conversacion,
  .vista__rejilla:not(.vista--abierta) .vista__bandeja {
    animation: none;
  }
  .vista__rejilla {
    grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
  }
  .vista__bandeja,
  .vista--abierta .vista__bandeja {
    display: flex;
    border-right: 1px solid var(--p-surface-200);
  }
  .vista__portada {
    display: flex;
  }
}
.vista__portada {
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
    font-family: "Space Grotesk", sans-serif;
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
