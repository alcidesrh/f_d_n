<!--
  Árbol vertical de ítems de un menú para las barras laterales. Un ítem con
  hijos navega a su ruta y además se despliega con el botón +/− a su izquierda; en modo
  `mini` solo se ven las raíces (íconos).
-->
<template>
  <ul class="sidebar-menu nav-tree" :class="{ 'nav-tree--nested': depth > 0 }">
    <li v-for="item in items" :key="item.id" class="menu-item">
      <div class="nav-row" :class="{ 'nav-row--mini': sidebar.collapsed }">
        <template v-if="!sidebar.collapsed">
          <button v-if="item.children.length" type="button" class="nav-toggle" :style="toggleIndent" :aria-expanded="expanded.has(item.id)" :title="expanded.has(item.id) ? 'Contraer' : 'Desplegar'" @click="toggle(item.id)">
            <icon :name="expanded.has(item.id) ? 'remove' : 'add'" size="0.9rem" />
          </button>
          <span v-else class="nav-toggle nav-toggle--spacer" :style="toggleIndent" aria-hidden="true" />
        </template>
        <component :is="navTarget(item, router) ? RouterLink : 'span'" :to="navTarget(item, router) ?? undefined" class="menu-link" :class="{ 'nav-disabled': !navTarget(item, router) }" :title="navTarget(item, router) ? item.label : `${item.label} (ruta no navegable)`">
          <span class="menu-icon"><icon :name="item.icon ?? 'fiber-manual-record'" size="1.3rem" /></span>
          <span class="menu-text">{{ item.label }}</span>
        </component>
      </div>
      <Transition :css="false" @enter="onEnter" @leave="onLeave">
        <NavTree v-if="item.children.length && expanded.has(item.id) && !sidebar.collapsed" :items="item.children" :depth="depth + 1" :sidebar="sidebar" />
      </Transition>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { RouterLink } from "vue-router";
import type { NavItem } from "@/core/navigation/userMenus";
import type { SidebarStore } from "../sidebarStore";
import { navTarget } from "./navTarget";

defineOptions({ name: "NavTree" });

const props = withDefaults(defineProps<{ items: NavItem[]; sidebar: SidebarStore; depth?: number }>(), {
  depth: 0,
});

const toggleIndent = computed(() => ({ marginInlineStart: `${0.5 + props.depth * 0.9}rem` }));
const router = useRouter();
const route = useRoute();
const expanded = ref(new Set<number>());

const contieneRuta = (item: NavItem, name: unknown): boolean => item.children.some((c) => c.route.name === name || contieneRuta(c, name));

/** Despliega los ítems que contienen la ruta actual (al montar y al navegar). */
watch(
  () => route.name,
  (name) => {
    const padres = props.items.filter((i) => contieneRuta(i, name) && !expanded.value.has(i.id));
    if (padres.length) expanded.value = new Set([...expanded.value, ...padres.map((i) => i.id)]);
  },
  { immediate: true },
);

/** Submenú: se despliega de alto 0 a su altura natural y sus ítems entran escalonados. */
function onEnter(el: Element, done: () => void) {
  gsap
    .timeline({ onComplete: done })
    .fromTo(
      el,
      { height: 0, opacity: 0, overflow: "hidden" },
      {
        height: "auto",
        opacity: 1,
        duration: 0.3,
        ease: "power2.out",
        clearProps: "height,overflow,opacity",
      },
    )
    .fromTo(
      el.querySelectorAll(":scope > .menu-item"),
      { x: -8, opacity: 0 },
      {
        x: 0,
        opacity: 1,
        duration: 0.25,
        stagger: 0.04,
        ease: "power2.out",
        clearProps: "transform,opacity",
      },
      0.05,
    );
}

/** Se pliega hasta alto 0. */
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

function toggle(id: number) {
  const next = new Set(expanded.value);
  if (!next.delete(id)) next.add(id);
  expanded.value = next;
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
.nav-row > .menu-link {
  flex: 1;
}
.nav-row:not(.nav-row--mini) > .menu-link {
  padding-inline-start: 0.5rem;
}
.nav-toggle {
  flex: none;
  display: flex;
  width: 1.5rem;
  height: 1.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.nav-toggle--spacer {
  cursor: default;
}
.nav-toggle--spacer:hover {
  background: none;
}
.nav-toggle:not(.nav-toggle--spacer):hover {
  background-color: var(--p-surface-100);
}
.nav-disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
