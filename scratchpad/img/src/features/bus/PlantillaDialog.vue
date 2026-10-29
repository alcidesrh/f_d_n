<!--
  Elegir el croquis de otro bus como plantilla. Los buses con la misma
  distribución se agrupan en una sola tarjeta (como los "tipos de bus" del
  legado); se busca por código de bus.
-->
<template>
  <Dialog
    :visible="visible"
    modal
    header="Usar el croquis de otro bus"
    :style="{ width: 'min(64rem, 96vw)' }"
    :content-style="{ paddingTop: '0.25rem' }"
    @update:visible="emit('update:visible', $event)"
  >
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <IconField class="grow">
        <InputIcon><icon name="search" /></InputIcon>
        <InputText
          v-model="busqueda"
          placeholder="Buscar por código de bus"
          size="small"
          class="w-full"
          autofocus
        />
      </IconField>
      <SelectButton
        v-model="filtroPlantas"
        :options="OPCIONES_PLANTAS"
        option-label="label"
        option-value="value"
        size="small"
        :allow-empty="false"
      />
    </div>

    <div v-if="cargando" class="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
      <Skeleton v-for="i in 6" :key="i" height="15rem" />
    </div>
    <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
    <p v-else-if="visibles.length === 0" class="py-10 text-center text-muted-color">
      {{
        plantillas.length
          ? 'Ningún croquis coincide con la búsqueda.'
          : 'Todavía no hay otros buses con croquis.'
      }}
    </p>
    <ul v-else class="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
      <li v-for="plantilla in visibles" :key="plantilla.firma">
        <button type="button" class="tpl" @click="elegir(plantilla)">
          <div class="tpl__map">
            <BusMap :elementos="plantilla.elementos" tamano="xs" :etiquetas="false" />
          </div>
          <div class="tpl__body">
            <div class="flex items-baseline justify-between gap-2">
              <strong class="text-sm">{{ plantilla.asientos }} asientos</strong>
              <span class="text-xs text-muted-color">
                {{ plantilla.plantas === 2 ? 'Dos plantas' : 'Una planta' }}
              </span>
            </div>
            <div class="text-xs text-muted-color">{{ detalleClases(plantilla) }}</div>
            <div class="tpl__buses">
              <span
                v-for="bus in plantilla.buses.slice(0, MAX_CHIPS)"
                :key="bus.id"
                class="tpl__chip"
                :class="{ 'tpl__chip--match': coincide(bus.codigo) }"
                >{{ bus.codigo }}</span
              >
              <span v-if="plantilla.buses.length > MAX_CHIPS" class="tpl__chip tpl__chip--more">
                +{{ plantilla.buses.length - MAX_CHIPS }}
              </span>
            </div>
          </div>
        </button>
      </li>
    </ul>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { fetchPlantillas } from '@/core/croquis/api'
import { esAsiento } from '@/core/croquis/model'
import type { PlantillaCroquis } from '@/core/croquis/types'
import BusMap from '@/shared/bus-map/BusMap.vue'

const MAX_CHIPS = 8
const OPCIONES_PLANTAS = [
  { label: 'Todas', value: 0 },
  { label: '1 planta', value: 1 },
  { label: '2 plantas', value: 2 },
]

const props = defineProps<{ visible: boolean; excluirBusId?: number | null }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  elegir: [plantilla: PlantillaCroquis]
}>()

const plantillas = ref<PlantillaCroquis[]>([])
const cargando = ref(false)
const error = ref('')
const busqueda = ref('')
const filtroPlantas = ref(0)
let cargadas = false

watch(
  () => props.visible,
  async (visible) => {
    if (!visible || cargadas) return
    cargando.value = true
    error.value = ''
    try {
      plantillas.value = await fetchPlantillas()
      cargadas = true
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : String(cause)
    } finally {
      cargando.value = false
    }
  },
  { immediate: true },
)

const normalizar = (texto: string) => texto.trim().toLowerCase()
const coincide = (codigo: string) =>
  !!busqueda.value.trim() && normalizar(codigo).includes(normalizar(busqueda.value))

const visibles = computed(() =>
  plantillas.value
    .map((p) => ({ ...p, buses: p.buses.filter((b) => b.id !== props.excluirBusId) }))
    .filter((p) => p.buses.length > 0)
    .filter((p) => !filtroPlantas.value || p.plantas === filtroPlantas.value)
    .filter((p) => !busqueda.value.trim() || p.buses.some((b) => coincide(b.codigo)))
    // Los que coinciden con la búsqueda, primero.
    .map((p) => ({
      ...p,
      buses: [...p.buses].sort((a, b) => Number(coincide(b.codigo)) - Number(coincide(a.codigo))),
    })),
)

function detalleClases(plantilla: PlantillaCroquis): string {
  const asientos = plantilla.elementos.filter(esAsiento)
  const b = asientos.filter((a) => a.clase === 'B').length
  return b ? `${asientos.length - b} clase A · ${b} clase B` : 'Solo clase A'
}

function elegir(plantilla: PlantillaCroquis) {
  emit('elegir', plantilla)
  emit('update:visible', false)
}
</script>

<style scoped>
.tpl {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  text-align: left;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  background: var(--p-content-background);
  overflow: hidden;
  transition:
    border-color 0.15s,
    box-shadow 0.15s,
    transform 0.15s;
}
.tpl:hover,
.tpl:focus-visible {
  border-color: var(--p-primary-color);
  box-shadow: 0 8px 24px -16px color-mix(in srgb, var(--p-primary-color) 70%, transparent);
  transform: translateY(-1px);
}
.tpl__map {
  display: grid;
  place-items: center;
  min-height: 9.5rem;
  padding: 0.9rem;
  background: color-mix(in srgb, var(--p-text-color) 3%, var(--p-content-background));
  border-bottom: 1px solid var(--p-content-border-color);
}
.tpl__body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.7rem 0.8rem 0.8rem;
}
.tpl__buses {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-top: 0.15rem;
}
.tpl__chip {
  font-size: 0.7rem;
  font-variant-numeric: tabular-nums;
  padding: 0.05rem 0.4rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-text-color) 7%, transparent);
}
.tpl__chip--match {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.tpl__chip--more {
  color: var(--p-text-muted-color);
}
</style>
