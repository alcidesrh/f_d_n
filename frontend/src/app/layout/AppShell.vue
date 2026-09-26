<template>
  <div class="shell">
    <AppHeader :right-sidebar="rightSidebar">
      <template #menu-content>
        <NavArea area="topbar_right" />
      </template>
    </AppHeader>
    <div class="body-row">
      <!-- Debajo de `lg` los sidebars son drawers: el fondo los cierra. -->
      <Transition name="fade">
        <div v-if="drawerOpen" class="shell-backdrop" aria-hidden="true" @click="closeDrawers" />
      </Transition>
      <SidebarLeft />
      <main class="main">
        <div class="main-inner">
          <slot />
        </div>
      </main>
      <Sidebar v-if="panel" :key="String(route.name)" :store="rightSidebar" nomini>
        <template #menu-content>
          <component :is="panel" />
        </template>
      </Sidebar>
      <SidebarRight v-else />
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import { useUiStore } from '@/app/ui'
import { useSessionStore } from '@/core/auth/session'
import { useUserMenusStore } from '@/core/navigation/userMenus'
import AppHeader from './AppHeader.vue'
import NavArea from './navigation/NavArea.vue'
import Sidebar from './Sidebar.vue'
import SidebarLeft from './SidebarLeft.vue'
import SidebarRight from './SidebarRight.vue'
import { defineSidebarStore } from './sidebarStore'

const route = useRoute()
const session = useSessionStore()
const userMenus = useUserMenusStore()

// Menús del usuario: se cargan al entrar al shell y al cambiar de usuario.
watch(
  () => session.user,
  (user) => (user ? void userMenus.load() : userMenus.clear()),
  { immediate: true },
)

/** Panel propio de la ruta (`meta.panel`), con su propio store de ancho/modo. */
const panel = computed(() =>
  route.meta.panel ? defineAsyncComponent(route.meta.panel.component) : null,
)

const rightSidebar = computed(() => {
  if (!route.meta.panel) return defineSidebarStore('right')()
  const store = defineSidebarStore('right', `panel:${String(route.name)}`)()
  store.open = route.meta.panel.width ?? store.open
  return store
})

// Drawers (debajo de `lg`) --------------------------------------------------
const ui = useUiStore()
const leftSidebar = defineSidebarStore('left')()
const drawerOpen = computed(() => leftSidebar.drawer || rightSidebar.value.drawer)

function closeDrawers() {
  leftSidebar.drawer = false
  rightSidebar.value.drawer = false
}

// Uno a la vez; se cierran al navegar y al pasar a escritorio.
watch(
  () => leftSidebar.drawer,
  (open) => open && (rightSidebar.value.drawer = false),
)
watch(
  () => rightSidebar.value.drawer,
  (open) => open && (leftSidebar.drawer = false),
)
watch(() => route.fullPath, closeDrawers)
watch(
  () => ui.isMobile,
  (mobile) => !mobile && closeDrawers(),
)

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && drawerOpen.value) closeDrawers()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>
