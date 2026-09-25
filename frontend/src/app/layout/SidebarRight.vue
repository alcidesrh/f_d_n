<template>
  <Sidebar side="right">
    <template #menu-content>
      <NavArea area="sidebar_right" :sidebar="sidebarStore">
        <!-- Sin ningún menú configurado para el usuario: todas las rutas, para no quedar sin navegación. -->
        <template v-if="userMenus.loaded && !hasAnyMenu" #empty>
          <div class="sidebar-header">
            <span class="menu-icon">⚡</span>
            <span class="menu-text" style="font-weight: bold; font-size: 1.1rem">Dashboard</span>
          </div>
          <ul class="sidebar-menu">
            <li v-for="route in sidebarRoutes" :key="route.name ?? route.path" class="menu-item">
              <RouterLink
                :to="route.path"
                class="menu-link"
                @mouseenter="sidebarStore.handleMouseEnter"
                @mouseleave="sidebarStore.handleMouseLeave"
              >
                <span class="menu-icon">
                  <icon :name="route.meta.icon ?? 'link'" size="1.5rem" />
                </span>
                <span class="menu-text">{{ route.meta.label ?? route.name }}</span>
              </RouterLink>
            </li>
          </ul>
        </template>
      </NavArea>
    </template>
  </Sidebar>
</template>
<script setup lang="ts">
import { useUserMenusStore } from '@/core/navigation/userMenus'
import NavArea from './navigation/NavArea.vue'
import Sidebar from './Sidebar.vue'
import { defineSidebarStore } from './sidebarStore'

const sidebarStore = defineSidebarStore('right')()
const router = useRouter()
const userMenus = useUserMenusStore()

const sidebarRoutes = computed(() => router.getRoutes())
const hasAnyMenu = computed(() => Object.values(userMenus.areas).some((menus) => menus.length > 0))
</script>
