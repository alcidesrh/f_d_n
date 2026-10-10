<template>
  <header class="app-header">
    <div class="left-header" :class="[sidebarStore.mode]">
      <div class="flex btn-siderbar-header">
        <button type="button" class="icon-btn" aria-label="Menú de navegación" :aria-expanded="ui.isMobile ? sidebarStore.drawer : sidebarStore.mode !== 'close'" @click="sidebarStore.toggle()">
          <icon name="left_panel_open" size="35px" :weight="100" color="text-surface-500" />
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

    <div class="right-header" :class="[rightSidebar.mode]">
      <div class="header-actions">
        <slot name="menu-content"></slot>

        <button class="icon-btn cursor-pointer" :title="ui.mode === 'dark' ? 'Modo claro' : 'Modo oscuro'" :aria-label="ui.mode === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'" @click="ui.toggleMode()">
          <icon :name="ui.mode === 'dark' ? 'light-mode-outline' : 'dark-mode-outline'" />
        </button>

        <button class="icon-btn cursor-pointer" title="Personalizar apariencia" aria-label="Personalizar apariencia" @click.stop="showThemeEditor()">
          <icon name="palette-outline" />
        </button>

        <button class="icon-btn header-fullscreen" title="Pantalla completa" aria-label="Pantalla completa" @click="toggleFullscreen" :class="{ 'active-state': openPopover === 'fullscreen' }">
          <icon name="fullscreen" />
        </button>
        <button type="button" class="icon-btn header-chat" :aria-label="chat.noLeidos ? `Mensajes, ${chat.noLeidos} sin leer` : 'Mensajes'" v-tooltip.bottom="'Mensajes'" @click="invocarChat">
          <icon name="forum-outline" />
          <span v-if="chat.noLeidos" class="header-chat__badge">{{ chat.noLeidos > 99 ? "99+" : chat.noLeidos }}</span>
        </button>
        <div style="position: relative">
          <button class="icon-btn" title="Notificaciones" aria-label="Notificaciones" @click.stop="toggle('notif')">
            <icon name="notifications-outline" />
          </button>
        </div>

        <div style="position: relative">
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
      </div>
      <div class="flex btn-siderbar-header">
        <Divider layout="vertical" class="mx-[5px]!" />
        <button type="button" class="icon-btn right" title="Mostrar/ocultar menú" aria-label="Panel lateral derecho" :aria-expanded="ui.isMobile ? rightSidebar.drawer : rightSidebar.mode !== 'close'" @click="rightSidebar.toggle()">
          <icon name="menu" />
        </button>
      </div>
    </div>
  </header>
</template>
<script setup lang="ts">
import type { MenuItem } from "primevue/menuitem";
import type Menu from "primevue/menu";
import { useDialog } from "primevue/usedialog";
import { router } from "@/app/router";
import { useUiStore } from "@/app/ui";
import { useSessionStore } from "@/core/auth/session";
import { useChatStore } from "@/core/chat/store";
import { useCuentaStore } from "@/core/cuenta/store";
import { useVentanaChat } from "@/features/chat/ventana";
import ChatAvatar from "@/shared/chat/ChatAvatar.vue";
import { useNavigationHistoryStore } from "./navigationHistory";
import { defineSidebarStore, type SidebarStore } from "./sidebarStore";
import ThemeEditor from "./ThemeEditor.vue";

/** Store del panel derecho visible (menú o panel propio de la ruta). */
defineProps<{ rightSidebar: SidebarStore }>();

const session = useSessionStore();
const chat = useChatStore();
const cuenta = useCuentaStore();
const ui = useUiStore();
const ventanaChat = useVentanaChat();

/** El chat tal como quedó: la página (centro) o la ventana con su modo y estado. */
function invocarChat() {
  if (ventanaChat.esVentana) return ventanaChat.invocar();
  void router.push({ name: "chat", params: { canal: ventanaChat.canal ?? undefined } });
}

const dialog = useDialog();

const showThemeEditor = () => dialog.open(ThemeEditor, { props: { header: "Edit Profile" } });

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
const opcionesUsuario: MenuItem[] = [{ label: "Mi cuenta", iconName: "manage-accounts-outline", command: () => void router.push({ name: "mi-cuenta" }) }, { separator: true }, { label: "Cerrar sesión", iconName: "logout", command: () => void logout() }];

async function logout() {
  await chat.salirDeLinea();
  await session.logout();
  await router.push({ name: "login" });
}
</script>
