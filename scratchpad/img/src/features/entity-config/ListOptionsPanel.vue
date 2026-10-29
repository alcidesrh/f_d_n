<!--
  Opciones del listado de la entidad (`EntityConfiguration.listOptions`,
  ADR-029). Lo que se deja en "Por defecto" no se guarda y el listado usa
  su valor de siempre. Edita `useEntityConfigStore().listOptions` en sitio.
-->
<template>
  <div class="list-options">
    <label class="cell">
      <span class="cell__label">Filas por página</span>
      <InputNumber v-model="options.pageSize" :min="1" :max="500" placeholder="10" size="small" show-buttons fluid />
    </label>
    <label class="cell">
      <span class="cell__label">Opciones de filas</span>
      <InputText :model-value="pageSizesText" placeholder="10, 25, 50, 100" size="small" :invalid="pageSizesInvalid" @update:model-value="onPageSizes" />
    </label>
    <label class="cell">
      <span class="cell__label">Densidad</span>
      <Select v-model="options.density" :options="DENSITIES" option-label="label" option-value="value" placeholder="Por defecto (normal)" show-clear size="small" />
    </label>
    <label class="cell">
      <span class="cell__label">Varios filtros</span>
      <Select v-model="options.filterMode" :options="FILTER_MODES" option-label="label" option-value="value" placeholder="Por defecto (cualquiera)" show-clear size="small" />
    </label>
    <label class="cell">
      <span class="cell__label">Modo selección</span>
      <Select v-model="options.selectable" :options="ON_OFF" option-label="label" option-value="value" placeholder="Por defecto (sí)" show-clear size="small" />
    </label>
    <label class="cell">
      <span class="cell__label">Edición en línea</span>
      <Select v-model="options.inlineEdit" :options="ON_OFF" option-label="label" option-value="value" placeholder="Por defecto (sí)" show-clear size="small" />
    </label>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useEntityConfigStore } from './store'

const DENSITIES = [
  { label: 'Compacta', value: 'compact' },
  { label: 'Normal', value: 'normal' },
  { label: 'Amplia', value: 'comfortable' },
]
const FILTER_MODES = [
  { label: 'Cualquiera (OR)', value: 'or' },
  { label: 'Todos (AND)', value: 'and' },
]
const ON_OFF = [
  { label: 'Sí', value: true },
  { label: 'No', value: false },
]

const store = useEntityConfigStore()
const options = computed(() => store.listOptions)

const pageSizesText = ref('')
const pageSizesInvalid = ref(false)

watch(
  () => store.listOptions.pageSizes,
  (sizes) => {
    if (!pageSizesInvalid.value) pageSizesText.value = (sizes ?? []).join(', ')
  },
  { immediate: true },
)

/** "10, 25, 50" → `[10, 25, 50]`; vacío borra la opción. */
function onPageSizes(value: string | undefined) {
  pageSizesText.value = value ?? ''
  const parts = pageSizesText.value.split(/[\s,;]+/).filter(Boolean)
  const sizes = parts.map(Number)
  pageSizesInvalid.value = sizes.some((n) => !Number.isInteger(n) || n <= 0 || n > 500)
  store.setAttrsError('listOptions#pageSizes', pageSizesInvalid.value)
  if (!pageSizesInvalid.value) store.listOptions.pageSizes = sizes.length ? [...new Set(sizes)].sort((a, b) => a - b) : undefined
}
</script>

<style scoped>
.list-options {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 11rem), 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.cell {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.2rem;
}
.cell__label {
  font-size: 0.7rem;
  letter-spacing: 0.02em;
  color: var(--p-text-muted-color);
  white-space: nowrap;
}
</style>
