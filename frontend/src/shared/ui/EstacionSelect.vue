<!--
  Selector de estación igual al de la compra en la página web: agrupado por
  departamento, con los departamentos contraídos (se abren de uno en uno o
  todos a la vez) y todos visibles al buscar. El departamento `propio` (el de
  la estación del usuario) va primero y arranca abierto, igual que el del valor
  elegido.
-->
<template>
  <Select
    ref="select"
    :model-value="modelValue"
    :options="visibles"
    option-label="nombre"
    option-value="id"
    option-group-label="departamento"
    option-group-children="estaciones"
    filter
    auto-filter-focus
    :show-clear="showClear"
    filter-placeholder="Buscar estación"
    empty-filter-message="Sin resultados"
    empty-message="Sin resultados"
    :placeholder="placeholder"
    :disabled="disabled"
    :input-id="inputId"
    fluid
    scroll-height="18rem"
    :pt="ptSelect"
    @update:model-value="emit('update:modelValue', $event ?? null)"
    @filter="filtro = $event.value ?? ''"
    @show="alAbrir"
    @hide="filtro = ''"
  >
    <template #value="{ value, placeholder: ph }">
      <span v-if="value != null && nombrePorId.has(value)">{{ nombrePorId.get(value) }}</span>
      <span v-else>{{ ph }}</span>
    </template>

    <template #header>
      <div v-if="!filtro && grupos.length > 1" class="flex justify-end border-b border-surface-200 px-2 py-1">
        <button type="button" class="inline-flex cursor-pointer items-center gap-1 border-0 bg-transparent px-1 py-0.5 text-xs font-medium text-primary" @click="alternarTodos">
          <icon :name="todosAbiertos ? 'keyboard-double-arrow-up' : 'keyboard-double-arrow-down'" size="0.95rem" />
          {{ todosAbiertos ? 'Contraer todo' : 'Expandir todo' }}
        </button>
      </div>
    </template>

    <template #optiongroup="{ option }">
      <button
        v-if="!filtro"
        type="button"
        class="flex w-full cursor-pointer items-center gap-1.5 border-0 bg-transparent p-0 text-left text-xs font-semibold uppercase tracking-wide text-muted-color"
        :aria-expanded="abiertos.has(option.departamento)"
        @click.stop="alternar(option.departamento)"
      >
        <icon :name="abiertos.has(option.departamento) ? 'keyboard-arrow-down' : 'chevron-right'" size="0.9rem" />
        <icon name="location-on-outline" size="0.85rem" />
        <span class="flex-1">{{ option.departamento }}</span>
        <span class="font-normal normal-case tracking-normal">{{ totales.get(option.departamento) }}</span>
      </button>
      <span v-else class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-color"><icon name="location-on-outline" size="0.85rem" />{{ option.departamento }}</span>
    </template>
  </Select>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { agruparPorDepartamento, type EstacionAgrupable } from '@/core/estacion/agrupar'

const props = withDefaults(
  defineProps<{
    modelValue: number | null
    estaciones: readonly EstacionAgrupable[]
    /** Departamento de la estación del usuario: sale primero y abierto. */
    propio?: string | null
    placeholder?: string
    inputId?: string
    disabled?: boolean
    showClear?: boolean
  }>(),
  { propio: null, placeholder: 'Todas las estaciones', inputId: undefined, disabled: false, showClear: true },
)
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>()

const filtro = ref('')
const abiertos = ref(new Set<string>())

const ptSelect = {
  overlay: { class: '!min-w-64 !bg-surface-0' },
  optionGroup: { class: '!bg-surface-100 !py-1.5' },
  option: { class: '!pl-9' },
}

const grupos = computed(() => agruparPorDepartamento(props.estaciones, { propio: props.propio }))
const nombrePorId = computed(() => new Map(props.estaciones.map((e) => [e.id, e.nombre] as const)))
const totales = computed(() => new Map(grupos.value.map((g) => [g.departamento, g.estaciones.length] as const)))
const todosAbiertos = computed(() => grupos.value.every((g) => abiertos.value.has(g.departamento)))
/** Sin búsqueda: solo las estaciones de los departamentos abiertos. Buscando: todas. */
const visibles = computed(() => (filtro.value ? grupos.value : grupos.value.map((g) => (abiertos.value.has(g.departamento) ? g : { ...g, estaciones: [] }))))

/** Al abrir el panel se muestran el departamento propio y el del valor elegido. */
function alAbrir() {
  const s = new Set(abiertos.value)
  const propio = grupos.value.find((g) => propioCoincide(g.departamento))
  if (propio) s.add(propio.departamento)
  const elegido = grupos.value.find((g) => g.estaciones.some((e) => e.id === props.modelValue))
  if (elegido) s.add(elegido.departamento)
  abiertos.value = s
}

const clave = (d: string) => d.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim()
const propioCoincide = (d: string) => !!props.propio && clave(d) === clave(props.propio)

function alternar(departamento: string) {
  const s = new Set(abiertos.value)
  if (!s.delete(departamento)) s.add(departamento)
  abiertos.value = s
}

function alternarTodos() {
  abiertos.value = todosAbiertos.value ? new Set() : new Set(grupos.value.map((g) => g.departamento))
}
</script>
