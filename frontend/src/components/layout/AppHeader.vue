<template>
  <header class="app-header">
    <div class="brand">
      <button class="icon-btn" title="Mostrar/ocultar menú" @click="ui.cycleLeft()">
        <AppIcon name="menu" :size="19" />
      </button>
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
      <!-- <button class="icon-btn" title="Panel de operación" @click="ui.cycleRight()">
            <AppIcon name="chevronsleft" :size="18" />
          </button>

          <button
            class="icon-btn"
            title="Cambiar tema"
            @click="ui.setMode(ui.mode === 'light' ? 'dark' : 'light')"
          >
            <AppIcon :name="ui.mode === 'light' ? 'moon' : 'sun'" :size="18" />
          </button> -->

      <!-- Appearance customizer: 4th icon counting from the right, as in PrimeVue's Sakai template -->
      <!-- <ThemeEditor /> -->
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
        <!-- <div v-if="openPopover === 'notif'" class="pop" style="right: 0; width: 340px">
          <div class="pop-header">
            <b>Notificaciones</b>
            <p>5 nuevas actualizaciones de la operación</p>
          </div>
          <div class="pop-scroll">
            <div v-for="(n, i) in NOTIFICATIONS" :key="i" class="notif-item">
              <div class="notif-ic" :style="{ color: n.color, background: n.bg }">
                <AppIcon :name="n.icon" :size="15" />
              </div>
              <div class="notif-body">
                <b>{{ n.title }}</b>
                <p>{{ n.desc }}</p>
                <time>{{ n.time }}</time>
              </div>
            </div>
          </div>
          <div class="pop-footer">
            <button class="btn btn-subtle" style="width: 100%; justify-content: center">
              Ver todas
            </button>
          </div>
        </div> -->
      </div>

      <div style="position: relative">
        <div class="header-user">
          <div class="who">
            <span>username</span>
          </div>
          <AppIcon name="chevrondown" :size="14" />
        </div>
      </div>
    </div>
  </header>
</template>
<script setup lang="ts">
import { NOTIFICATIONS } from "@/data/mock";
import ThemeEditor from "@/components/ThemeEditor.vue"
import { useDialog } from 'primevue/usedialog';
const dialog = useDialog();
const showThemeEditor = () => dialog.open(ThemeEditor, {props: {header:"Edit Profile"}})
defineProps<{ crumbs: string[] }>();
type PopoverName = "notif" | "customizer" | "user" | "fullscreen" | null;
const openPopover = ref<PopoverName>(null);
// const ui = useUiStore();

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
