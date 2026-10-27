<template>
  <header class="app-header">
    <div class="left-header">
      <div class="flex btn-siderbar-header">
        <button type="button" class="icon-btn" aria-label="Menú de navegación" :aria-expanded="ui.isMobile ? sidebarStore.drawer : sidebarStore.mode !== 'close'" @click="sidebarStore.toggle()">
          <!-- <icon name="menu" size="35px" :weight="300" color="text-surface-500" /> -->
          <icon name="right_panel_close" size="30" color="text-surface-500" />
        </button>
        <Divider layout="vertical" class="mx-[5px]!" />
      </div>
      <div class="brand">
        <!-- <div style="min-width: 0; position: relative"> -->
        <div class="header-clock" v-html="currentTime"></div>
        <div class="brand-name">FDN</div>
        <!-- </div> -->
      </div>
    </div>

    <div class="header-crumbs">
      <nav class="crumbs" aria-label="Breadcrumb">
        <!-- <icon v-if="i > 0" name="chevron-right" size=".8rem"></icon> -->
        <router-link :to="'/'" class="crumb-link"> Inicio </router-link>
        <icon name="chevron-right" size=".8rem"></icon>
        <template v-for="(crumb, i) in navigationHistory.entries" :key="crumb.path + i">
          <icon v-if="i > 0" name="chevron-right" size=".8rem"></icon>
          <icon v-if="crumb.icon" :name="crumb.icon" size=".8rem"></icon>
          <router-link
            :to="crumb.name ? { name: crumb.name, params: crumb.params } : crumb.path"
            class="crumb-link"
            :class="{
              'crumb-current': i === navigationHistory.entries.length - 1,
            }"
          >
            {{ crumb.label }}
          </router-link>
        </template>
      </nav>
    </div>

    <div class="right-header">
      <div class="header-actions">
        <slot name="menu-content"></slot>

        <!-- <button class="icon-btn header-secundario cursor-pointer" :title="ui.mode === 'dark' ? 'Modo claro' : 'Modo oscuro'" :aria-label="ui.mode === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'" @click="ui.toggleMode()">
          <icon :name="ui.mode === 'dark' ? 'light-mode-outline' : 'dark-mode-outline'" />
        </button> -->

        <button class="icon-btn header-secundario cursor-pointer" title="Personalizar apariencia" aria-label="Personalizar apariencia" aria-haspopup="dialog" :class="{ 'active-state': temaAbierto }" @click="temaPopover?.toggle($event)">
          <icon name="palette-outline" />
        </button>
        <Popover ref="temaPopover" @show="temaAbierto = true" @hide="temaAbierto = false">
          <ThemeEditor @cerrar="temaPopover?.hide()" />
        </Popover>

        <button type="button" class="icon-btn header-chat" :aria-label="chat.noLeidos ? `Mensajes, ${chat.noLeidos} sin leer` : 'Mensajes'" v-tooltip.bottom="'Mensajes'" @click="invocarChat">
          <icon name="forum-outline" />
          <span v-if="chat.noLeidos" class="header-chat__badge">{{ chat.noLeidos > 99 ? "99+" : chat.noLeidos }}</span>
        </button>
        <!-- Ayuda: abre las guías de la pantalla actual (o todas si no hay). -->
        <RouterLink :to="{ name: 'ayuda', query: ayudaPantalla ? { pantalla: ayudaPantalla } : {} }" class="icon-btn header-secundario" aria-label="Ayuda" v-tooltip.bottom="'Ayuda'">
          <icon name="help-outline" />
        </RouterLink>
        <!-- Autorizaciones (ADR-040): las que espera resolver y sus pases aprobados sin usar. -->
        <RouterLink :to="{ name: 'autorizaciones', query: autorizacionesPendientes && autorizaciones.resumen?.porResolver ? { vista: 'por-resolver' } : {} }" class="icon-btn header-chat" :aria-label="autorizacionesPendientes ? `Autorizaciones, ${autorizacionesPendientes} por atender` : 'Autorizaciones'" v-tooltip.bottom="'Autorizaciones'">
          <icon name="notifications-outline" />
          <span v-if="autorizacionesPendientes" class="header-chat__badge">{{ autorizacionesPendientes > 99 ? "99+" : autorizacionesPendientes }}</span>
        </RouterLink>
        <button class="icon-btn header-fullscreen" title="Pantalla completa" aria-label="Pantalla completa" @click="toggleFullscreen" :class="{ 'active-state': openPopover === 'fullscreen' }">
          <icon name="fullscreen" />
        </button>
      </div>
      <!-- Se corre con las acciones cuando se cierra el sidebar derecho (ver header.css). -->
      <div class="header-sinc">
        <IndicadorSincronizacion />
      </div>
      <!-- Estación de trabajo (ADR-039): donde opera; se cambia entre las de sus asignaciones. -->
      <button v-if="acceso.estacionTrabajo || acceso.debeElegirEstacion" type="button" class="icon-btn header-estacion" :class="{ 'header-estacion--falta': !acceso.estacionTrabajo }" :aria-label="acceso.estacionTrabajo ? `Estación de trabajo: ${acceso.estacionTrabajo.nombre}. Cambiar` : 'Elegir estación de trabajo'" v-tooltip.bottom="acceso.estaciones.length > 1 ? 'Estación de trabajo' : undefined" :disabled="acceso.estaciones.length < 2 && !!acceso.estacionTrabajo" @click="acceso.eligiendoEstacion = true">
        <icon name="location_home-outline" :size="ui.isMobile ? undefined : '1.05rem'" />
        <span class="header-estacion__nombre">{{ acceso.estacionTrabajo?.nombre ?? "Elegir estación" }}</span>
      </button>
      <!-- Fuera de la franja que scrollea: Mi cuenta, Instalar app y Cerrar sesión siempre a la vista. -->
      <div class="header-user-wrap">
        <button type="button" class="header-user" :title="session.user ?? ''" aria-haspopup="true" aria-controls="menu-usuario" @click="menuUsuario?.toggle($event)">
          <ChatAvatar :id="cuenta.cuenta?.id ?? 0" :nombre="cuenta.nombre" :foto="cuenta.foto" tamano="sm" />
          <span class="header-user__name">{{ session.user }}</span>
          <icon name="keyboard-arrow-down" size=".9rem" />
        </button>
        <Menu id="menu-usuario" ref="menuUsuario" :model="opcionesUsuario" popup>
          <template #start>
            <div class="header-user__card">
              <ChatAvatar :id="cuenta.cuenta?.id ?? 0" :nombre="cuenta.nombre" :foto="cuenta.foto" tamano="lg" />
              <div class="min-w-0">
                <div class="truncate font-semibold">{{ cuenta.nombre }}</div>
                <div class="truncate text-xs text-muted-color">@{{ session.user }}</div>
              </div>
            </div>
          </template>
          <template #item="{ item, props }">
            <a v-bind="props.action" class="flex items-center gap-2">
              <icon :name="String(item.iconName)" size="1.1rem" />
              <span>{{ item.label }}</span>
            </a>
          </template>
        </Menu>
      </div>
      <div v-if="rightSidebar" class="flex btn-siderbar-header">
        <Divider layout="vertical" class="mx-[5px]!" />
        <button type="button" class="icon-btn right" title="Mostrar/ocultar menú" aria-label="Panel lateral derecho" :aria-expanded="ui.isMobile ? rightSidebar.drawer : rightSidebar.mode !== 'close'" @click="rightSidebar.toggle()">
          <icon name="left_panel_close  " size="30px" color="text-surface-500" />
        </button>
      </div>
    </div>
  </header>
</template>
<script setup lang="ts">
import type { MenuItem } from "primevue/menuitem";
import type Menu from "primevue/menu";
import type Popover from "primevue/popover";
import { instalar, puedeInstalar } from "@/app/pwa";
import { router } from "@/app/router";
import { useUiStore } from "@/app/ui";
import { salirDeStores } from "@/core/auth/alcanceSesion";
import { useSessionStore } from "@/core/auth/session";
import { useAccesoStore } from "@/core/acceso/store";
import { desactivarPush } from "@/core/chat/push";
import { useChatStore } from "@/core/chat/store";
import { useCuentaStore } from "@/core/cuenta/store";
import { pendientesDe } from "@/core/autorizacion/reglas";
import { clavesDeRuta } from "@/core/ayuda/reglas";
import { useAutorizacionesStore } from "@/core/autorizacion/store";
import { useVentanaChat } from "@/features/chat/ventana";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";
import IndicadorSincronizacion from "./IndicadorSincronizacion.vue";
import { useNavigationHistoryStore } from "./navigationHistory";
import { defineSidebarStore, type SidebarStore } from "./sidebarStore";
import ThemeEditor from "./ThemeEditor.vue";

/** Store del panel derecho visible (menú o panel propio de la ruta). */
defineProps<{ rightSidebar: SidebarStore | null }>();

const session = useSessionStore();
const acceso = useAccesoStore();
const chat = useChatStore();
const cuenta = useCuentaStore();
const autorizaciones = useAutorizacionesStore();
const autorizacionesPendientes = computed(() => pendientesDe(autorizaciones.resumen));
/** Pantalla actual para la ayuda contextual (la clave más específica); en la ayuda misma, ninguna. */
const ayudaPantalla = computed(() => {
  const r = router.currentRoute.value;
  return r.name === "ayuda" ? null : (clavesDeRuta(r.name, r.params)[0] ?? null);
});
const ui = useUiStore();
const ventanaChat = useVentanaChat();

/** El chat tal como quedó: la página (centro) o la ventana con su modo y estado. */
function invocarChat() {
  if (ventanaChat.esVentana) return ventanaChat.invocar();
  void router.push({ name: "chat", params: { canal: ventanaChat.canal ?? undefined } });
}

const temaPopover = ref<InstanceType<typeof Popover> | null>(null);
const temaAbierto = ref(false);

const sidebarStore = defineSidebarStore("left")();
type PopoverName = "notif" | "customizer" | "user" | "fullscreen" | null;
const openPopover = ref<PopoverName>(null);
const navigationHistory = useNavigationHistoryStore();

function formatClock(date: Date) {
  const pad = (n: number) => String(n).padStart(2, "0");
  const hours12 = date.getHours() % 12 || 12;
  const seconds10 = Math.floor(date.getSeconds() / 10) * 10;
  const ampm = date.getHours() >= 12 ? "pm" : "am";
  return `${pad(hours12)}:${pad(date.getMinutes())} <span class="text-[.8rem] font-bold">${pad(seconds10)}</span> ${ampm}`;
}
const currentTime = ref(formatClock(new Date()));
let clockTimer: ReturnType<typeof setInterval>;
onMounted(() => {
  clockTimer = setInterval(() => {
    currentTime.value = formatClock(new Date());
  }, 1000);
});
onUnmounted(() => clearInterval(clockTimer));

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen?.();
  } else {
    document.exitFullscreen?.();
  }
  toggle("fullscreen");
}
function toggle(name: Exclude<PopoverName, null>) {
  openPopover.value = openPopover.value === name ? null : name;
}

const menuUsuario = ref<InstanceType<typeof Menu> | null>(null);
const opcionesUsuario = computed<MenuItem[]>(() => [{ label: "Mi cuenta", iconName: "manage-accounts-outline", command: () => void router.push({ name: "mi-cuenta" }) }, ...(acceso.estaciones.length > 1 ? [{ label: "Estación de trabajo", iconName: "location-on-outline", command: () => (acceso.eligiendoEstacion = true) }] : []), ...(puedeInstalar.value ? [{ label: "Instalar app", iconName: "install-mobile", command: () => void instalar() }] : []), { separator: true }, { label: "Cerrar sesión", iconName: "logout", command: () => void logout() }]);

async function logout() {
  // Antes del logout (después el token ya no vale): en una taquilla compartida el siguiente no debe recibirlas.
  await desactivarPush().catch(() => undefined);
  await chat.salirDeLinea();
  await session.logout();
  await router.push({ name: "login" });
  // Ya fuera de las pantallas del usuario: nada montado usa sus stores.
  salirDeStores();
}
</script>
