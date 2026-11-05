<!--
  Menús que el usuario ve en un área del shell (`sidebar_left`,
  `sidebar_right`, `topbar_right`), en el orden configurado. En las barras
  laterales cada menú lleva su nombre como encabezado de sección.
-->
<template>
  <NavMenuBar v-if="area === 'topbar_right'" :menus="menus" />
  <template v-else>
    <div class="border-t border-t-surface-300" v-for="menu in menus" :key="menu.id">
      <section class="nav-section" :class="{ open: opened.has(menu.id) }">
        <button v-if="menus.length > 1 && !sidebar?.compact" type="button" class="nav-section-title py-6!" @click="toggle(menu.id)">
          <icon :weight="200" class="text-surface-800" :name="!opened.has(menu.id) ? 'add' : 'remove'" size="1rem" />
          <span>{{ menu.nombre }}</span>
        </button>
        <Transition :css="false" @enter="onEnter" @leave="onLeave">
          <div v-if="sidebar && (sidebar.compact || menus.length < 2 || opened.has(menu.id))">
            <NavTree :items="menu.items" :sidebar="sidebar" />
          </div>
        </Transition>
      </section>
    </div>

    <slot v-if="!menus.length" name="empty" />
  </template>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { useUserMenusStore, type LayoutArea } from "@/core/navigation/userMenus";
import type { SidebarStore } from "../sidebarStore";
import NavMenuBar from "./NavMenuBar.vue";
import NavTree from "./NavTree.vue";

const props = defineProps<{ area: LayoutArea; sidebar?: SidebarStore }>();

const userMenus = useUserMenusStore();
const menus = computed(() => userMenus.areas[props.area]);

/** Menús contraídos: los guarda el store del panel (por usuario). */
const opened = computed(() => new Set(props.sidebar?.expandedMenus ?? []));
const toggle = (id: number) => props.sidebar?.toggleMenu(id);

function onEnter(el: Element, done: () => void) {
  gsap.fromTo(
    el,
    { height: 0, opacity: 0, overflow: "hidden" },
    {
      height: "auto",
      opacity: 1,
      duration: 0.3,
      ease: "sine.out",
      clearProps: "height,overflow,opacity",
      onComplete: done,
    },
  );
}
function onLeave(el: Element, done: () => void) {
  gsap.to(el, {
    height: 0,
    opacity: 0,
    overflow: "hidden",
    duration: 0.25,
    ease: "sine.out",
    onComplete: done,
  });
}
</script>

<style scoped>
.nav-section-title {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.25rem;
  cursor: pointer;
  text-align: start;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--p-surface-600);
  white-space: nowrap;
}
.nav-section.open {
  background-color: color-mix(in hsl, var(--p-surface-200) 20%, var(--p-surface-100) 70%);
}
.nav-section.open > .nav-section-title {
  position: relative;
  z-index: 90;
}

.nav-section-title > span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
