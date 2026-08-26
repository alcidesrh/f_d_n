<template>
  <aside :class="sidebarClasses" :style="sidebarStyle">
    <div class="sidebar-control">
      <div @click="ui.setRight(ui.rightState == 'mini' ? 'open' : 'mini')">
        <icon :name="ui.rightState != 'mini' ? 'chevrons-right' : 'chevrons-left'" sw="2" />
      </div>
      <div @click="ui.setRight('close')">
        <icon name="x" sw="2" />
      </div>
    </div>
    <slot name="menu-content"></slot>

    <div class="p-3 sidebar-content">
      <slot>
        <template v-if="showMiniRail">
          <div class="sidebar-scroll">
            <div class="mini-quick">
              <button class="icon-btn" title="Próximas salidas">
                <AppIcon name="clock" :size="18" />
              </button>
              <button class="icon-btn" title="Notificaciones">
                <AppIcon name="bell" :size="18" />
                <span class="badge-dot"></span>
              </button>
              <button class="icon-btn" title="Nuevo boleto">
                <AppIcon name="plus" :size="18" />
              </button>
              <button class="icon-btn" title="Reportar incidente">
                <AppIcon name="alert" :size="18" />
              </button>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="sidebar-scroll">
            <div class="side-block">
              <div class="side-block-title">
                <b>Próximas salidas</b><AppIcon name="clock" :size="14" />
              </div>
              <div v-for="(d, i) in DEPARTURES" :key="i" class="dep-row">
                <div class="dep-time">{{ d.time }}</div>
                <div class="dep-info">
                  <b>{{ d.route }}</b>
                  <span>{{ d.bus }} · {{ d.driver }}</span>
                </div>
              </div>
            </div>

            <div class="side-block">
              <div class="side-block-title"><b>Acciones rápidas</b></div>
              <div class="quick-actions">
                <button class="qa-btn">
                  <span class="nav-ic"><AppIcon name="plus" :size="15" /></span> Nuevo boleto
                </button>
                <button class="qa-btn">
                  <span class="nav-ic"><AppIcon name="alert" :size="15" /></span> Reportar incidente
                </button>
                <button class="qa-btn">
                  <span class="nav-ic"><AppIcon name="users" :size="15" /></span> Asignar chofer
                </button>
              </div>
            </div>

            <div class="side-block">
              <div class="side-block-title"><b>Actividad reciente</b></div>
              <div
                v-for="(n, i) in recentActivity"
                :key="i"
                class="notif-item"
                style="padding: 10px 0"
              >
                <div class="notif-ic" :style="{ color: n.color, background: n.bg }">
                  <AppIcon :name="n.icon" :size="14" />
                </div>
                <div class="notif-body">
                  <b style="font-size: 12.5px">{{ n.title }}</b>
                  <time>{{ n.time }}</time>
                </div>
              </div>
            </div>
          </div>
        </template>
      </slot>
    </div>
  </aside>
</template>
<script setup lang="ts">
import { computed } from "vue";
// import { useUiStore } from '@/stores/ui'
import { DEPARTURES, NOTIFICATIONS } from "@/data/mock";
import AppIcon from "@/components/icons/AppIcon.vue";

const props = defineProps<{
  w?: number;
  nomini?: boolean;
}>();
// const ui = useUiStore()

const sidebarClasses = computed(() => [
  "sidebar",
  "bg-surface-50",
  "right-side",
  { mini: ui.rightState === "mini" },
  { closed: ui.rightState === "close" && !ui.isMobile },
  { "mobile-hidden": ui.isMobile && !ui.mobileRightOpen },
]);

const sidebarStyle = computed(() => {
  if (props.w) {
    // if(nomini && ui.rightState == 'mini')
    return !ui.isMobile ? { width: props.w + "px" } : {};
  }
  return !ui.isMobile ? { width: ui.rightWidth } : {};
});
// const sidebarStyle = computed(() => (!ui.isMobile ? { width: ui.rightWidth } : {}))
const showMiniRail = computed(() => ui.rightState === "mini" && !ui.isMobile);
const recentActivity = computed(() => NOTIFICATIONS.slice(0, 3));
</script>
