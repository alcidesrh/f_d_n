<template>
  <div class="list-toolbar">
    <!-- Con filas elegidas: qué hacer con ellas. Si no, lo que ponga el listado (filtros aplicados). -->
    <div v-if="selectionMode && selectedCount > 0" class="list-toolbar__selection" role="region" aria-label="Selección">
      <span class="whitespace-nowrap text-sm font-medium">{{ selectedCount }} {{ selectedCount === 1 ? 'seleccionado' : 'seleccionados' }}</span>
      <span class="flex flex-wrap items-center gap-1">
        <Button v-for="accion in acciones" :key="accion.key" size="small" text :severity="accion.severity ?? 'primary'" :aria-label="accion.label" v-tooltip.bottom="accion.label" @click="emit('accion', accion)">
          <icon :name="accion.icon" size="1.15rem" :class="accion.severity === 'danger' ? 'text-red-500' : 'text-primary'" />
          <span class="hidden md:inline">{{ accion.label }}</span>
        </Button>
        <Button v-if="features.share" size="small" text aria-label="Enviar por chat" v-tooltip.bottom="'Enviar por chat'" @click="emit('share')">
          <icon name="forum-outline" size="1.15rem" class="text-primary" />
          <span class="hidden md:inline">Enviar por chat</span>
        </Button>
        <button type="button" class="list-toolbar__btn tap-target" aria-label="Limpiar selección" v-tooltip.bottom="'Limpiar selección'" @click="emit('clear-selection')">
          <icon name="close" size="1.1rem" />
        </button>
      </span>
    </div>
    <div v-else class="list-toolbar__start">
      <slot name="start" />
    </div>

    <div class="list-toolbar__controls" role="toolbar" aria-label="Controles del listado">
      <button v-if="features.filter" type="button" class="list-toolbar__btn tap-target" :class="{ 'is-on': view.filtersOpen }" :aria-pressed="view.filtersOpen" aria-label="Filtros" v-tooltip.bottom="'Filtros'" @click="emit('toggle-filters')">
        <OverlayBadge v-if="activeFilters > 0" :value="String(activeFilters)" size="small">
          <icon name="filter-alt-outline" size="1.25rem" />
        </OverlayBadge>
        <icon v-else name="filter-alt-outline" size="1.25rem" />
      </button>

      <button v-if="features.columns" type="button" class="list-toolbar__btn tap-target" aria-label="Columnas" v-tooltip.bottom="'Columnas'" @click="columnsPopover?.toggle($event)">
        <OverlayBadge v-if="hiddenCount > 0" :value="String(hiddenCount)" severity="secondary" size="small">
          <icon name="view-column-outline" size="1.25rem" />
        </OverlayBadge>
        <icon v-else name="view-column-outline" size="1.25rem" />
      </button>
      <Popover ref="columnsPopover">
        <div class="flex w-64 max-w-[80vw] flex-col gap-1">
          <div class="flex items-center justify-between px-1 pb-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">Columnas</span>
            <Button v-if="hiddenCount > 0" label="Mostrar todas" size="small" text @click="emit('show-all-columns')" />
          </div>
          <div class="max-h-[50vh] overflow-y-auto">
            <label v-for="column in columns" :key="column.field" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-100">
              <Checkbox binary :model-value="column.visible !== false" :disabled="column.visible !== false && visibleCount <= 1" @update:model-value="emit('column-visible', column.field, Boolean($event))" />
              <span class="truncate capitalize">{{ column.label ?? column.field }}</span>
            </label>
          </div>
        </div>
      </Popover>

      <button v-if="features.view" type="button" class="list-toolbar__btn tap-target" aria-label="Vista" v-tooltip.bottom="'Vista'" @click="viewPopover?.toggle($event)">
        <icon :name="DENSITY_ICON[view.density]" size="1.25rem" />
      </button>
      <Popover ref="viewPopover">
        <div class="flex flex-col gap-3 p-1">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">Densidad</span>
            <SelectButton :model-value="view.density" :options="DENSITIES" option-label="label" option-value="value" :allow-empty="false" size="small" @update:model-value="emit('density', $event)" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">Disposición</span>
            <SelectButton :model-value="view.layout" :options="LAYOUTS" option-label="label" option-value="value" :allow-empty="false" size="small" @update:model-value="emit('layout', $event)" />
          </div>
        </div>
      </Popover>

      <button v-if="features.selection" type="button" class="list-toolbar__btn tap-target" :class="{ 'is-on': selectionMode }" :aria-pressed="selectionMode" aria-label="Modo selección" v-tooltip.bottom="'Seleccionar'" @click="emit('toggle-selection')">
        <icon :name="selectionMode ? 'check-box' : 'check-box-outline'" size="1.25rem" />
      </button>

      <span v-if="features.maximize || configurable || features.reset" class="list-toolbar__sep" aria-hidden="true" />

      <button v-if="features.maximize" type="button" class="list-toolbar__btn tap-target" :aria-label="maximized ? 'Restaurar tamaño' : 'Maximizar'" v-tooltip.bottom="maximized ? 'Restaurar (Esc)' : 'Maximizar'" @click="emit('maximize')">
        <icon :name="maximized ? 'fullscreen-exit' : 'fullscreen'" size="1.3rem" />
      </button>
      <button v-if="configurable" type="button" class="list-toolbar__btn tap-target" aria-label="Configurar entidad" v-tooltip.bottom="'Configurar'" @click="emit('configure')">
        <icon name="settings-outline" size="1.25rem" />
      </button>
      <button v-if="features.reset" type="button" class="list-toolbar__btn tap-target" aria-label="Restablecer vista" v-tooltip.bottom="'Restablecer vista'" @click="emit('reset')">
        <icon name="ink-eraser-outline" size="1.25rem" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Popover as PopoverType } from 'primevue'
import type { CollectionFieldConfig, ListDensity, ListLayout, ListViewState } from '@/core/entities/types'
import type { EntityBulkAction } from './listActions'
import type { ListFeatures } from './listFeatures'

const DENSITIES: Array<{ label: string; value: ListDensity }> = [
  { label: 'Compacta', value: 'compact' },
  { label: 'Normal', value: 'normal' },
  { label: 'Amplia', value: 'comfortable' },
]
const LAYOUTS: Array<{ label: string; value: ListLayout }> = [
  { label: 'Auto', value: 'auto' },
  { label: 'Tabla', value: 'table' },
  { label: 'Tarjetas', value: 'cards' },
]
const DENSITY_ICON: Record<ListDensity, string> = { compact: 'density-small', normal: 'density-medium', comfortable: 'density-large' }

const props = withDefaults(
  defineProps<{
    features: ListFeatures
    /** Todas las columnas (visibles y ocultas), en orden. */
    columns: CollectionFieldConfig[]
    view: ListViewState
    activeFilters?: number
    selectionMode: boolean
    selectedCount: number
    /** Acciones sobre la selección (eliminar y las de la entidad), ya filtradas por permiso. */
    acciones?: EntityBulkAction[]
    maximized?: boolean
    /** Muestra la opción que reemplaza el listado por la configuración de la entidad. */
    configurable?: boolean
  }>(),
  { activeFilters: 0, acciones: () => [], maximized: false, configurable: false },
)

const emit = defineEmits<{
  'toggle-selection': []
  'clear-selection': []
  /** Enviar los seleccionados por el chat interno. */
  share: []
  /** Una acción sobre la selección. */
  accion: [accion: EntityBulkAction]
  'column-visible': [field: string, visible: boolean]
  'show-all-columns': []
  'toggle-filters': []
  density: [density: ListDensity]
  layout: [layout: ListLayout]
  maximize: []
  reset: []
  configure: []
}>()

const columnsPopover = ref<InstanceType<typeof PopoverType> | null>(null)
const viewPopover = ref<InstanceType<typeof PopoverType> | null>(null)

const hiddenCount = computed(() => props.columns.filter((c) => c.visible === false).length)
const visibleCount = computed(() => props.columns.length - hiddenCount.value)
</script>

<style scoped>
.list-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  min-height: 2.75rem;
  padding: 0.25rem 0.25rem 0.5rem;
}
.list-toolbar__start,
.list-toolbar__selection {
  display: flex;
  min-width: 0;
  flex: 1 1 18rem;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}
.list-toolbar__selection {
  padding: 0.125rem 0.25rem 0.125rem 0.75rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-primary-color) 9%, transparent);
  color: var(--p-primary-color);
}
.list-toolbar__controls {
  display: flex;
  margin-inline-start: auto;
  align-items: center;
  gap: 0.25rem;
}
.list-toolbar__btn {
  display: inline-grid;
  width: 2.25rem;
  height: 2.25rem;
  place-items: center;
  border-radius: 0.5rem;
  color: var(--p-text-muted-color);
  cursor: pointer;
  transition:
    background-color 0.15s,
    color 0.15s;
}
.list-toolbar__btn:hover {
  background: color-mix(in srgb, var(--p-surface-500) 12%, transparent);
  color: var(--p-text-color);
}
.list-toolbar__btn.is-on {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
  color: var(--p-primary-color);
}
.list-toolbar__btn.is-on :deep(span) {
  color: var(--p-primary-color);
}
.list-toolbar__sep {
  width: 1px;
  height: 1.25rem;
  margin-inline: 0.25rem;
  background: var(--p-content-border-color);
}
</style>
