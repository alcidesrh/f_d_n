<template>
  <Sidebar side="right">
    <template #menu-content>
      <nav>
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
      </nav>
    </template>
  </Sidebar>
</template>
<script setup lang="ts">
import Sidebar from './Sidebar.vue'
import { defineSidebarStore } from './sidebarStore'

const sidebarStore = defineSidebarStore('right')()
const router = useRouter()

const sidebarRoutes = computed(() => router.getRoutes())
</script>
