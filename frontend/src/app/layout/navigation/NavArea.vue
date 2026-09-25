<!--
  Menús que el usuario ve en un área del shell (`sidebar_left`,
  `sidebar_right`, `topbar_right`), en el orden configurado. En las barras
  laterales cada menú lleva su nombre como encabezado de sección.
-->
<template>
  <NavMenuBar v-if="area === 'topbar_right'" :menus="menus" />
  <nav v-else class="nav-area">
    <section v-for="menu in menus" :key="menu.id" class="nav-section">
      <div v-if="menus.length > 1 && sidebar?.mode !== 'mini'" class="nav-section-title">
        {{ menu.nombre }}
      </div>
      <NavTree v-if="sidebar" :items="menu.items" :sidebar="sidebar" />
    </section>
    <slot v-if="!menus.length" name="empty" />
  </nav>
</template>

<script setup lang="ts">
import { nextTick } from 'vue'
import { useUserMenusStore, type LayoutArea } from '@/core/navigation/userMenus'
import type { SidebarStore } from '../sidebarStore'
import NavMenuBar from './NavMenuBar.vue'
import NavTree from './NavTree.vue'

const props = defineProps<{ area: LayoutArea; sidebar?: SidebarStore }>()

const userMenus = useUserMenusStore()
const menus = computed(() => userMenus.areas[props.area])

// Los menús llegan después de montar el sidebar: reaplicar su modo (p. ej. ocultar textos en `mini`).
watch(menus, async () => {
  await nextTick()
  props.sidebar?.sidebarUpdate()
})
</script>

<style scoped>
.nav-section + .nav-section {
  border-top: 1px solid var(--p-content-border-color);
}
.nav-section-title {
  padding: 0.75rem 1.25rem 0;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
</style>
