<!--
  Menús que el usuario ve en un área del shell (`sidebar_left`,
  `sidebar_right`, `topbar_right`), en el orden configurado. En las barras
  laterales cada menú lleva su nombre como encabezado de sección.
-->
<template>
  <NavMenuBar v-if="area === 'topbar_right'" :menus="menus" />
  <template v-else>
    <section v-for="menu in menus" :key="menu.id" class="nav-section">
      <button v-if="menus.length > 1 && !sidebar?.collapsed" type="button" class="nav-section-title" :aria-expanded="!closed.has(menu.id)" :title="closed.has(menu.id) ? 'Desplegar' : 'Contraer'" @click="toggle(menu.id)">
        <icon :name="closed.has(menu.id) ? 'plus' : 'minus'" size="0.9rem" />
        <span>{{ menu.nombre }}</span>
      </button>
      <Transition :css="false" @enter="onEnter" @leave="onLeave">
        <div v-if="sidebar && (sidebar.collapsed || menus.length < 2 || !closed.has(menu.id))">
          <NavTree :items="menu.items" :sidebar="sidebar" />
        </div>
      </Transition>
      <divider class="my-4!" />
    </section>
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

const closed = ref(new Set<number>());
function toggle(id: number) {
  const next = new Set(closed.value);
  if (!next.delete(id)) next.add(id);
  closed.value = next;
}

function onEnter(el: Element, done: () => void) {
  gsap.fromTo(
    el,
    { height: 0, opacity: 0, overflow: "hidden" },
    {
      height: "auto",
      opacity: 1,
      duration: 0.3,
      ease: "power2.out",
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
    duration: 0.22,
    ease: "power2.in",
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
  /*background-color: white;*/
  /*var(--p-surface-50);*/
  cursor: pointer;
  text-align: start;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
.nav-section-title:hover {
  background-color: var(--p-surface-200);
}
</style>
