<template>
  <aside :class="sidebarClasses">
    <template v-if="dynamicMenus.length > 0">
      <template v-for="group in groupedMenus" :key="group.label">
        <nav class="">
          <div class="sidebar-header">
            <span class="menu-icon">⚡</span>
            <span class="menu-text" style="font-weight: bold; font-size: 1.1rem">Dashboard</span>
          </div>
          <ul class="sidebar-menu">
            <li class="menu-item">
              <a href="#" class="menu-link">
                <span class="menu-icon">🏠</span>
                <span class="menu-text">Inicio</span>
              </a>
            </li>
            <li class="menu-item">
              <a href="#" class="menu-link">
                <span class="menu-icon">📊</span>
                <span class="menu-text">Analíticas</span>
              </a>
            </li>
            <li class="menu-item">
              <a href="#" class="menu-link">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">Configuración</span>
              </a>
            </li>
          </ul>
        </nav>
      </template>
    </template>

    <template v-else>
      <!-- <div class="nav-group-label">Operación</div> -->
      <nav>
        <!-- <div class="sidebar-header">
          <span class="menu-icon">⚡</span>
          <span class="menu-text" style="font-weight: bold; font-size: 1.1rem">Dashboard</span>
        </div> -->
        <div class="sidebar-control">
          <div @click="ui.setLeft('close')">
            <icon name="x" />
          </div>
          <div @click="ui.setLeft(ui.leftState == 'mini' ? 'open' : 'mini')">
            <icon :name="ui.leftState != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
          </div>
        </div>
        <ul class="sidebar-menu">
          <li class="menu-item">
            <a href="#" class="menu-link">
              <span class="menu-icon">🏠</span>
              <span class="menu-text">Inicio</span>
            </a>
          </li>
          <li class="menu-item">
            <a href="#" class="menu-link">
              <span class="menu-icon">📊</span>
              <span class="menu-text">Analíticas</span>
            </a>
          </li>
          <li class="menu-item">
            <a href="#" class="menu-link">
              <span class="menu-icon">⚙️</span>
              <span class="menu-text">Configuración</span>
            </a>
          </li>
        </ul>
      </nav>
    </template>
  </aside>
</template>
<script setup lang="ts">
import { computed } from "vue";
import { useMenusStore, type MenuItem } from "@/stores/menus";
import { NAV_MAIN, NAV_OPS } from "@/config/nav";
import AppIcon from "@/components/icons/AppIcon.vue";

const menusStore = useMenusStore();
const dynamicMenus = computed(() => menusStore.sidebarLeftItems);

interface MenuGroup {
  label: string;
  items: MenuItem[];
}

const groupedMenus = computed<MenuGroup[]>(() => {
  const items = dynamicMenus.value;
  if (items.length === 0) return [];
  return [{ label: "Navegación", items }];
});

const sidebarClasses = computed(() => [
  "sidebar",
  "left-side",
  { mini: ui.leftState === "mini" },
  { closed: ui.leftState === "close" && !ui.isMobile },
  { "mobile-hidden": ui.isMobile && !ui.mobileLeftOpen },
]);

const sidebarStyle = computed(() => (!ui.isMobile ? { width: ui.leftWidth } : {}));
</script>
