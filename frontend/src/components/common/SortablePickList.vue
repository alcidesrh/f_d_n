<template>
  <div
    ref="rootEl"
    class="spl"
    :class="{ 'spl--disabled': disabled }"
    :style="{
      width: `${2 * itemWidth + gap}px`,
      height: `${containerHeight}px`,
    }"
  >
    <div
      class="spl-panel border border-surface-200 rounded-lg bg-surface-0"
      :style="{ left: '0px', width: `${itemWidth}px`, height: `${containerHeight}px` }"
    >
      <span v-if="labelA" class="spl-label">{{ labelA }}</span>
    </div>
    <div
      class="spl-panel border border-surface-200 rounded-lg bg-surface-0"
      :style="{ left: `${itemWidth + gap}px`, width: `${itemWidth}px`, height: `${containerHeight}px` }"
    >
      <span v-if="labelB" class="spl-label">{{ labelB }}</span>
    </div>

    <div
      v-for="it in renderList"
      :key="it.key"
      class="spl-item border border-surface-200 rounded-md bg-white shadow-sm"
      :data-key="it.key"
      :ref="(el) => setRef(it.key, el)"
      :style="{ width: `${itemWidth}px`, height: `${itemHeight - 4}px` }"
    >
      <slot name="item" :item="it">{{ it.key }}</slot>
    </div>
  </div>
</template>

<script lang="ts">
export interface SortablePickListItem {
  key: string;
  position: number;
}
</script>

<script setup lang="ts">
import { gsap } from "gsap";
import { Draggable } from "gsap/Draggable";
import { nextTick } from "vue";

gsap.registerPlugin(Draggable);

defineOptions({ name: "SortablePickList" });

const props = withDefaults(
  defineProps<{
    modelValue: [SortablePickListItem[], SortablePickListItem[]] | null;
    itemHeight?: number;
    itemWidth?: number;
    gap?: number;
    labelA?: string;
    labelB?: string;
    disabled?: boolean;
  }>(),
  {
    modelValue: null,
    itemHeight: 44,
    itemWidth: 240,
    gap: 24,
    labelA: "",
    labelB: "",
    disabled: false,
  },
);

const emit = defineEmits<{
  "update:modelValue": [lists: [SortablePickListItem[], SortablePickListItem[]]];
}>();

const rootEl = ref<HTMLElement | null>(null);
const listA = ref<SortablePickListItem[]>([]);
const listB = ref<SortablePickListItem[]>([]);
const els = new Map<string, HTMLElement>();
const drags = new Map<HTMLElement, Draggable>();
let draggingKey: string | null = null;

const cellW = computed(() => props.itemWidth + props.gap);
const containerHeight = computed(() => Math.max(1, listA.value.length, listB.value.length) * props.itemHeight);

const renderList = computed(() => [
  ...listA.value.map((it) => ({ ...it, column: 0 })),
  ...listB.value.map((it) => ({ ...it, column: 1 })),
]);

watch(
  () => props.modelValue,
  (v) => {
    listA.value = v?.[0] ? [...v[0]] : [];
    listB.value = v?.[1] ? [...v[1]] : [];
  },
  { immediate: true, deep: true },
);

function setRef(key: string, el: unknown | null) {
  if (el) els.set(key, el as HTMLElement);
  else els.delete(key);
}

function homeOf(it: { position: number; column: number }) {
  return { x: it.column * cellW.value, y: (it.position - 1) * props.itemHeight };
}

function findItem(key: string): { list: 0 | 1; index: number } {
  const a = listA.value.findIndex((i) => i.key === key);
  if (a >= 0) return { list: 0, index: a };
  const b = listB.value.findIndex((i) => i.key === key);
  return { list: 1, index: b };
}

function layoutAll() {
  for (const it of renderList.value) {
    if (it.key === draggingKey) continue;
    const el = els.get(it.key);
    if (el) gsap.to(el, { ...homeOf(it), duration: 0.3, ease: "power2.out", overwrite: "auto" });
  }
}

function renumber() {
  listA.value.forEach((it, i) => (it.position = i + 1));
  listB.value.forEach((it, i) => (it.position = i + 1));
}

function emitState() {
  emit(
    "update:modelValue",
    [listA.value.slice(), listB.value.slice()] as [SortablePickListItem[], SortablePickListItem[]],
  );
}

function applyMove(key: string, col: 0 | 1, row: number) {
  const src = findItem(key);
  const srcArr = src.list === 0 ? listA.value : listB.value;
  const dstArr = col === 0 ? listA.value : listB.value;
  if (col === src.list && row === src.index) return;
  const item = srcArr.splice(src.index, 1)[0];
  if (!item) return;
  dstArr.splice(row, 0, item);
  renumber();
  layoutAll();
  emitState();
}

function rowFor(d: Draggable, col: 0 | 1, src: { list: 0 | 1 }): number {
  const row = Math.floor((d.y + props.itemHeight / 2) / props.itemHeight);
  const len = col === src.list ? (src.list === 0 ? listA.value.length : listB.value.length) - 1
            : (col === 0 ? listA.value.length : listB.value.length);
  return Math.min(Math.max(row, 0), Math.max(len, 0));
}

function onDragStart(this: Draggable) {
  draggingKey = (this.target as HTMLElement).dataset.key ?? null;
  this.update();
}

function onDrag(this: Draggable) {
  if (!draggingKey) return;
  const c = Math.round(this.x / cellW.value);
  const col: 0 | 1 = c <= 0 ? 0 : 1;
  applyMove(draggingKey, col, rowFor(this, col, findItem(draggingKey)));
}

function onRelease(this: Draggable) {
  layoutAll();
  const key = (this.target as HTMLElement).dataset.key;
  const it = key ? renderList.value.find((i) => i.key === key) : undefined;
  if (it) gsap.to(this.target, { ...homeOf(it), duration: 0.3, ease: "power2.out", overwrite: "auto" });
  draggingKey = null;
  emitState();
}

function createDragger(el: HTMLElement) {
  if (props.disabled || drags.has(el)) return;
  const [d] = Draggable.create(el, {
    type: "x,y",
    bounds: rootEl.value ?? undefined,
    onDragStart,
    onDrag,
    onRelease,
  });
  if (d) drags.set(el, d);
}

onMounted(async () => {
  await nextTick();
  for (const it of renderList.value) {
    const el = els.get(it.key);
    if (!el) continue;
    gsap.set(el, homeOf(it));
    createDragger(el);
  }
});

watch(
  renderList,
  async () => {
    await nextTick();
    for (const it of renderList.value) {
      const el = els.get(it.key);
      if (el) createDragger(el);
    }
    layoutAll();
  },
  { deep: true },
);
</script>

<style scoped>
.spl {
  position: relative;
  touch-action: none;
}
.spl-panel {
  position: absolute;
  top: 0;
  pointer-events: none;
}
.spl-label {
  position: absolute;
  top: 6px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgb(100 116 139);
}
.spl-item {
  position: absolute;
  left: 0;
  top: 0;
  box-sizing: border-box;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}
.spl--disabled .spl-item {
  cursor: default;
}
</style>