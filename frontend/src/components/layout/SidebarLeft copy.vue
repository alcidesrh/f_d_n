<template>
  <aside :class="sidebarClasses" :style="sidebarStyle">
    <div class="sidebar-control">
      <div @click="ui.setLeft('close')">
        <icon name="x" />
      </div>
      <div @click="ui.setLeft(ui.leftState == 'mini' ? 'open' : 'mini')">
        <icon :name="ui.leftState != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
      </div>
    </div>

    <div class="sidebar-content">
      <slot name="menu-content"></slot>

      <template v-if="dynamicMenus.length > 0">
        <template v-for="group in groupedMenus" :key="group.label">
          <!-- <div class="nav-group-label">{{ group.label }}</div> -->
          <nav class="nav">
            <div class="nav-rail" aria-hidden="true"></div>
            <template v-for="item in group.items" :key="item.id">
              <router-link :to="item.ruta ?? '#'" class="a-nav-item" :data-tip="item.label">
                <div class="nav-item">
                  <span class="stop-dot" aria-hidden="true"></span>
                  <span class="nav-ic"><AppIcon :name="item.icon ?? 'circle'" :size="18" /></span>
                  <span class="nav-label">{{ item.label }}</span>
                </div>
              </router-link>
              <template v-if="item.children?.length">
                <router-link
                  v-for="child in item.children"
                  :key="child.id"
                  :to="child.ruta ?? '#'"
                  class="a-nav-item"
                  :data-tip="child.label"
                  style="padding-left: 1.5rem"
                >
                  <div class="nav-item">
                    <span class="stop-dot" aria-hidden="true"></span>
                    <span class="nav-ic"
                      ><AppIcon :name="child.icon ?? 'circle'" :size="16"
                    /></span>
                    <span class="nav-label">{{ child.label }}</span>
                  </div>
                </router-link>
              </template>
            </template>
          </nav>
        </template>
      </template>

      <template v-else>
        <!-- <div class="nav-group-label">Operación</div> -->
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
            </div>
          </router-link>
        </nav>
      </template>
    </div>
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
