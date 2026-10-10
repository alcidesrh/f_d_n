<!--
  Grid de datos sobre CSS Grid (ADR-029): reemplaza al `<table>` del
  DataTable de PrimeVue en los listados.

  - Todas las filas comparten `grid-template-columns` y el ancho del cuerpo
    (`layout.ts`): las columnas quedan alineadas sin medir contenido y el alto
    de fila es fijo (densidad). Lo que no cabe se corta con elipsis.
  - Cabecera fija arriba; selección fija a la izquierda y acciones a la
    derecha (`position: sticky`) con scroll horizontal en el medio.
  - Columnas: ordenar (clic en el título), filtrar (embudo: activa el input
    de esa columna en la fila de filtros; lo resuelve el padre), ocultar, reordenar (GSAP, desde el tirador) y redimensionar (borde
    derecho; doble clic vuelve al ancho configurado).
  - Por debajo de `cardsBelow` rem de ancho del contenedor (o con
    `layout="cards"`) cada fila es una tarjeta del mismo alto con las primeras
    `cardFields` columnas.

  No sabe de entidades ni de APIs: emite y deja los slots `cell`, `editor`,
  `filter`, `actions` y `empty`.
-->
<template>
  <div ref="root" class="dg" :class="[`dg--${density}`, { 'dg--cards': cards, 'dg--loading': loading }]" :style="rootVars">
    <div v-if="loading" class="dg-progress" role="progressbar" aria-label="Cargando" />

    <!-- Tabla -->
    <div v-if="!cards" class="dg-scroll" data-grid-scroll role="grid" :aria-rowcount="rows.length + 1" :aria-colcount="columns.length" :aria-busy="loading">
      <div class="dg-body" :style="{ minWidth: template.minWidth }">
        <div ref="headerRow" class="dg-row dg-head" role="row">
          <div v-if="selectable" class="dg-cell dg-sel dg-sticky-l" role="columnheader">
            <Checkbox binary :model-value="pageState === 'all'" :indeterminate="pageState === 'some'" :disabled="!rows.length" aria-label="Seleccionar la página" @update:model-value="emit('toggle-page', Boolean($event))" />
          </div>
          <div
            v-for="column in columns"
            :key="column.key"
            class="dg-cell dg-hcell"
            :class="[alignClass(column), { 'is-sorted': column.sort, 'is-filtered': column.filtered }]"
            :data-grid-col="column.key"
            role="columnheader"
            :aria-sort="column.sort === 'asc' ? 'ascending' : column.sort === 'desc' ? 'descending' : undefined"
          >
            <span v-if="reorderable" data-grid-grip class="dg-grip" title="Arrastrar para mover la columna">
              <icon name="drag-indicator" size="1rem" />
            </span>
            <button v-if="column.sortable" type="button" class="dg-hlabel dg-sortable" :aria-label="`Ordenar por ${column.label}`" @click="emit('sort', column.key)">
              <span class="truncate">{{ column.label }}</span>
              <icon :name="SORT_ICONS[column.sort ?? 'none']" size="0.95rem" class="dg-sort-icon" />
            </button>
            <span v-else class="dg-hlabel truncate" :title="column.label">{{ column.label }}</span>
            <span class="dg-htools">
              <button v-if="column.filterable" type="button" class="dg-tool tap-target" :class="{ 'is-active': column.filterOpen }" :aria-pressed="Boolean(column.filterOpen)" :aria-label="column.filterOpen ? `Quitar filtro ${column.label}` : `Filtrar ${column.label}`" v-tooltip.top="column.filterOpen ? 'Quitar filtro' : 'Filtrar'" @click="emit('filter', column.key)">
                <icon :name="column.filterOpen ? 'filter-alt' : 'filter-alt-outline'" size="1rem" />
              </button>
              <button v-if="hideable" type="button" class="dg-tool tap-target" :aria-label="`Ocultar columna ${column.label}`" @click="emit('hide', column.key)">
                <icon name="visibility-off-outline" size="1rem" />
              </button>
            </span>
            <span v-if="resizable" class="dg-resizer" title="Arrastrar para cambiar el ancho (doble clic: ancho original)" @pointerdown="resize.start($event, column.key)" @dblclick.stop="resize.reset(column.key)" />
          </div>
          <div v-if="template.filler" class="dg-cell dg-filler" aria-hidden="true" />
          <div v-if="actions" class="dg-cell dg-actions dg-sticky-r" role="columnheader" aria-label="Acciones" />
        </div>

        <div v-if="filterRow" class="dg-row dg-filters" role="row">
          <div v-if="selectable" class="dg-cell dg-sel dg-sticky-l" />
          <div v-for="column in columns" :key="column.key" class="dg-cell" :data-grid-filter="column.key">
            <slot v-if="column.filterable && column.filterOpen" name="filter" :column="column" />
          </div>
          <div v-if="template.filler" class="dg-cell dg-filler" />
          <div v-if="actions" class="dg-cell dg-actions dg-sticky-r" />
        </div>

        <template v-if="rows.length">
          <div
            v-for="(row, index) in rows"
            :key="rowKey(row)"
            class="dg-row dg-brow"
            :class="{ 'is-selected': isSelected(row), 'is-clickable': rowClickable }"
            role="row"
            :aria-rowindex="index + 2"
            :aria-selected="selectable ? isSelected(row) : undefined"
            @click="emit('row-click', row)"
          >
            <div v-if="selectable" class="dg-cell dg-sel dg-sticky-l" role="gridcell" @click.stop>
              <Checkbox binary :model-value="isSelected(row)" :aria-label="`Seleccionar fila ${index + 1}`" @update:model-value="emit('toggle-row', row, Boolean($event))" />
            </div>
            <div
              v-for="column in columns"
              :key="column.key"
              class="dg-cell dg-dcell"
              :class="[alignClass(column), { 'is-editable': column.editable && !isEditing(row, column), 'is-editing': isEditing(row, column) }]"
              role="gridcell"
              :tabindex="column.editable && !isEditing(row, column) ? 0 : undefined"
              @click="onCellClick(row, column, $event)"
              @keydown.enter.self.prevent="onCellClick(row, column, $event)"
            >
              <slot v-if="isEditing(row, column)" name="editor" :row="row" :column="column" />
              <slot v-else name="cell" :row="row" :column="column">
                <GridText :text="String((row as Record<string, unknown>)?.[column.key] ?? '')" />
              </slot>
            </div>
            <div v-if="template.filler" class="dg-cell dg-filler" />
            <div v-if="actions" class="dg-cell dg-actions dg-sticky-r" role="gridcell" @click.stop>
              <slot name="actions" :row="row" />
            </div>
          </div>
        </template>
        <template v-else-if="loading">
          <div v-for="n in skeletonRows" :key="`sk-${n}`" class="dg-row dg-brow dg-skeleton" aria-hidden="true">
            <div v-if="selectable" class="dg-cell dg-sel dg-sticky-l" />
            <div v-for="column in columns" :key="column.key" class="dg-cell"><span class="dg-bone" /></div>
            <div v-if="template.filler" class="dg-cell dg-filler" />
            <div v-if="actions" class="dg-cell dg-actions dg-sticky-r" />
          </div>
        </template>
      </div>
      <div v-if="!rows.length && !loading" class="dg-empty">
        <slot name="empty">
          <icon name="inbox-outline" size="2rem" />
          <span>Sin registros</span>
        </slot>
      </div>
    </div>

    <!-- Tarjetas -->
    <div v-else class="dg-cards-wrap">
      <div v-if="selectable" class="dg-cards-head">
        <Checkbox binary :input-id="`${uid}-page`" :model-value="pageState === 'all'" :indeterminate="pageState === 'some'" :disabled="!rows.length" @update:model-value="emit('toggle-page', Boolean($event))" />
        <label :for="`${uid}-page`" class="text-sm text-surface-500">Seleccionar la página</label>
      </div>
      <div v-if="rows.length" class="dg-cards" role="list">
        <article
          v-for="row in rows"
          :key="rowKey(row)"
          class="dg-card"
          :class="{ 'is-selected': isSelected(row), 'is-clickable': rowClickable }"
          role="listitem"
          @click="emit('row-click', row)"
        >
          <header class="dg-card-head">
            <span v-if="selectable" class="dg-card-check" @click.stop>
              <Checkbox binary :model-value="isSelected(row)" aria-label="Seleccionar" @update:model-value="emit('toggle-row', row, Boolean($event))" />
            </span>
            <div v-if="cardColumns[0]" class="dg-card-title" :class="{ 'is-editable': cardColumns[0].editable }" @click="onCellClick(row, cardColumns[0], $event)">
              <slot v-if="isEditing(row, cardColumns[0])" name="editor" :row="row" :column="cardColumns[0]" />
              <slot v-else name="cell" :row="row" :column="cardColumns[0]" />
            </div>
            <span v-if="actions" class="dg-card-actions" @click.stop>
              <slot name="actions" :row="row" />
            </span>
          </header>
          <dl class="dg-card-fields">
            <div v-for="column in cardColumns.slice(1)" :key="column.key" class="dg-card-field">
              <dt class="truncate">{{ column.label }}</dt>
              <dd :class="{ 'is-editable': column.editable && !isEditing(row, column) }" @click="onCellClick(row, column, $event)">
                <slot v-if="isEditing(row, column)" name="editor" :row="row" :column="column" />
                <slot v-else name="cell" :row="row" :column="column" />
              </dd>
            </div>
          </dl>
        </article>
      </div>
      <div v-else-if="!loading" class="dg-empty">
        <slot name="empty">
          <icon name="inbox-outline" size="2rem" />
          <span>Sin registros</span>
        </slot>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue'
import GridText from './GridText.vue'
import { ROW_HEIGHT, gridTemplate } from './layout'
import type { GridColumn, GridDensity, GridEditing, GridLayout } from './types'
import { useColumnDrag } from './useColumnDrag'
import { useColumnResize } from './useColumnResize'
import { useElementWidth } from './useElementWidth'

defineOptions({ name: 'DataGrid' })

const SORT_ICONS = { asc: 'arrow-upward', desc: 'arrow-downward', none: 'swap-vert' } as const

const props = withDefaults(
  defineProps<{
    columns: GridColumn[]
    rows: unknown[]
    rowKey: (row: unknown) => string
    loading?: boolean
    density?: GridDensity
    layout?: GridLayout
    /** Ancho del contenedor (rem) por debajo del cual `auto` pasa a tarjetas (`md`). */
    cardsBelow?: number
    /** Columnas que muestra cada tarjeta (la primera es el título). */
    cardFields?: number
    /** Columna de selección con casillas. */
    selectable?: boolean
    selectedKeys?: ReadonlySet<string>
    /** Columna de acciones fija a la derecha, con su ancho. */
    actions?: string | null
    reorderable?: boolean
    resizable?: boolean
    hideable?: boolean
    editing?: GridEditing | null
    /** La fila entera responde al clic (p. ej. en modo selección). */
    rowClickable?: boolean
    /** Alto del área con scroll (CSS); por defecto crece con las filas. */
    maxHeight?: string
  }>(),
  {
    loading: false,
    density: 'normal',
    layout: 'auto',
    cardsBelow: 48,
    cardFields: 4,
    selectable: false,
    selectedKeys: () => new Set<string>(),
    actions: null,
    reorderable: false,
    resizable: false,
    hideable: false,
    editing: null,
    rowClickable: false,
    maxHeight: undefined,
  },
)

const emit = defineEmits<{
  sort: [key: string]
  filter: [key: string]
  hide: [key: string]
  reorder: [from: number, to: number]
  resize: [key: string, width: string | null]
  'row-click': [row: unknown]
  'cell-click': [row: unknown, column: GridColumn]
  'toggle-row': [row: unknown, selected: boolean]
  'toggle-page': [selected: boolean]
  /** Cambió la disposición efectiva (con `auto`, según el ancho). */
  mode: [mode: 'table' | 'cards']
}>()

const uid = useId()
const root = ref<HTMLElement | null>(null)
const headerRow = ref<HTMLElement | null>(null)
const width = useElementWidth(root)

/** px por rem del documento (para comparar con `cardsBelow`). */
const remPx = () => (typeof document === 'undefined' ? 16 : Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16)

const cards = computed(() => props.layout === 'cards' || (props.layout === 'auto' && width.value > 0 && width.value < props.cardsBelow * remPx()))
const cardColumns = computed(() => props.columns.slice(0, Math.max(1, props.cardFields)))
watch(cards, (value) => emit('mode', value ? 'cards' : 'table'), { immediate: true })

const resize = useColumnResize((key, value) => emit('resize', key, value))

const template = computed(() =>
  gridTemplate(props.columns, {
    selection: props.selectable ? '3rem' : null,
    actions: props.actions,
    live: resize.live,
  }),
)

const rootVars = computed(() => ({
  '--dg-cols': template.value.columns,
  '--dg-row-h': ROW_HEIGHT[props.density],
  '--dg-card-lines': String(Math.max(0, cardColumns.value.length - 1)),
  ...(props.maxHeight ? { '--dg-max-h': props.maxHeight } : {}),
}))

const skeletonRows = 6

const pageState = computed<'none' | 'some' | 'all'>(() => {
  const selected = props.rows.filter((row) => props.selectedKeys.has(props.rowKey(row))).length
  if (!selected) return 'none'
  return selected === props.rows.length ? 'all' : 'some'
})

/** Fila de filtros bajo la cabecera: si alguna columna tiene su filtro activado (slot `filter`). */
const filterRow = computed(() => props.columns.some((column) => column.filterable && column.filterOpen))

const isSelected = (row: unknown) => props.selectedKeys.has(props.rowKey(row))
const isEditing = (row: unknown, column: GridColumn) => props.editing?.column === column.key && props.editing.row === props.rowKey(row)
const alignClass = (column: GridColumn) => (column.align && column.align !== 'start' ? `is-${column.align}` : '')

function onCellClick(row: unknown, column: GridColumn, event: Event) {
  if (isEditing(row, column)) return event.stopPropagation()
  if (column.editable && !props.rowClickable) {
    event.stopPropagation()
    emit('cell-click', row, column)
  }
}

useColumnDrag({
  header: headerRow,
  keys: () => (cards.value ? [] : props.columns.map((c) => c.key)),
  enabled: () => props.reorderable && !cards.value,
  onMove: (from, to) => emit('reorder', from, to),
})
</script>

<style scoped>
.dg {
  --dg-bg: var(--p-content-background);
  --dg-border: var(--p-content-border-color);
  --dg-head-bg: color-mix(in srgb, var(--p-surface-500) 6%, var(--dg-bg));
  --dg-hover: color-mix(in srgb, var(--p-surface-500) 7%, var(--dg-bg));
  --dg-selected: color-mix(in srgb, var(--p-primary-color) 11%, var(--dg-bg));
  --dg-pad-x: 0.75rem;
  position: relative;
  display: flex;
  min-height: 0;
  flex-direction: column;
  color: var(--p-text-color);
  font-size: 0.875rem;
}
.dg--compact {
  --dg-pad-x: 0.5rem;
  font-size: 0.8125rem;
}

/* Barra de carga: indeterminada, sin tapar los datos anteriores. */
.dg-progress {
  position: absolute;
  inset: 0 0 auto;
  z-index: 6;
  height: 2px;
  overflow: hidden;
  background: color-mix(in srgb, var(--p-primary-color) 15%, transparent);
}
.dg-progress::after {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 35%;
  background: var(--p-primary-color);
  animation: dg-progress 1.1s var(--ease) infinite;
}
@keyframes dg-progress {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(300%);
  }
}
.dg--loading .dg-brow:not(.dg-skeleton) {
  opacity: 0.55;
  transition: opacity 0.2s;
}

/* ---------- Tabla ---------- */
.dg-scroll {
  position: relative;
  min-height: 0;
  max-height: var(--dg-max-h, none);
  flex: 1 1 auto;
  overflow: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: thin;
}
.dg-body {
  position: relative;
  width: 100%;
}
.dg-row {
  display: grid;
  grid-template-columns: var(--dg-cols);
  width: 100%;
}
.dg-cell {
  position: relative;
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.25rem;
  padding-inline: var(--dg-pad-x);
  background: var(--dg-row-bg, var(--dg-bg));
  border-bottom: 1px solid var(--dg-border);
}
.dg-cell.is-end {
  justify-content: flex-end;
  text-align: end;
}
.dg-cell.is-center {
  justify-content: center;
  text-align: center;
}

.dg-head {
  position: sticky;
  top: 0;
  z-index: 3;
  --dg-row-bg: var(--dg-head-bg);
}
.dg-head .dg-cell {
  height: calc(var(--dg-row-h) + 0.25rem);
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.dg-hcell {
  padding-inline-start: 0.25rem;
  user-select: none;
}
.dg-hcell.is-sorted,
.dg-hcell.is-filtered {
  color: var(--p-text-color);
}
.dg-hlabel {
  display: inline-flex;
  min-width: 0;
  flex: 0 1 auto;
  align-items: center;
  gap: 0.25rem;
  padding-inline-start: 0.25rem;
}
.dg-sortable {
  cursor: pointer;
  border-radius: 0.25rem;
  font: inherit;
  letter-spacing: inherit;
  text-transform: inherit;
  color: inherit;
}
.dg-sortable:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
.dg-sort-icon {
  flex: none;
  opacity: 0.45;
  transition: opacity 0.15s;
}
.dg-sortable:hover .dg-sort-icon,
.dg-sortable:focus-visible .dg-sort-icon {
  opacity: 0.8;
}
.dg-hcell.is-sorted .dg-sort-icon {
  opacity: 1;
  color: var(--p-primary-color);
}
.dg-htools {
  display: inline-flex;
  margin-inline-start: auto;
  align-items: center;
  gap: 0.125rem;
}
.dg-tool {
  display: inline-grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.375rem;
  color: var(--p-text-muted-color);
  cursor: pointer;
  transition: background-color 0.15s;
}
.dg-tool:hover {
  background: color-mix(in srgb, var(--p-surface-500) 14%, transparent);
}
.dg-tool.is-active,
.dg-tool.is-active :deep(span) {
  color: var(--p-primary-color);
}
.dg-grip {
  display: inline-grid;
  flex: none;
  place-items: center;
  width: 1rem;
  height: 1.75rem;
  cursor: grab;
  opacity: 0.25;
  touch-action: none;
  transition: opacity 0.15s;
}
.dg-hcell:hover .dg-grip {
  opacity: 0.7;
}
/* Sin hover (táctil): el tirador también se ve. */
@media (hover: none) {
  .dg-grip {
    opacity: 0.5;
  }
}
.dg-hcell.is-dragging {
  z-index: 5;
  border-radius: 0.375rem;
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.16);
  color: var(--p-text-color);
}
.dg-head.is-reordering .dg-hcell:not(.is-dragging) {
  opacity: 0.85;
}
.dg-resizer {
  position: absolute;
  inset-block: 0.5rem;
  inset-inline-end: -1px;
  z-index: 1;
  width: 7px;
  cursor: col-resize;
  touch-action: none;
}
.dg-resizer::after {
  content: '';
  position: absolute;
  inset-block: 0;
  left: 3px;
  width: 1px;
  background: var(--dg-border);
  transition: background-color 0.15s;
}
.dg-resizer:hover::after {
  width: 2px;
  background: var(--p-primary-color);
}
:global(body.grid-resizing) {
  cursor: col-resize;
  user-select: none;
}

.dg-filters {
  position: sticky;
  top: calc(var(--dg-row-h) + 0.25rem);
  z-index: 3;
  --dg-row-bg: var(--dg-head-bg);
}
.dg-filters .dg-cell {
  height: 2.75rem;
  padding-inline: 0.375rem;
}

.dg-brow .dg-cell {
  height: var(--dg-row-h);
  transition: background-color 0.12s;
}
.dg-brow:hover {
  --dg-row-bg: var(--dg-hover);
}
.dg-brow.is-selected {
  --dg-row-bg: var(--dg-selected);
}
.dg-brow.is-clickable {
  cursor: pointer;
}
.dg-dcell.is-editable {
  cursor: text;
}
.dg-dcell.is-editable:hover {
  box-shadow: inset 0 -1px 0 var(--p-primary-color);
}
.dg-dcell:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: -2px;
}
.dg-dcell.is-editing {
  padding-inline: 0.25rem;
  overflow: visible;
  z-index: 1;
}

.dg-sticky-l {
  position: sticky;
  left: 0;
  z-index: 1;
  justify-content: center;
  padding-inline: 0;
}
.dg-sticky-r {
  position: sticky;
  right: 0;
  z-index: 1;
  justify-content: flex-end;
  padding-inline: 0.25rem 0.5rem;
  box-shadow: -10px 0 12px -12px rgb(0 0 0 / 0.35);
}
.dg-head .dg-sticky-l,
.dg-head .dg-sticky-r,
.dg-filters .dg-sticky-l,
.dg-filters .dg-sticky-r {
  z-index: 4;
}

.dg-bone {
  display: block;
  width: 70%;
  height: 0.6rem;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--p-surface-200), var(--p-surface-100), var(--p-surface-200));
  background-size: 200% 100%;
  animation: dg-bone 1.2s ease-in-out infinite;
}
@keyframes dg-bone {
  to {
    background-position: -200% 0;
  }
}

.dg-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}

/* ---------- Tarjetas ---------- */
.dg-cards-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-height: 0;
  max-height: var(--dg-max-h, none);
  overflow: auto;
}
.dg-cards-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-inline: 0.25rem;
}
.dg-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr));
  gap: 0.625rem;
}
.dg-card {
  --dg-line: 1.625rem;
  display: grid;
  height: calc(2.25rem + var(--dg-card-lines) * var(--dg-line) + 1.25rem);
  grid-template-rows: 2.25rem 1fr;
  overflow: hidden;
  padding: 0.5rem 0.75rem 0.75rem;
  border: 1px solid var(--dg-border);
  border-radius: 0.75rem;
  background: var(--dg-row-bg, var(--dg-bg));
  transition:
    background-color 0.15s,
    border-color 0.15s;
}
.dg--compact .dg-card {
  --dg-line: 1.375rem;
}
.dg--comfortable .dg-card {
  --dg-line: 1.875rem;
}
.dg-card.is-selected {
  --dg-row-bg: var(--dg-selected);
  border-color: color-mix(in srgb, var(--p-primary-color) 45%, transparent);
}
.dg-card.is-clickable {
  cursor: pointer;
}
.dg-card-head {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.5rem;
}
.dg-card-title {
  min-width: 0;
  flex: 1 1 auto;
  font-weight: 600;
}
.dg-card-actions {
  display: flex;
  flex: none;
  align-items: center;
}
.dg-card-fields {
  display: grid;
  align-content: start;
  grid-auto-rows: var(--dg-line);
  margin: 0;
}
.dg-card-field {
  display: grid;
  min-width: 0;
  align-items: center;
  grid-template-columns: minmax(5rem, 38%) minmax(0, 1fr);
  gap: 0.75rem;
}
.dg-card-field dt {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}
.dg-card-field dd {
  min-width: 0;
  margin: 0;
}
.dg-card .is-editable {
  cursor: text;
}
</style>
