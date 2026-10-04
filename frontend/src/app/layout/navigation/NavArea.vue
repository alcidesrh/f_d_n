<!--
  Menús que el usuario ve en un área del shell (`sidebar_left`,
  `sidebar_right`, `topbar_right`), en el orden configurado. En las barras
  laterales cada menú lleva su nombre como encabezado de sección.
-->
<template>
  <NavMenuBar v-if="area === 'topbar_right'" :menus="menus" />
  <nav v-else class="nav-area">
    <section v-for="menu in menus" :key="menu.id" class="nav-section">
      <button
        v-if="menus.length > 1 && !sidebar?.collapsed"
        type="button"
        class="nav-section-title"
        :aria-expanded="!closed.has(menu.id)"
        :title="closed.has(menu.id) ? 'Desplegar' : 'Contraer'"
        @click="toggle(menu.id)"
      >
        <span>{{ menu.nombre }}</span>
        <icon
          name="chevron-down"
          class="nav-section-chevron"
          :class="{ 'is-closed': closed.has(menu.id) }"
        />
      </button>
      <Transition :css="false" @enter="onEnter" @leave="onLeave">
        <div v-if="sidebar && (sidebar.collapsed || menus.length < 2 || !closed.has(menu.id))">
          <NavTree :items="menu.items" :sidebar="sidebar" />
        </div>
      </Transition>
    </section>
    <slot v-if="!menus.length" name="empty" />
  </nav>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { useUserMenusStore, type LayoutArea } from '@/core/navigation/userMenus'
import type { SidebarStore } from '../sidebarStore'
import NavMenuBar from './NavMenuBar.vue'
import NavTree from './NavTree.vue'

const props = defineProps<{ area: LayoutArea; sidebar?: SidebarStore }>()

const userMenus = useUserMenusStore()
const menus = computed(() => userMenus.areas[props.area])

const closed = ref(new Set<number>())
function toggle(id: number) {
  const next = new Set(closed.value)
  if (!next.delete(id)) next.add(id)
  closed.value = next
}

function onEnter(el: Element, done: () => void) {
  gsap.fromTo(
    el,
    { height: 0, opacity: 0, overflow: 'hidden' },
    {
      height: 'auto',
      opacity: 1,
      duration: 0.3,
      ease: 'power2.out',
      clearProps: 'height,overflow,opacity',
      onComplete: done,
    },
  )
}
function onLeave(el: Element, done: () => void) {
  gsap.to(el, {
    height: 0,
    opacity: 0,
    overflow: 'hidden',
    duration: 0.22,
    ease: 'power2.in',
    onComplete: done,
  })
}
</script>

<style scoped>
.nav-section-title {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 1.25rem;
  background-color: var(--p-surface-100);
  cursor: pointer;
  text-align: start;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
.nav-section-title:hover {
  background-color: var(--p-surface-200);
}
.nav-section-chevron {
  transition: transform 0.25s ease;
}
.nav-section-chevron.is-closed {
  transform: rotate(-90deg);
}
</style>
