<!--
  Lista vertical de paneles reordenables con GSAP Draggable.

  Cada elemento del `modelValue` se pinta como un panel absoluto de altura fija
  y se arrastra únicamente desde su tirador (`handle`, por defecto
  `[data-drag-handle]`), de modo que los inputs del panel siguen siendo usables.
  Al soltar emite `update:modelValue` con el nuevo orden; la posición final de
  cada elemento es su índice en ese array.

  Docs de GSAP Draggable: https://gsap.com/docs/v3/Plugins/Draggable/
-->
<template>
  <div
    ref="rootEl"
    class="spn"
    :class="{ 'spn--disabled': disabled }"
    :style="{ height: `${rows.length * rowHeight}px` }"
  >
    <div
      v-for="(row, index) in rows"
      :key="row.key"
      :ref="(el) => setRef(row.key, el)"
      class="spn-row"
      :data-key="row.key"
      :style="{ height: `${rowHeight - gap}px` }"
    >
      <slot name="row" :row="row" :index="index" />
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends { key: string }">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { nextTick, type Ref } from "vue";

gsap.registerPlugin(Draggable);

defineOptions({ name: "SortablePanelList" });

const props = withDefaults(
  defineProps<{
    modelValue: T[];
    /** Alto de cada fila incluyendo `gap` (px). */
    rowHeight?: number;
    /** Separación vertical entre paneles (px). */
    gap?: number;
    /** Selector del tirador dentro del panel; sin coincidencia arrastra el panel entero. */
    handle?: string;
    disabled?: boolean;
  }>(),
  { rowHeight: 84, gap: 8, handle: "[data-drag-handle]", disabled: false },
);

const emit = defineEmits<{ "update:modelValue": [rows: T[]] }>();

const rootEl = ref<HTMLElement | null>(null);
const rows = ref([]) as Ref<T[]>;
const els = new Map<string, HTMLElement>();
const drags = new Map<string, Draggable>();
let draggingKey: string | null = null;

const keysOf = (list: T[]) => list.map((row) => row.key).join("|");

// El padre y esta lista comparten los objetos fila (editar un input del panel
// muta el mismo objeto): solo hay que resincronizar cuando cambia el orden o
// el conjunto de filas, nunca en mitad de un arrastre.
watch(
  () => props.modelValue,
  (value) => {
    const next = value ?? [];
    if (draggingKey || keysOf(next) === keysOf(rows.value)) return;
    rows.value = [...next];
  },
  { immediate: true },
);

function setRef(key: string, el: unknown | null) {
  if (el) els.set(key, el as HTMLElement);
  else els.delete(key);
}

function homeY(index: number): number {
  return index * props.rowHeight;
}

function indexOfKey(key: string): number {
  return rows.value.findIndex((row) => row.key === key);
}

function layoutAll(animate = true) {
  rows.value.forEach((row, index) => {
    if (row.key === draggingKey) return;
    const el = els.get(row.key);
    if (!el) return;
    if (animate) gsap.to(el, { y: homeY(index), duration: 0.28, ease: "power2.out", overwrite: "auto" });
    else gsap.set(el, { y: homeY(index) });
  });
}

function onDragStart(this: Draggable) {
  const el = this.target as HTMLElement;
  draggingKey = el.dataset.key ?? null;
  el.classList.add("is-dragging");
  gsap.set(el, { zIndex: 50 });
  this.update();
}

function onDrag(this: Draggable) {
  if (!draggingKey) return;
  const from = indexOfKey(draggingKey);
  if (from < 0) return;
  const to = Math.min(Math.max(Math.round(this.y / props.rowHeight), 0), rows.value.length - 1);
  if (to === from) return;
  const [row] = rows.value.splice(from, 1);
  if (!row) return;
  rows.value.splice(to, 0, row);
  layoutAll();
}

function onRelease(this: Draggable) {
  const el = this.target as HTMLElement;
  const index = indexOfKey(el.dataset.key ?? "");
  draggingKey = null;
  el.classList.remove("is-dragging");
  gsap.to(el, {
    y: homeY(Math.max(index, 0)),
    duration: 0.28,
    ease: "power2.out",
    overwrite: "auto",
    onComplete: () => gsap.set(el, { zIndex: "auto" }),
  });
  layoutAll();
  emit("update:modelValue", rows.value.slice());
}

function syncDraggables() {
  for (const [key, draggable] of drags) {
    if (!els.has(key)) {
      draggable.kill();
      drags.delete(key);
    }
  }
  if (props.disabled) return;
  for (const row of rows.value) {
    const el = els.get(row.key);
    if (!el || drags.has(row.key)) continue;
    const [draggable] = Draggable.create(el, {
      type: "y",
      bounds: rootEl.value ?? undefined,
      trigger: (props.handle && el.querySelector(props.handle)) || el,
      cursor: "grab",
      activeCursor: "grabbing",
      onDragStart,
      onDrag,
      onRelease,
    });
    if (draggable) drags.set(row.key, draggable);
  }
}

const sameSet = (a: string, b: string) =>
  a.split("|").sort().join("|") === b.split("|").sort().join("|");

// Un reordenamiento externo (mismas filas en otro orden, p. ej. al ocultar un
// campo) se anima; un cambio del conjunto de filas (otra entidad) se coloca de
// golpe, y además hay que crear/matar los draggables.
watch(
  () => keysOf(rows.value),
  async (next, previous) => {
    await nextTick();
    syncDraggables();
    if (!draggingKey) layoutAll(previous !== undefined && sameSet(next, previous));
  },
);

onMounted(async () => {
  await nextTick();
  layoutAll(false);
  syncDraggables();
});

onBeforeUnmount(() => {
  drags.forEach((draggable) => draggable.kill());
  drags.clear();
});
</script>

<style scoped>
.spn {
  position: relative;
  touch-action: none;
}
.spn-row {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  will-change: transform;
}
.spn-row.is-dragging {
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.14);
}
</style>
