<!--
  Ítems navegables que aún no están en el menú abierto. Cada uno se arrastra
  (GSAP Draggable) hasta el `MenuOutline`, que previsualiza dónde y en qué
  nivel quedaría; soltarlo fuera lo devuelve a su sitio.
-->
<template>
  <div class="flex flex-col gap-2">
    <IconField>
      <InputIcon><icon name="search" /></InputIcon>
      <InputText v-model="query" class="w-full" size="small" placeholder="Filtrar ítems" />
    </IconField>
    <p v-if="!visible.length" class="py-4 text-center text-sm text-muted-color">
      {{ items.length ? 'Sin coincidencias' : 'Todos los ítems están en el menú' }}
    </p>
    <ul class="flex flex-col gap-1.5">
      <li
        v-for="item in visible"
        :key="item.id"
        :ref="(el) => setRef(item.id, el)"
        class="palette-item"
        :data-id="item.id"
      >
        <span class="palette-handle" data-drag-handle title="Arrastrar al menú">
          <icon name="grip-vertical" />
        </span>
        <icon :name="item.icon.icon" class="text-primary" />
        <div class="flex min-w-0 flex-1 flex-col leading-tight">
          <span class="truncate text-sm font-medium">{{ item.nombre }}</span>
          <span class="truncate text-xs text-muted-color">{{ item.route.path }}</span>
        </div>
        <button type="button" class="palette-btn" title="Editar" @click="emit('edit', item)">
          <icon name="pencil" />
        </button>
        <button type="button" class="palette-btn" title="Eliminar" @click="emit('remove', item)">
          <icon name="trash" />
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { Draggable } from 'gsap/Draggable'
import { nextTick } from 'vue'
import { numericId, type MenuItemDto } from './api'

gsap.registerPlugin(Draggable)

/** Lo que la paleta necesita del `MenuOutline` (su API expuesta). */
export interface DropTarget {
  previewIncoming(rect: DOMRect, pointer?: PointerEvent | MouseEvent | TouchEvent): boolean
  commitIncoming(id: number): boolean
  cancelIncoming(): void
}

const props = defineProps<{ items: MenuItemDto[]; target: DropTarget | null }>()
const emit = defineEmits<{ edit: [item: MenuItemDto]; remove: [item: MenuItemDto] }>()

const query = ref('')
const visible = computed(() => {
  const needle = query.value.trim().toLowerCase()
  if (!needle) return props.items
  return props.items.filter((item) =>
    `${item.nombre} ${item.route.path ?? ''}`.toLowerCase().includes(needle),
  )
})

const els = new Map<string, HTMLElement>()
const drags = new Map<string, Draggable>()

function setRef(id: string, el: unknown) {
  if (el) els.set(id, el as HTMLElement)
  else els.delete(id)
}

function sync() {
  for (const [id, draggable] of drags) {
    if (!els.has(id)) {
      draggable.kill()
      drags.delete(id)
    }
  }
  for (const [id, el] of els) {
    if (drags.has(id)) continue
    const [draggable] = Draggable.create(el, {
      type: 'x,y',
      trigger: el.querySelector('[data-drag-handle]') ?? el,
      cursor: 'grab',
      activeCursor: 'grabbing',
      zIndexBoost: true,
      onDragStart() {
        el.classList.add('is-dragging')
      },
      onDrag(this: Draggable) {
        props.target?.previewIncoming(el.getBoundingClientRect(), this.pointerEvent)
      },
      onRelease() {
        el.classList.remove('is-dragging')
        const dropped = props.target?.commitIncoming(numericId(id)) ?? false
        // Insertado: la fila desaparece de la paleta; si no, vuelve a su sitio.
        if (!dropped) gsap.to(el, { x: 0, y: 0, duration: 0.3, ease: 'power2.out' })
      },
    })
    if (draggable) drags.set(id, draggable)
  }
}

watch(
  () => visible.value.map((item) => item.id).join('|'),
  async () => {
    await nextTick()
    sync()
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  drags.forEach((draggable) => draggable.kill())
  drags.clear()
})
</script>

<style scoped>
.palette-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background-color: var(--p-content-background);
}
.palette-item.is-dragging {
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.14);
}
.palette-handle {
  display: flex;
  cursor: grab;
  color: var(--p-text-muted-color);
}
.palette-btn {
  display: flex;
  padding: 0.3rem;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.palette-btn:hover {
  background-color: var(--p-content-hover-background);
  color: var(--p-text-color);
}
</style>
