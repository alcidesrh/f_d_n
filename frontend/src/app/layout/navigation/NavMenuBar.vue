<!--
  Menús de la cabecera: cada raíz es un botón; si tiene hijos abre un
  `TieredMenu` con el resto del árbol (los grupos también navegan: su propia
  ruta es la primera entrada del submenú).
-->
<template>
  <div class="nav-menubar">
    <template v-for="menu in menus" :key="menu.id">
      <template v-for="item in menu.items" :key="item.id">
        <button
          type="button"
          class="icon-btn nav-menubar-btn"
          :class="{ 'nav-disabled': !item.children.length && !navTarget(item, router) }"
          :title="item.label"
          @click="onRoot($event, item)"
        >
          <icon :name="item.icon ?? 'point'" />
          <span class="nav-menubar-text">{{ item.label }}</span>
          <icon v-if="item.children.length" name="chevron-down" size=".8rem" />
        </button>
      </template>
    </template>
    <TieredMenu ref="popup" :model="popupModel" popup>
      <template #item="{ item, props, hasSubmenu }">
        <a v-bind="props.action" :class="{ 'nav-disabled': item.disabled }">
          <icon :name="String(item.iconName ?? 'point')" />
          <span class="ml-2">{{ item.label }}</span>
          <icon v-if="hasSubmenu" name="chevron-right" class="ml-auto" size=".8rem" />
        </a>
      </template>
    </TieredMenu>
  </div>
</template>

<script setup lang="ts">
import type { MenuItem } from 'primevue/menuitem'
import type TieredMenu from 'primevue/tieredmenu'
import type { NavItem, NavMenu } from '@/core/navigation/userMenus'
import { navTarget } from './navTarget'

defineProps<{ menus: NavMenu[] }>()

const router = useRouter()
const popup = ref<InstanceType<typeof TieredMenu> | null>(null)
const popupModel = ref<MenuItem[]>([])

function entry(item: NavItem): MenuItem {
  const target = navTarget(item, router)
  return {
    key: String(item.id),
    label: item.label,
    iconName: item.icon,
    disabled: !target,
    command: target ? () => router.push(target) : undefined,
  }
}

/** Submenú de un ítem: él mismo (si navega) y luego sus hijos, recursivamente. */
function submenu(item: NavItem): MenuItem[] {
  const self = navTarget(item, router) ? [entry(item), { separator: true }] : []
  return [
    ...self,
    ...item.children.map((child) =>
      child.children.length
        ? { ...entry(child), command: undefined, disabled: false, items: submenu(child) }
        : entry(child),
    ),
  ]
}

function onRoot(event: MouseEvent, item: NavItem) {
  if (item.children.length) {
    popupModel.value = submenu(item)
    popup.value?.toggle(event)
    return
  }
  const target = navTarget(item, router)
  if (target) void router.push(target)
}
</script>

<style scoped>
.nav-menubar {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.nav-menubar-btn {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  width: auto;
  padding-inline: 0.5rem;
}
.nav-menubar-text {
  font-size: 0.875rem;
  white-space: nowrap;
}
.nav-disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
