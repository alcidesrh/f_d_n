<template>
  <header class="app-header">
    <div class="flex btn-siderbar-header" :class="[sidebarStore.mode]">
      <button class="icon-btn" title="Mostrar/ocultar menú" @click="sidebarStore.setMode()">
        <AppIcon name="menu" :size="19" />
      </button>
      <Divider layout="vertical" class="mx-[5px]!" />
    </div>
    <div class="brand">
      <div style="min-width: 0">
        <div class="brand-name">FDN</div>
      </div>
    </div>

    <div class="header-crumbs">
      <nav class="crumbs" aria-label="Breadcrumb">
        <template v-for="(crumb, i) in crumbs" :key="crumb + i">
          <span v-if="i > 0" class="crumb-sep" aria-hidden="true"></span>
          <span :class="i === crumbs.length - 1 ? 'crumb-current' : ''">{{ crumb }}</span>
        </template>
      </nav>
    </div>

    <div class="header-actions">
      <template v-if="topbarMenuItems.length > 0">
        <router-link
          v-for="item in topbarMenuItems"
          :key="item.id"
          :to="item.ruta ?? '#'"
          class="icon-btn"
          :title="item.label"
        >
          <AppIcon :name="item.icon ?? 'circle'" :size="18" />
        </router-link>
      </template>
      <slot name="menu-content"></slot>

      <button
        class="icon-btn cursor-pointer"
        title="Personalizar apariencia"
        @click.stop="showThemeEditor()"
      >
        <AppIcon name="palette" :size="18" />
      </button>

      <button
        class="icon-btn"
        title="Pantalla completa"
        @click="toggleFullscreen"
        :class="{ 'active-state': openPopover === 'fullscreen' }"
      >
        <AppIcon name="maximize" :size="18" />
      </button>
      <div style="position: relative">
        <button
          class="icon-btn"
          :class="{ 'active-state': openPopover === 'notif' }"
          title="Notificaciones"
          @click.stop="toggle('notif')"
        >
          <AppIcon name="bell" :size="18" />
          <span class="badge-dot"></span>
        </button>
      </div>

      <div style="position: relative">
        <div class="header-user">
          <div class="who">
            <span>username</span>
          </div>
          <AppIcon name="chevrondown" :size="14" />
        </div>
      </div>
      <div class="flex btn-siderbar-header" :class="[sidebarStoreR.mode]">
        <button class="icon-btn" title="Mostrar/ocultar menú" @click="sidebarStoreR.setMode()">
          <AppIcon name="menu" :size="19" />
        </button>
        <Divider layout="vertical" class="mx-[5px]!" />
      </div>
    </div>
    <div class="flex btn-siderbar-header" :class="[ui.rightState]">
      <Divider layout="vertical" class="mx-[5px]!" />
      <button class="icon-btn right" title="Mostrar/ocultar menú" @click="ui.cycleRight()">
        <AppIcon name="menu" :size="19" />
      </button>
    </div>
  </header>
</template>
<script setup lang="ts">
import { computed } from "vue";
import { useMenusStore } from "@/stores/menus";
import { NOTIFICATIONS } from "@/data/mock";
import { useDialog } from "primevue/usedialog";
import ThemeEditor from "@/components/common/ThemeEditor.vue";
const dialog = useDialog();
const showThemeEditor = () => dialog.open(ThemeEditor, { props: { header: "Edit Profile" } });
defineProps<{ crumbs: string[] }>();
const sidebarStore = defineSidebarStore("left")();
const sidebarStoreR = defineSidebarStore("right")();
type PopoverName = "notif" | "customizer" | "user" | "fullscreen" | null;
const openPopover = ref<PopoverName>(null);

const menusStore = useMenusStore();
const topbarMenuItems = computed(() => menusStore.topbarRightItems);

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
</script>
