<!--
  Barra de título del chat: a la izquierda el título, en vivo y sin leer; al
  centro el modo (ícono y nombre); a la derecha minimizar, maximizar y
  cerrar (en modo centro solo maximizar). En la flotante es además el asa para arrastrarla. Cambiar de/a
  centro navega: el centro es la página `/chat`.
-->
<template>
  <header class="barra" :class="{ 'barra--minimizada': ventana.minimizada && ventana.esVentana }" @click="alTocar">
    <span class="barra__titulo">
      <icon name="forum-outline" size="1.15rem" color="text-current" />
      <span>Mensajes</span>
      <span class="vivo" :class="{ 'vivo--off': !chat.enVivo }" v-tooltip.bottom="chat.enVivo ? 'En vivo' : 'Sin conexión en vivo: se actualiza cada 30 s'" />
      <span v-if="chat.noLeidos" class="barra__badge" :aria-label="`${chat.noLeidos} sin leer`">{{ chat.noLeidos > 99 ? "99+" : chat.noLeidos }}</span>
    </span>

    <button v-if="!(ventana.esVentana && ventana.minimizada)" type="button" class="modo" aria-haspopup="true" aria-controls="chat-modos" :aria-label="`Modo: ${MODOS[ventana.modo].nombre}`" v-tooltip.bottom="'Modo de la ventana'" @click="menu?.toggle($event)">
      <icon :name="MODOS[ventana.modo].icono" size="1.05rem" color="text-current" />
      <span class="modo__nombre">{{ MODOS[ventana.modo].nombre }}</span>
      <icon name="keyboard-arrow-down" size="1rem" color="text-current" class="modo__flecha" />
    </button>

    <span class="barra__controles">
      <template v-if="ventana.esVentana && ventana.minimizada">
        <button type="button" class="control" aria-label="Restaurar" v-tooltip.bottom="'Restaurar'" @click="ventana.restaurar()"><icon name="keyboard-arrow-up" size="1.2rem" color="text-current" /></button>
      </template>
      <template v-else>
        <button v-if="ventana.esVentana" type="button" class="control" aria-label="Minimizar" v-tooltip.bottom="'Minimizar'" @click="ventana.minimizar()"><icon name="minimize" size="1.1rem" color="text-current" /></button>
        <button type="button" class="control" :aria-label="ventana.maximizada ? 'Restaurar tamaño' : 'Maximizar'" v-tooltip.bottom="ventana.maximizada ? 'Restaurar tamaño' : 'Pantalla completa'" @click="ventana.alternarMaximizada()">
          <icon :name="ventana.maximizada ? 'close-fullscreen' : 'open-in-full'" size="1rem" color="text-current" />
        </button>
      </template>
      <button v-if="ventana.esVentana" type="button" class="control" aria-label="Cerrar" v-tooltip.bottom="'Cerrar'" @click="ventana.cerrar()"><icon name="close" size="1.15rem" color="text-current" /></button>
    </span>

    <Menu id="chat-modos" ref="menu" :model="opciones" popup>
      <template #start><span class="block px-3 pt-2 pb-1 text-xs font-semibold text-muted-color">Mostrar el chat</span></template>
      <template #item="{ item, props: p }">
        <a v-bind="p.action" class="flex items-center gap-2.5">
          <icon :name="String(item.icono)" size="1.15rem" />
          <span class="flex-1">
            <span class="block text-sm">{{ item.label }}</span>
            <span class="block text-xs text-muted-color">{{ item.detalle }}</span>
          </span>
          <icon v-if="item.modo === ventana.modo" name="check" size="1rem" class="text-primary" />
        </a>
      </template>
    </Menu>
  </header>
</template>

<script setup lang="ts">
import type { MenuItem } from "primevue/menuitem";
import type Menu from "primevue/menu";
import { useChatStore } from "@/core/chat/store";
import type { ModoVentana } from "@/core/chat/ventana";
import { useVentanaChat } from "./ventana";

/** La flotante restaura con el clic de Draggable (distingue clic de arrastre). */
const props = defineProps<{ arrastrable?: boolean }>();

const MODOS: Record<ModoVentana, { nombre: string; icono: string; detalle: string }> = {
  centro: { nombre: "Centro", icono: "web-asset", detalle: "Como página, en el centro" },
  esquina: { nombre: "Esquina", icono: "picture-in-picture-alt", detalle: "Abajo a la derecha, sobre lo que haga" },
  flotante: { nombre: "Flotante", icono: "select-window", detalle: "Donde la deje y del tamaño que quiera" },
};

const chat = useChatStore();
const ventana = useVentanaChat();
const router = useRouter();
const menu = ref<InstanceType<typeof Menu> | null>(null);

const opciones = computed<MenuItem[]>(() => (Object.keys(MODOS) as ModoVentana[]).map((modo) => ({ label: MODOS[modo].nombre, detalle: MODOS[modo].detalle, icono: MODOS[modo].icono, modo, command: () => elegir(modo) })));

function elegir(modo: ModoVentana) {
  const antes = ventana.modo;
  ventana.cambiarModo(modo);
  if (modo === "centro") void router.push({ name: "chat", params: { canal: ventana.canal ?? undefined } });
  else if (antes === "centro") void router.push(ventana.pagina ?? "/");
}

/** Minimizada, tocar la barra (fuera de los botones) la restaura. */
function alTocar(e: MouseEvent) {
  if (props.arrastrable || !ventana.minimizada || (e.target as HTMLElement).closest("button")) return;
  ventana.restaurar();
}
</script>

<style scoped>
.barra {
  container: barra / inline-size;
  flex: none;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 0.5rem;
  height: 44px;
  padding: 0 0.35rem 0 0.9rem;
  border-bottom: 1px solid var(--p-surface-200);
  color: var(--p-surface-700);
  background: var(--p-surface-200);
  user-select: none;
}
.barra--minimizada {
  cursor: pointer;
  border-bottom: 0;
}
.barra__titulo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  font-family: "Space Grotesk", sans-serif;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--p-surface-800);
}
.vivo {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: var(--c-success);
  box-shadow: 0 0 0 3px var(--c-success-soft);
}
.vivo--off {
  background: var(--p-surface-400);
  box-shadow: 0 0 0 3px var(--p-surface-200);
}
.barra__badge {
  min-width: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  font-family: inherit;
  font-size: 0.7rem;
  line-height: 1.25rem;
  text-align: center;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}
.barra__controles {
  grid-column: 3;
  justify-self: end;
  display: flex;
  align-items: center;
}
.modo {
  grid-column: 2;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  height: 1.9rem;
  padding: 0 0.4rem 0 0.6rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--p-surface-600);
  background: var(--p-surface-100);
  cursor: pointer;
  &:hover {
    color: var(--p-primary-color);
    background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
  }
}
.modo__flecha {
  opacity: 0.6;
}
/* Barra estrecha: solo el ícono del modo. */
@container barra (max-width: 24rem) {
  .modo {
    padding: 0 0.45rem;
  }
  .modo__nombre,
  .modo__flecha {
    display: none;
  }
}
.control {
  display: grid;
  place-items: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.55rem;
  color: var(--p-surface-500);
  cursor: pointer;
  &:hover {
    color: var(--p-surface-800);
    background: var(--p-surface-100);
  }
}
@media (pointer: coarse) {
  .control {
    width: 2.6rem;
    height: 2.6rem;
  }
}
</style>
