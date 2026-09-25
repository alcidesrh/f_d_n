<!--
  Editor del árbol de un menú como esquema con sangría (ver `outline.ts`),
  con GSAP Draggable. Todos los nodos están "expandidos": cada fila es un
  ítem desplazado a la derecha según su profundidad.

  - Arrastrar el tirador mueve el bloque (el ítem y sus descendientes):
    la posición vertical elige la fila destino y la horizontal la sangría
    (un nivel cada `INDENT` px, ajustada a lo que el árbol admite).
  - Soltar un bloque fuera del contenedor lo quita del menú (vuelve a la
    paleta). Los botones ←/→ cambian el nivel sin arrastrar.
  - La paleta (`MenuItemPalette`) inserta ítems nuevos con la API expuesta
    `previewIncoming` / `commitIncoming` / `cancelIncoming`.

  Docs de GSAP Draggable: https://gsap.com/docs/v3/Plugins/Draggable/
-->
<template>
  <div
    ref="rootEl"
    class="mo"
    :class="{ 'mo--over': incoming !== null, 'mo--empty': !rows.length && incoming === null }"
    :style="{ height: `${Math.max(slots, 2) * ROW_H + PADDING * 2}px` }"
  >
    <div v-if="!rows.length && incoming === null" class="mo-empty">
      <icon name="drag-drop" lg />
      <span>Arrastrá ítems de la paleta hasta aquí</span>
    </div>

    <div
      v-if="placeholder"
      class="mo-placeholder"
      :style="{
        transform: `translate(${placeholder.depth * INDENT}px, ${placeholder.index * ROW_H}px)`,
        height: `${placeholder.size * ROW_H - GAP}px`,
        width: `calc(100% - ${PADDING * 2 + placeholder.depth * INDENT}px)`,
        top: `${PADDING}px`,
      }"
    />

    <div
      v-for="(row, index) in rows"
      :key="row.key"
      :ref="(el) => setRef(row.key, el)"
      class="mo-row"
      :class="{
        'is-dragging': dragKeys.has(row.key),
        'is-removing': removing && dragKeys.has(row.key),
      }"
      :data-key="row.key"
      :style="{ height: `${ROW_H - GAP}px`, top: `${PADDING}px` }"
    >
      <span class="mo-handle" data-drag-handle title="Arrastrar">
        <icon name="grip-vertical" />
      </span>
      <icon :name="itemsById.get(row.id)?.icon.icon ?? 'point'" class="text-primary" />
      <div class="mo-text">
        <span class="mo-label">{{ itemsById.get(row.id)?.nombre ?? `#${row.id}` }}</span>
        <span class="mo-route">{{ itemsById.get(row.id)?.route.path }}</span>
      </div>
      <div class="mo-actions">
        <button
          type="button"
          title="Subir un nivel"
          :disabled="row.depth === 0"
          @click="shift(index, -1)"
        >
          <icon name="arrow-bar-to-left" />
        </button>
        <button
          type="button"
          title="Bajar un nivel (hijo del anterior)"
          :disabled="!canIndent(index)"
          @click="shift(index, 1)"
        >
          <icon name="arrow-bar-to-right" />
        </button>
        <button
          type="button"
          title="Quitar del menú"
          @click="emit('update:rows', liftRow(rows, index))"
        >
          <icon name="x" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { Draggable } from 'gsap/Draggable'
import { nextTick } from 'vue'
import type { MenuItemDto } from './api'
import {
  clampDepth,
  depthRange,
  liftRow,
  moveBlock,
  placeBlock,
  rowKey,
  takeBlock,
  type OutlineRow,
} from './outline'

gsap.registerPlugin(Draggable)

defineOptions({ name: 'MenuOutline' })

const props = defineProps<{ rows: OutlineRow[]; itemsById: Map<number, MenuItemDto> }>()
const emit = defineEmits<{ 'update:rows': [rows: OutlineRow[]] }>()

/** Alto de fila (incluye `GAP`), sangría por nivel y márgenes, en px. */
const ROW_H = 52
const GAP = 6
const INDENT = 32
const PADDING = 8
/** Margen alrededor del contenedor dentro del cual soltar no quita el ítem. */
const KEEP_MARGIN = 24

const rootEl = ref<HTMLElement | null>(null)
const els = new Map<string, HTMLElement>()
const drags = new Map<string, Draggable>()

/** Arrastre en curso de un bloque del propio esquema. */
let drag: { from: number; block: OutlineRow[]; rest: OutlineRow[] } | null = null
const dragKeys = ref(new Set<string>())
const removing = ref(false)
/** Destino calculado del arrastre (o de un ítem que llega de la paleta). */
const target = ref<{ index: number; depth: number; size: number } | null>(null)
const incoming = ref<{ index: number; depth: number } | null>(null)

const placeholder = computed(() => (removing.value ? null : target.value))
const slots = computed(() => props.rows.length + (incoming.value ? 1 : 0))

/** Filas ya colocadas al menos una vez: las nuevas aparecen en su sitio, sin animar. */
const placed = new Set<string>()

function setRef(key: string, el: unknown) {
  if (el) els.set(key, el as HTMLElement)
  else {
    els.delete(key)
    placed.delete(key)
  }
}

function rowWidth(depth: number): number {
  return Math.max((rootEl.value?.clientWidth ?? 0) - PADDING * 2 - depth * INDENT, 160)
}

/** Coloca las filas de `order` en su índice/sangría, saltando el bloque arrastrado. */
function layout(order: readonly OutlineRow[], animate = true) {
  order.forEach((row, position) => {
    if (dragKeys.value.has(row.key)) return
    const el = els.get(row.key)
    if (!el) return
    const index = position + (incoming.value && position >= incoming.value.index ? 1 : 0)
    const vars = { x: PADDING + row.depth * INDENT, y: index * ROW_H, width: rowWidth(row.depth) }
    if (animate && placed.has(row.key))
      gsap.to(el, { ...vars, duration: 0.25, ease: 'power2.out', overwrite: 'auto' })
    else gsap.set(el, vars)
    placed.add(row.key)
  })
}

/** Filas del resto mostradas dejando hueco para el bloque en `target`. */
function layoutAround(rest: readonly OutlineRow[], at: number, size: number) {
  rest.forEach((row, position) => {
    const el = els.get(row.key)
    if (!el) return
    const index = position >= at ? position + size : position
    gsap.to(el, {
      x: PADDING + row.depth * INDENT,
      y: index * ROW_H,
      width: rowWidth(row.depth),
      duration: 0.25,
      ease: 'power2.out',
      overwrite: 'auto',
    })
  })
}

function pointerInside(event: PointerEvent | MouseEvent | TouchEvent | undefined): boolean {
  const rect = rootEl.value?.getBoundingClientRect()
  if (!rect || !event) return true
  const point = 'touches' in event ? event.changedTouches[0] : event
  if (!point) return true
  return (
    point.clientX >= rect.left - KEEP_MARGIN &&
    point.clientX <= rect.right + KEEP_MARGIN &&
    point.clientY >= rect.top - KEEP_MARGIN &&
    point.clientY <= rect.bottom + KEEP_MARGIN
  )
}

function onPress(this: Draggable) {
  const key = (this.target as HTMLElement).dataset.key ?? ''
  const from = props.rows.findIndex((row) => row.key === key)
  if (from < 0) return
  const { rest, block } = takeBlock(props.rows, from)
  drag = { from, block, rest }
  dragKeys.value = new Set(block.map((row) => row.key))
  for (const row of block) gsap.set(els.get(row.key)!, { zIndex: 50 })
  target.value = { index: from, depth: block[0]!.depth, size: block.length }
}

function onDrag(this: Draggable) {
  if (!drag) return
  const [root, ...descendants] = drag.block
  // El bloque sigue al puntero conservando la sangría relativa de cada fila.
  descendants.forEach((row, k) => {
    const el = els.get(row.key)
    if (el)
      gsap.set(el, { x: this.x + (row.depth - root!.depth) * INDENT, y: this.y + (k + 1) * ROW_H })
  })

  const outside = !pointerInside(this.pointerEvent)
  const index = Math.min(Math.max(Math.round(this.y / ROW_H), 0), drag.rest.length)
  const depth = clampDepth(drag.rest, index, (this.x - PADDING) / INDENT)
  const previous = target.value
  if (
    outside === removing.value &&
    previous &&
    previous.index === index &&
    previous.depth === depth
  )
    return

  removing.value = outside
  target.value = { index, depth, size: drag.block.length }
  if (outside) layout(drag.rest)
  else layoutAround(drag.rest, index, drag.block.length)
}

function onRelease(this: Draggable) {
  if (!drag) return
  const { block, rest } = drag
  const next = removing.value
    ? rest
    : target.value
      ? placeBlock(rest, block, target.value.index, target.value.depth)
      : props.rows
  drag = null
  dragKeys.value = new Set()
  removing.value = false
  target.value = null
  for (const row of block) {
    const el = els.get(row.key)
    if (el) gsap.set(el, { zIndex: 'auto' })
  }
  layout(next)
  if (next !== props.rows) emit('update:rows', next)
}

/** Cambia el nivel del bloque de `index` en `delta` (si el árbol lo admite). */
function shift(index: number, delta: number) {
  const { rest } = takeBlock(props.rows, index)
  const depth = clampDepth(rest, index, props.rows[index]!.depth + delta)
  if (depth !== props.rows[index]!.depth)
    emit('update:rows', moveBlock(props.rows, index, index, depth))
}

function canIndent(index: number): boolean {
  const { rest } = takeBlock(props.rows, index)
  return depthRange(rest, index).max > props.rows[index]!.depth
}

function syncDraggables() {
  for (const [key, draggable] of drags) {
    if (!els.has(key)) {
      draggable.kill()
      drags.delete(key)
    }
  }
  for (const row of props.rows) {
    const el = els.get(row.key)
    if (!el || drags.has(row.key)) continue
    const [draggable] = Draggable.create(el, {
      type: 'x,y',
      trigger: el.querySelector('[data-drag-handle]') ?? el,
      cursor: 'grab',
      activeCursor: 'grabbing',
      zIndexBoost: false,
      onPress,
      onDrag,
      onRelease,
    })
    if (draggable) drags.set(row.key, draggable)
  }
}

// --- API para la paleta --------------------------------------------------

/**
 * Previsualiza la llegada de un ítem arrastrado: `pointer` (evento del
 * arrastre) decide si está sobre el contenedor; la esquina superior
 * izquierda de su elemento (`rect`, coordenadas de viewport) decide la fila
 * y la sangría, igual que al arrastrar una fila propia.
 */
function previewIncoming(rect: DOMRect, pointer?: PointerEvent | MouseEvent | TouchEvent): boolean {
  const root = rootEl.value?.getBoundingClientRect()
  if (!root) return false
  if (!pointerInside(pointer)) {
    cancelIncoming()
    return false
  }
  const index = Math.min(
    Math.max(Math.round((rect.top - root.top - PADDING) / ROW_H), 0),
    props.rows.length,
  )
  const depth = clampDepth(props.rows, index, (rect.left - root.left - PADDING) / INDENT)
  const previous = incoming.value
  if (previous && previous.index === index && previous.depth === depth) return true
  incoming.value = { index, depth }
  target.value = { index, depth, size: 1 }
  layout(props.rows)
  return true
}

/** Inserta el ítem `id` donde se previsualizó; `false` si no había destino. */
function commitIncoming(id: number): boolean {
  const at = incoming.value
  cancelIncoming()
  if (!at) return false
  emit(
    'update:rows',
    placeBlock(props.rows, [{ key: rowKey(id), id, depth: 0 }], at.index, at.depth),
  )
  return true
}

function cancelIncoming() {
  if (!incoming.value) return
  incoming.value = null
  target.value = null
  layout(props.rows)
}

defineExpose({ previewIncoming, commitIncoming, cancelIncoming })

// --- sincronización ------------------------------------------------------

watch(
  () => props.rows.map((row) => `${row.key}:${row.depth}`).join('|'),
  async () => {
    await nextTick()
    syncDraggables()
    if (!drag) layout(props.rows)
  },
)

let resizeObserver: ResizeObserver | undefined

onMounted(async () => {
  await nextTick()
  layout(props.rows, false)
  syncDraggables()
  resizeObserver = new ResizeObserver(() => {
    if (!drag) layout(props.rows, false)
  })
  if (rootEl.value) resizeObserver.observe(rootEl.value)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  drags.forEach((draggable) => draggable.kill())
  drags.clear()
})
</script>

<style scoped>
.mo {
  position: relative;
  border: 1px dashed var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 6px);
  transition:
    border-color 0.15s,
    background-color 0.15s;
  touch-action: none;
}
.mo--over {
  border-color: var(--p-primary-color);
  background-color: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}
.mo-empty {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}
.mo-placeholder {
  position: absolute;
  left: 0;
  border: 2px dashed var(--p-primary-color);
  border-radius: 6px;
  background-color: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
  margin-left: 8px;
  transition:
    transform 0.2s ease-out,
    height 0.2s ease-out,
    width 0.2s ease-out;
  pointer-events: none;
}
.mo-row {
  position: absolute;
  left: 0;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background-color: var(--p-content-background);
  will-change: transform;
}
.mo-row.is-dragging {
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.14);
}
.mo-row.is-removing {
  opacity: 0.45;
  border-color: var(--p-red-400);
}
.mo-handle {
  display: flex;
  cursor: grab;
  color: var(--p-text-muted-color);
}
.mo-text {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  line-height: 1.15;
}
.mo-label {
  overflow: hidden;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mo-route {
  overflow: hidden;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mo-actions {
  display: flex;
  gap: 0.1rem;
}
.mo-actions button {
  display: flex;
  padding: 0.3rem;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.mo-actions button:hover:not(:disabled) {
  background-color: var(--p-content-hover-background);
  color: var(--p-text-color);
}
.mo-actions button:disabled {
  opacity: 0.35;
  cursor: default;
}
</style>
