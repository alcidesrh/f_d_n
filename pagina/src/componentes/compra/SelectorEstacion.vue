<!--
  Lista de estaciones agrupadas por departamento. Los departamentos arrancan
  contraídos y se abren de uno en uno o todos a la vez; al buscar se muestran
  todos los que coinciden. Con valor, el campo y el buscador se pueden limpiar.
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
    show-clear
    :filter-placeholder="t('buscador.buscarEstacion')"
    :empty-filter-message="t('buscador.sinResultados')"
    :empty-message="vacio ?? t('buscador.sinResultados')"
    :placeholder="placeholder"
    :disabled="disabled"
    :loading="cargando"
    :input-id="inputId"
    fluid
    scroll-height="18rem"
    :pt="ptSelect"
    @update:model-value="emit('update:modelValue', $event ?? null)"
    @filter="filtro = $event.value ?? ''"
    @hide="reiniciarFiltro"
  >
    <template #value="{ value, placeholder: ph }">
      <span v-if="value != null && nombrePorId.has(value)">{{ nombrePorId.get(value) }}</span>
      <span v-else>{{ ph }}</span>
    </template>

    <template #header>
      <div v-if="!filtro && grupos.length > 1" class="flex justify-end border-b border-surface-200 px-2 py-1">
        <button type="button" class="inline-flex cursor-pointer items-center gap-1 border-0 bg-transparent px-1 py-0.5 text-xs font-medium text-primary" @click="alternarTodos">
          <icon :name="todosAbiertos ? 'chevrons-up' : 'chevrons-down'" size="0.95rem" />
          {{ todosAbiertos ? t('buscador.contraerTodo') : t('buscador.expandirTodo') }}
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
        <icon :name="abiertos.has(option.departamento) ? 'chevron-down' : 'chevron-right'" size="0.9rem" />
        <icon name="map-pin" size="0.85rem" />
        <span class="flex-1">{{ option.departamento }}</span>
        <span class="font-normal normal-case tracking-normal">{{ totales.get(option.departamento) }}</span>
      </button>
      <span v-else class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-color"><icon name="map-pin" size="0.85rem" />{{ option.departamento }}</span>
    </template>

    <template #filtericon>
      <button
        v-if="filtro"
        type="button"
        class="grid size-6 cursor-pointer place-items-center rounded-full border-0 bg-transparent p-0 text-muted-color hover:text-color"
        :aria-label="t('buscador.limpiar')"
        :title="t('buscador.limpiar')"
        @mousedown.prevent
        @click.stop="limpiarFiltro"
      >
        <icon name="x" size="1rem" />
      </button>
      <icon v-else name="search" size="1rem" />
    </template>
  </Select>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { SELECT_PANEL } from '@/panelSelect'
import type { Estacion } from '@/tipos'

interface Grupo {
  departamento: string
  estaciones: Estacion[]
}

const props = defineProps<{
  modelValue: number | null
  grupos: Grupo[]
  placeholder: string
  inputId: string
  disabled?: boolean
  cargando?: boolean
  vacio?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>()
const { t } = useI18n()

// Instancia de PrimeVue Select: se limpia su buscador interno (`filterValue`).
const select = ref<{ filterValue: string | null; $el: HTMLElement } | null>(null)
const filtro = ref('')
const abiertos = ref(new Set<string>())

/** Panel con fondo sólido y ancho suficiente para nombres largos. */
const ptSelect = {
  overlay: { class: `!bg-white ${SELECT_PANEL}` },
  // Departamento con fondo tenue; sus estaciones sangradas para marcar la jerarquía.
  optionGroup: { class: '!bg-marca-50 !py-1.5' },
  option: { class: '!pl-9' },
}

const nombrePorId = computed(() => new Map(props.grupos.flatMap((g) => g.estaciones.map((e) => [e.id, e.nombre] as const))))
const totales = computed(() => new Map(props.grupos.map((g) => [g.departamento, g.estaciones.length] as const)))
const todosAbiertos = computed(() => props.grupos.every((g) => abiertos.value.has(g.departamento)))
/** Sin búsqueda: solo las estaciones de los departamentos abiertos. Buscando: todas. */
const visibles = computed(() => (filtro.value ? props.grupos : props.grupos.map((g) => (abiertos.value.has(g.departamento) ? g : { ...g, estaciones: [] }))))

function alternar(departamento: string) {
  const s = new Set(abiertos.value)
  if (!s.delete(departamento)) s.add(departamento)
  abiertos.value = s
}

function alternarTodos() {
  abiertos.value = todosAbiertos.value ? new Set() : new Set(props.grupos.map((g) => g.departamento))
}

function reiniciarFiltro() {
  filtro.value = ''
}

function limpiarFiltro() {
  filtro.value = ''
  if (select.value) select.value.filterValue = null
  document.querySelector<HTMLInputElement>('.p-select-overlay input[role="searchbox"]')?.focus()
}
</script>
