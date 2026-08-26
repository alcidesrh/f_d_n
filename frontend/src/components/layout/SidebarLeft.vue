<template>
  <aside :class="sidebarClasses" :style="sidebarStyle">
    <!-- <div class="h-[32px] w-full"></div> -->
    <div class="sidebar-control">
      <div @click="ui.setLeft('close')">
        <icon name="x" sw="2" />
      </div>
      <div @click="ui.setLeft(ui.leftState == 'mini' ? 'open' : 'mini')">
        <icon :name="ui.leftState != 'mini' ? 'chevrons-left' : 'chevrons-right'" sw="2" />
        <!-- <i
          :class="[ui.leftState != 'mini' ? 'pi pi-angle-double-left' : 'pi pi-chevron-right']"
        ></i> -->
      </div>
    </div>

    <div class="sidebar-content">
      <slot name="menu-content"></slot>
      <div class="nav-group-label">Operación</div>
      <nav class="nav">
        <div class="nav-rail" aria-hidden="true"></div>
        <router-link
          v-for="item in NAV_MAIN"
          :key="item.to"
          :to="item.to"
          class="a-nav-item"
          :data-tip="item.label"
        >
          <div class="nav-item">
            <span class="stop-dot" aria-hidden="true"></span>
            <span class="nav-ic"><AppIcon :name="item.icon" :size="18" /></span>
            <span class="nav-label">{{ item.label }}</span>
            <span v-if="item.count" class="nav-count">{{ item.count }}</span>
          </div>
        </router-link>
      </nav>

      <div class="nav-group-label">Gestión</div>
      <nav class="nav">
        <div class="nav-rail" aria-hidden="true"></div>
        <router-link
          v-for="item in NAV_OPS"
          :key="item.to"
          :to="item.to"
          class="a-nav-item"
          :data-tip="item.label"
        >
          <div class="nav-item">
            <span class="stop-dot" aria-hidden="true"></span>
            <span class="nav-ic"><AppIcon :name="item.icon" :size="18" /></span>
            <span class="nav-label">{{ item.label }}</span>
            <span v-if="item.count" class="nav-count">{{ item.count }}</span>
          </div>
        </router-link>
      </nav>
    </div>
  </aside>
</template>
<script setup lang="ts">
import { computed } from "vue";
import { useUiStore } from "@/stores/ui";
import { NAV_MAIN, NAV_OPS } from "@/config/nav";
import AppIcon from "@/components/icons/AppIcon.vue";

// const ui = useUiStore();

const sidebarClasses = computed(() => [
  "sidebar",
  "left-side",
  { mini: ui.leftState === "mini" },
  { closed: ui.leftState === "close" && !ui.isMobile },
  { "mobile-hidden": ui.isMobile && !ui.mobileLeftOpen },
]);

const sidebarStyle = computed(() => (!ui.isMobile ? { width: ui.leftWidth } : {}));
</script>
