<!--
  Lista de transferencia: dos conjuntos apilados verticalmente. Arriba lo
  disponible, abajo lo asignado. Cada fila se arrastra (GSAP Draggable, también
  con el dedo) al otro conjunto; el botón de la fila hace lo mismo sin arrastrar.
  Presentación pura: el valor entra y sale por `modelValue`.
-->
<template>
  <div class="transfer flex w-full flex-col gap-3">
    <IconField>
      <InputIcon><icon name="search" /></InputIcon>
      <InputText v-model="query" class="w-full" size="small" placeholder="Filtrar" />
    </IconField>

    <section
      v-for="panel in PANELES"
      :key="panel"
      :ref="(el) => setPanel(panel, el)"
      class="transfer__panel"
      :class="{ 'is-target': hover === panel }"
    >
      <header class="transfer__titulo">
        <span>{{ panel === 'disponibles' ? disponiblesTitle : asignadasTitle }}</span>
        <span class="text-muted-color">{{ partes[panel].length }}</span>
      </header>
      <ul class="transfer__lista">
        <li
          v-for="option in partes[panel]"
          :key="keyOf(option)"
          :ref="(el) => setItem(panel, option, el)"
          class="transfer__item"
        >
          <span class="transfer__agarre" data-drag-handle title="Arrastrar">
            <icon name="drag-indicator" />
          </span>
          <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
          <button
            type="button"
            class="transfer__boton"
            :disabled="disabled"
            :aria-label="panel === 'disponibles' ? `Asignar ${option.label}` : `Quitar ${option.label}`"
            @click="mover(option.value, panel === 'disponibles' ? 'asignadas' : 'disponibles')"
          >
            <icon :name="panel === 'disponibles' ? 'add' : 'remove'" />
          </button>
        </li>
        <li v-if="!partes[panel].length" class="transfer__vacio">
          {{ panel === 'disponibles' ? vacioDisponibles : vacioAsignadas }}
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { gsap } from 'gsap'
import { Draggable } from 'gsap/Draggable'
import { dividirOpciones, type TransferOption } from './transfer'

gsap.registerPlugin(Draggable)

type Panel = 'disponibles' | 'asignadas'
const PANELES: Panel[] = ['disponibles', 'asignadas']

const props = withDefaults(
  defineProps<{
    options: TransferOption[]
    modelValue: unknown[]
    disabled?: boolean
    disponiblesTitle?: string
    asignadasTitle?: string
    vacioDisponibles?: string
    vacioAsignadas?: string
  }>(),
  {
    disabled: false,
    disponiblesTitle: 'Disponibles',
    asignadasTitle: 'Asignados',
    vacioDisponibles: 'Todos están asignados',
    vacioAsignadas: 'Arrastre aquí para asignar',
  },
)
const emit = defineEmits<{ 'update:modelValue': [value: unknown[]] }>()

const query = ref('')
const partes = computed(() => dividirOpciones(props.options, props.modelValue, query.value))
const hover = ref<Panel | null>(null)

const keyOf = (option: TransferOption) => String(option.value)

function mover(value: unknown, hacia: Panel) {
  if (props.disabled) return
  const sinValor = props.modelValue.filter((v) => v !== value)
  emit('update:modelValue', hacia === 'asignadas' ? [...sinValor, value] : sinValor)
}

interface Item {
  el: HTMLElement
  panel: Panel
  value: unknown
}

const panels = new Map<Panel, HTMLElement>()
const items = new Map<string, Item>()
const drags = new Map<string, Draggable>()

function setPanel(panel: Panel, el: unknown) {
  if (el) panels.set(panel, el as HTMLElement)
  else panels.delete(panel)
}

function setItem(panel: Panel, option: TransferOption, el: unknown) {
  const clave = `${panel}:${keyOf(option)}`
  if (el) items.set(clave, { el: el as HTMLElement, panel, value: option.value })
  else items.delete(clave)
}

/** Conjunto bajo el puntero, o null si el puntero está fuera de los dos. */
function panelBajo(x: number, y: number): Panel | null {
  for (const panel of PANELES) {
    const rect = panels.get(panel)?.getBoundingClientRect()
    if (rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
      return panel
    }
  }
  return null
}

function sync() {
  const activas: Map<string, Item> = props.disabled ? new Map() : items
  for (const [clave, draggable] of drags) {
    if (!activas.has(clave)) {
      draggable.kill()
      drags.delete(clave)
    }
  }
  for (const [clave, { el, panel, value }] of activas) {
    if (drags.has(clave)) continue
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
        hover.value = panelBajo(this.pointerX, this.pointerY)
      },
      onRelease(this: Draggable) {
        el.classList.remove('is-dragging')
        const destino = panelBajo(this.pointerX, this.pointerY)
        hover.value = null
        if (destino && destino !== panel) {
          mover(value, destino)
          return
        }
        gsap.to(el, { x: 0, y: 0, duration: 0.25, ease: 'power2.out' })
      },
    })
    if (draggable) drags.set(clave, draggable)
  }
}

watch(
  () => [...partes.value.disponibles, ...partes.value.asignadas].map(keyOf).join('|') + props.disabled,
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
.transfer__panel {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.5rem;
  border: 1px dashed var(--p-content-border-color);
  border-radius: 8px;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.transfer__panel.is-target {
  border-color: var(--p-primary-color);
  background-color: var(--p-primary-50, transparent);
}
.transfer__titulo {
  display: flex;
  justify-content: space-between;
  padding: 0 0.25rem;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.transfer__lista {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  max-height: 14rem;
  min-height: 3rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}
.transfer__item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background-color: var(--p-content-background);
}
.transfer__item.is-dragging {
  box-shadow: 0 10px 24px rgb(0 0 0 / 0.14);
}
.transfer__agarre {
  display: flex;
  cursor: grab;
  color: var(--p-text-muted-color);
  touch-action: none;
}
.transfer__boton {
  display: flex;
  padding: 0.2rem;
  border-radius: 999px;
  color: var(--p-text-muted-color);
  cursor: pointer;
}
.transfer__boton:hover:not(:disabled) {
  background-color: var(--p-content-hover-background);
  color: var(--p-text-color);
}
.transfer__boton:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}
.transfer__vacio {
  padding: 0.75rem 0.5rem;
  text-align: center;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
</style>
