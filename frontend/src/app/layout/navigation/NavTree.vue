<!--
  Árbol vertical de ítems de un menú para las barras laterales. Un ítem con
  hijos navega a su ruta y además se despliega con el chevrón; en modo `mini`
  solo se ven las raíces (íconos).
-->
<template>
  <ul class="sidebar-menu nav-tree" :class="{ 'nav-tree--nested': depth > 0 }">
    <li v-for="item in items" :key="item.id" class="menu-item">
      <div class="nav-row">
        <component
          :is="navTarget(item, router) ? RouterLink : 'span'"
          :to="navTarget(item, router) ?? undefined"
          class="menu-link"
          :class="{ 'nav-disabled': !navTarget(item, router) }"
          :style="{ paddingInlineStart: depth ? `${1.25 + depth * 0.9}rem` : undefined }"
          :title="navTarget(item, router) ? item.label : `${item.label} (ruta no navegable)`"
          @mouseenter="sidebar.handleMouseEnter"
          @mouseleave="sidebar.handleMouseLeave"
        >
          <span class="menu-icon"><icon :name="item.icon ?? 'point'" size="1.5rem" /></span>
          <span class="menu-text">{{ item.label }}</span>
        </component>
        <button
          v-if="item.children.length && sidebar.mode !== 'mini'"
          type="button"
          class="nav-toggle"
          :aria-expanded="expanded.has(item.id)"
          :title="expanded.has(item.id) ? 'Contraer' : 'Desplegar'"
          @click="toggle(item.id)"
        >
          <icon
            name="chevron-right"
            class="nav-chevron"
            :class="{ 'is-open': expanded.has(item.id) }"
          />
        </button>
      </div>
      <Transition :css="false" @enter="onEnter" @leave="onLeave">
        <NavTree
          v-if="item.children.length && expanded.has(item.id) && sidebar.mode !== 'mini'"
          :items="item.children"
          :depth="depth + 1"
          :sidebar="sidebar"
        />
      </Transition>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { RouterLink } from 'vue-router'
import type { NavItem } from '@/core/navigation/userMenus'
import type { SidebarStore } from '../sidebarStore'
import { navTarget } from './navTarget'

defineOptions({ name: 'NavTree' })

withDefaults(defineProps<{ items: NavItem[]; sidebar: SidebarStore; depth?: number }>(), {
  depth: 0,
})

const router = useRouter()
const expanded = ref(new Set<number>())

/** Submenú: se despliega de alto 0 a su altura natural y sus ítems entran escalonados. */
function onEnter(el: Element, done: () => void) {
  gsap
    .timeline({ onComplete: done })
    .fromTo(
      el,
      { height: 0, opacity: 0, overflow: 'hidden' },
      {
        height: 'auto',
        opacity: 1,
        duration: 0.3,
        ease: 'power2.out',
        clearProps: 'height,overflow,opacity',
      },
    )
    .fromTo(
      el.querySelectorAll(':scope > .menu-item'),
      { x: -8, opacity: 0 },
      {
        x: 0,
        opacity: 1,
        duration: 0.25,
        stagger: 0.04,
        ease: 'power2.out',
        clearProps: 'transform,opacity',
      },
      0.05,
    )
}

/** Se pliega hasta alto 0. */
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

function toggle(id: number) {
  const next = new Set(expanded.value)
  if (!next.delete(id)) next.add(id)
  expanded.value = next
}
</script>

<style scoped>
.nav-tree--nested {
  padding: 0;
}
.nav-row {
  position: relative;
  display: flex;
  align-items: center;
}
/* Textos largos: elipsis en lugar de salirse de la barra o pisar el chevrón. */
.nav-row > .menu-link {
  min-width: 0;
}
.nav-row > .menu-link .menu-text {
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-row:has(.nav-toggle) > .menu-link {
  padding-inline-end: 2.5rem;
}
.nav-toggle {
  position: absolute;
  inset-inline-end: 0.5rem;
  display: flex;
  padding: 0.25rem;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.nav-toggle:hover {
  background-color: var(--p-surface-100);
}
.nav-chevron {
  transition: transform 0.25s ease;
}
.nav-chevron.is-open {
  transform: rotate(90deg);
}
.nav-disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
