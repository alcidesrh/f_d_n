<!--
  Distribución de menús por área del shell: qué menús se despliegan en cada
  slot `menu-content` (barras laterales y cabecera) y en qué orden. Cada
  menú además solo se muestra a los usuarios cuyos roles lo permiten.
-->
<template>
  <div class="@container">
    <div class="grid grid-cols-1 gap-3 @4xl:grid-cols-3">
      <div v-for="area in LAYOUT_AREAS" :key="area" class="card flex flex-col gap-3 p-3">
        <div class="flex items-center gap-2">
          <icon :name="AREA_ICONS[area]" lg />
          <h3 class="font-semibold">{{ AREA_LABELS[area] }}</h3>
          <span class="ml-auto text-xs text-muted-color"
            >{{ store.layout[area].length }} menú(s)</span
          >
        </div>

        <SortablePanelList
          :model-value="rowsOf(area)"
          :row-height="ROW_HEIGHT"
          :gap="6"
          @update:model-value="
            (rows) =>
              store.setArea(
                area,
                rows.map((row) => row.id),
              )
          "
        >
          <template #row="{ row, index }">
            <div class="area-row">
              <span data-drag-handle class="flex cursor-grab text-muted-color"
                ><icon name="grip-vertical"
              /></span>
              <span class="w-5 text-xs text-muted-color">{{ index + 1 }}</span>
              <div class="flex min-w-0 flex-1 flex-col leading-tight">
                <span class="truncate font-medium">{{ row.nombre }}</span>
                <span class="truncate text-xs text-muted-color">{{
                  row.roles || 'Sin roles: nadie lo ve'
                }}</span>
              </div>
              <button
                type="button"
                class="area-btn"
                title="Quitar del área"
                @click="remove(area, row.id)"
              >
                <icon name="x" />
              </button>
            </div>
          </template>
        </SortablePanelList>
        <p v-if="!store.layout[area].length" class="text-center text-sm text-muted-color">
          Ningún menú en esta área
        </p>

        <Select
          :model-value="null"
          :options="available(area)"
          option-label="nombre"
          option-value="id"
          placeholder="Agregar menú…"
          size="small"
          :disabled="!available(area).length"
          @update:model-value="(id: number) => store.setArea(area, [...store.layout[area], id])"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { AREA_LABELS, LAYOUT_AREAS, type LayoutArea } from '@/core/navigation/userMenus'
import { numericId } from './api'
import { useMenuBuilderStore } from './store'

const ROW_HEIGHT = 56
const AREA_ICONS: Record<LayoutArea, string> = {
  sidebar_left: 'layout-sidebar',
  topbar_right: 'layout-navbar',
  sidebar_right: 'layout-sidebar-right',
}

const store = useMenuBuilderStore()

function rowsOf(area: LayoutArea) {
  return store.layout[area].map((id) => {
    const menu = store.menusById.get(id)
    return {
      key: `menu-${id}`,
      id,
      nombre: menu?.nombre ?? `#${id}`,
      roles: (menu?.roles ?? []).map((role) => role.nombre).join(', '),
    }
  })
}

function available(area: LayoutArea) {
  const used = new Set(store.layout[area])
  return store.menus
    .map((menu) => ({ id: numericId(menu.id), nombre: menu.nombre }))
    .filter((menu) => !used.has(menu.id))
}

function remove(area: LayoutArea, id: number) {
  store.setArea(
    area,
    store.layout[area].filter((other) => other !== id),
  )
}
</script>

<style scoped>
.area-row {
  display: flex;
  height: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background-color: var(--p-content-background);
}
.area-btn {
  display: flex;
  padding: 0.3rem;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.area-btn:hover {
  background-color: var(--p-content-hover-background);
}
</style>
