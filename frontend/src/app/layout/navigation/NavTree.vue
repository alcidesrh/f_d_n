<!--
  Árbol vertical de ítems de un menú para las barras laterales. Un ítem con
  hijos navega a su ruta y además se despliega con el botón +/− a su izquierda; un
  grupo (sin ruta) solo se despliega, con su fila entera. En modo `mini` solo se
  ven las raíces (íconos).
-->
<template>
  <ul class="sidebar-menu nav-tree @container" :class="{ 'nav-tree--nested': depth > 0 }">
    <li v-for="item in items" :key="item.id" class="menu-item @max-[60px]:last:pb-0 last:pb-8 py-2">
      <div class="nav-row" :class="{ 'nav-row--mini': compact }">
        <template v-if="!compact">
          <button v-if="item.children.length" type="button" class="nav-toggle" :style="toggleIndent" :aria-expanded="expanded.has(item.id)" :title="expanded.has(item.id) ? 'Contraer' : 'Desplegar'" @click="toggle(item.id)">
            <icon class="submenu-toggle-icon" :name="expanded.has(item.id) ? 'remove' : 'add'" size="0.9rem" />
          </button>
          <span v-else class="nav-toggle nav-toggle--spacer" :style="toggleIndent" aria-hidden="true" />
        </template>
        <component :is="linkTag(item)" :to="hasFlyout(item) ? undefined : (navTarget(item, router) ?? undefined)" :type="linkTag(item) === 'button' ? 'button' : undefined" class="menu-link" :class="{ 'nav-disabled': !navTarget(item, router) && !isGroup(item) && !hasFlyout(item), 'menu-link--flyout': hasFlyout(item), 'is-open': abierto?.id === item.id, 'contiene-ruta': hasFlyout(item) && contieneRuta(item, route.name) }" :title="titleOf(item)" :aria-expanded="isGroup(item) || hasFlyout(item) ? (hasFlyout(item) ? abierto?.id === item.id : expanded.has(item.id)) : undefined" :aria-haspopup="hasFlyout(item) ? 'menu' : undefined" @click="onClick($event, item)">
          <span class="menu-icon"><icon :name="item.icon ?? 'fiber-manual-record'" size="1.3rem" /></span>
          <span class="menu-text">{{ item.label }}</span>
          <span v-if="hasFlyout(item)" class="menu-flyout-mark" aria-hidden="true" />
        </component>
      </div>
      <Transition :css="false" @enter="onEnter" @leave="onLeave">
        <NavTree v-if="item.children.length && expanded.has(item.id) && !compact" :items="item.children" :depth="depth + 1" :sidebar="sidebar" :floating="floating" />
      </Transition>
    </li>
    <!-- Modo `mini`: los hijos de un ítem salen en un panel flotante junto a la barra. -->
    <Teleport v-if="depth === 0 && !floating" to="body">
      <Transition name="nav-flyout">
        <div v-if="abierto" ref="flyoutEl" class="nav-flyout" :class="`nav-flyout--${sidebar.side}`" :style="flyoutStyle" role="menu" :aria-label="abierto.label" @keydown.esc.stop="cerrar(true)">
          <component :is="navTarget(abierto, router) ? RouterLink : 'div'" :to="navTarget(abierto, router) ?? undefined" class="nav-flyout__head">
            <icon :name="abierto.icon ?? 'fiber-manual-record'" size="1.1rem" color="text-inherit" />
            <span class="truncate">{{ abierto.label }}</span>
          </component>
          <NavTree :items="abierto.children" :sidebar="sidebar" floating />
        </div>
      </Transition>
    </Teleport>
  </ul>
</template>

<script setup lang="ts">
import { gsap } from "gsap";
import { RouterLink } from "vue-router";
import type { NavItem } from "@/core/navigation/userMenus";
import type { SidebarStore } from "../sidebarStore";
import { navTarget } from "./navTarget";

defineOptions({ name: "NavTree" });

const props = withDefaults(defineProps<{ items: NavItem[]; sidebar: SidebarStore; depth?: number; /** Dentro del panel flotante del modo `mini`: siempre con textos. */ floating?: boolean }>(), {
  depth: 0,
  floating: false,
});

/** Sangría por nivel: el margen sale del CSS (`--depth`) para que GSAP pueda animarlo sin pisar estilos de Vue. */
const toggleIndent = computed(() => ({ "--depth": props.depth }));
const router = useRouter();
const route = useRoute();
/** Ítems desplegados: los guarda el store del panel (por usuario). */
const expanded = computed(() => new Set(props.sidebar.expandedItems));
/** Solo íconos (en el panel flotante, nunca). */
const compact = computed(() => props.sidebar.compact && !props.floating);
/** En `mini`, un ítem con hijos abre el panel flotante en lugar de navegar. */
const hasFlyout = (item: NavItem): boolean => compact.value && item.children.length > 0;

const contieneRuta = (item: NavItem, name: unknown): boolean => item.children.some((c) => c.route?.name === name || contieneRuta(c, name));

/** Un ítem que solo despliega: no navega y tiene hijos (un grupo, o uno cuya ruta ya no existe). */
const isGroup = (item: NavItem): boolean => !navTarget(item, router) && item.children.length > 0 && !compact.value;

/** Los grupos (y en `mini` los ítems con hijos) son botones; el resto, enlaces o texto. */
const linkTag = (item: NavItem) => (hasFlyout(item) ? "button" : navTarget(item, router) ? RouterLink : isGroup(item) ? "button" : "span");

function onClick(e: MouseEvent, item: NavItem) {
  if (hasFlyout(item)) abrir(e.currentTarget as HTMLElement, item);
  else if (isGroup(item)) toggle(item.id);
}

function titleOf(item: NavItem): string {
  if (navTarget(item, router)) return item.label;
  if (!item.route) return item.children.length ? item.label : `${item.label} (grupo vacío)`;
  return `${item.label} (ruta no navegable)`;
}

/** Despliega los ítems que contienen la ruta actual (al montar y al navegar). */
watch(
  () => route.name,
  (name) => {
    const padres = props.items.filter((i) => contieneRuta(i, name) && !expanded.value.has(i.id));
    if (padres.length) props.sidebar.expandItems(padres.map((i) => i.id));
  },
  { immediate: true },
);

// ---- Panel flotante del modo `mini` ----------------------------------------------

const abierto = shallowRef<NavItem | null>(null);
const flyoutEl = ref<HTMLElement | null>(null);
const flyoutStyle = ref<Record<string, string>>({});
let disparador: HTMLElement | null = null;

/** Junto al ítem, del lado de afuera de la barra; si no cabe hacia abajo, crece hacia arriba. */
function abrir(el: HTMLElement, item: NavItem) {
  if (abierto.value?.id === item.id) return cerrar();
  const r = el.getBoundingClientRect();
  const lado: Record<string, string> = props.sidebar.side === "left" ? { left: `${r.right + 6}px` } : { right: `${window.innerWidth - r.left + 6}px` };
  const abajo = r.top < window.innerHeight * 0.6;
  flyoutStyle.value = abajo ? { ...lado, top: `${r.top}px`, maxHeight: `${window.innerHeight - r.top - 12}px` } : { ...lado, bottom: `${window.innerHeight - r.bottom}px`, maxHeight: `${r.bottom - 12}px` };
  disparador = el;
  abierto.value = item;
  void nextTick(() => flyoutEl.value?.querySelector<HTMLElement>("a, button")?.focus({ preventScroll: true }));
}

function cerrar(devolverFoco = false) {
  if (!abierto.value) return;
  abierto.value = null;
  if (devolverFoco) disparador?.focus();
}

function fuera(e: PointerEvent) {
  const t = e.target as Node;
  if (abierto.value && !flyoutEl.value?.contains(t) && !disparador?.contains(t)) cerrar();
}
const alRedimensionar = () => cerrar();
watch(abierto, (item) => {
  if (item) {
    document.addEventListener("pointerdown", fuera, true);
    window.addEventListener("resize", alRedimensionar);
  } else {
    document.removeEventListener("pointerdown", fuera, true);
    window.removeEventListener("resize", alRedimensionar);
  }
});
watch(compact, () => cerrar());
// Al navegar desde el panel, se cierra.
watch(
  () => route.fullPath,
  () => cerrar(),
);
onBeforeUnmount(() => {
  document.removeEventListener("pointerdown", fuera, true);
  window.removeEventListener("resize", alRedimensionar);
});

/** Submenú: se despliega de alto 0 a su altura natural y sus ítems entran escalonados. */
function onEnter(el: Element, done: () => void) {
  // Al desplegarse el panel desde `mini` el alto lo anima el pliegue (`sidebarMotion.ts`).
  if (props.sidebar.morphing && !props.floating) return done();
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
  // Al llegar a `mini` el pliegue ya lo dejó en alto 0.
  if (props.sidebar.morphing && !props.floating) return done();
  gsap.to(el, {
    height: 0,
    opacity: 0,
    overflow: "hidden",
    duration: 0.22,
    ease: "power2.in",
    onComplete: done,
  });
}

const toggle = (id: number) => props.sidebar.toggleItem(id);
</script>

<style scoped>
.sidebar-menu {
  background-color: white;
  & > * {
    background-color: var(--bg2);
  }
}
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
  overflow: hidden;
  margin-inline-start: calc(0.5rem + var(--depth, 0) * 0.9rem);
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
/* `mini`: un ítem con hijos lleva una marca en la esquina y se resalta mientras su panel está abierto. */
.menu-link--flyout {
  position: relative;
  cursor: pointer;
}
.menu-flyout-mark {
  position: absolute;
  right: 0.4rem;
  bottom: 0.55rem;
  width: 0;
  height: 0;
  border-left: 5px solid transparent;
  border-bottom: 5px solid var(--p-surface-400);
}
.menu-link--flyout.is-open,
.menu-link--flyout.contiene-ruta {
  background-color: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
.menu-link--flyout.is-open .menu-flyout-mark,
.menu-link--flyout.contiene-ruta .menu-flyout-mark {
  border-bottom-color: var(--p-primary-color);
}
.menu-link--flyout.is-open .menu-text {
  opacity: 0 !important;
}
.submenu-toggle-icon {
  position: absolute;
  left: 23px;
}
</style>

<!-- El panel flotante va a `body` (Teleport): sus estilos no pueden ser `scoped`. -->
<style>
.nav-flyout {
  position: fixed;
  z-index: 1000;
  display: flex;
  min-width: 13rem;
  max-width: 18rem;
  flex-direction: column;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  background: var(--p-content-background);
  padding-block: 0.25rem;
  box-shadow:
    0 12px 32px -8px rgb(0 0 0 / 0.25),
    0 2px 6px rgb(0 0 0 / 0.08);
}
.nav-flyout .menu-link {
  padding-block: 9px;
}
.nav-flyout .menu-text {
  margin-left: 10px;
}
.nav-flyout__head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
  border-bottom: 1px solid var(--p-content-border-color);
  padding: 0.5rem 0.875rem 0.625rem;
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-decoration: none;
  text-transform: uppercase;
}
a.nav-flyout__head:hover {
  color: var(--p-primary-color);
}
.nav-flyout-enter-active,
.nav-flyout-leave-active {
  transition:
    opacity 0.16s ease,
    transform 0.16s ease;
}
.nav-flyout-enter-from,
.nav-flyout-leave-to {
  opacity: 0;
}
.nav-flyout--left.nav-flyout-enter-from,
.nav-flyout--left.nav-flyout-leave-to {
  transform: translateX(-6px) scale(0.98);
}
.nav-flyout--right.nav-flyout-enter-from,
.nav-flyout--right.nav-flyout-leave-to {
  transform: translateX(6px) scale(0.98);
}
@media (prefers-reduced-motion: reduce) {
  .nav-flyout-enter-active,
  .nav-flyout-leave-active {
    transition: none;
  }
}
</style>
