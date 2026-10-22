<!--
  Panel de control del calendario de salidas: navegar (la fecha elegida es el
  centro del eje X; «Hoy» vuelve al momento actual), cuántos días se ven,
  ocupación en asientos o en porciento, y los filtros (estado con su conteo,
  empresa, ruta y bus). En pantallas angostas los filtros se pliegan.
-->
<template>
  <section class="controles" aria-label="Controles del calendario">
    <div class="controles__nav">
      <div class="flex items-center gap-1">
        <Button severity="secondary" text rounded class="tap-target" :aria-label="`${store.dias} días antes`" v-tooltip.bottom="'Antes'" @click="store.mover(-1)">
          <template #icon><icon name="chevron-left" /></template>
        </Button>
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-icon icon-display="input" class="w-[9.5rem]" input-class="tabular-nums" aria-label="Fecha del centro" />
        <Button severity="secondary" text rounded class="tap-target" :aria-label="`${store.dias} días después`" v-tooltip.bottom="'Después'" @click="store.mover(1)">
          <template #icon><icon name="chevron-right" /></template>
        </Button>
        <Button label="Hoy" size="small" severity="secondary" outlined :disabled="esHoy" @click="emit('hoy')" />
      </div>

      <SelectButton v-model="store.dias" :options="OPCIONES_DIAS" option-label="l" option-value="v" :allow-empty="false" size="small" aria-label="Días visibles" />

      <label class="flex items-center gap-2 text-sm">
        <ToggleSwitch v-model="porciento" input-id="cal-porciento" />
        <span>En porciento</span>
      </label>

      <Button severity="secondary" outlined size="small" class="controles__plegar" :aria-expanded="abiertos" aria-controls="cal-filtros" @click="abiertos = !abiertos">
        <icon name="filter-alt-outline" class="mr-1" />Filtros<Badge v-if="store.filtrosActivos" :value="store.filtrosActivos" class="ml-1.5" />
      </Button>
    </div>

    <div id="cal-filtros" class="controles__filtros" :class="{ 'controles__filtros--abiertos': abiertos }">
      <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Estados">
        <button
          v-for="e in ESTADOS"
          :key="e.valor"
          type="button"
          class="estado"
          :class="{ 'estado--activo': store.estados.includes(e.valor) }"
          :aria-pressed="store.estados.includes(e.valor)"
          @click="store.alternarEstado(e.valor)"
        >
          <span class="estado__punto" :class="`estado__punto--${e.valor}`" />{{ e.etiqueta
          }}<span v-if="store.datos?.porEstado[e.valor]" class="tabular-nums opacity-70">{{ store.datos.porEstado[e.valor] }}</span>
        </button>
      </div>
      <div class="grid grid-cols-1 gap-2 @2xl:grid-cols-3">
        <Select
          v-if="(store.opciones?.empresas.length ?? 0) > 1"
          v-model="store.empresa"
          :options="store.opciones?.empresas ?? []"
          option-label="nombre"
          option-value="id"
          show-clear
          fluid
          placeholder="Todas las empresas"
          aria-label="Empresa"
        />
        <RutaSelect v-model="store.ruta" show-clear placeholder="Todas las rutas" aria-label="Ruta" />
        <Select
          v-model="store.bus"
          :options="buses"
          :option-label="(b: BusOpcion) => [b.codigo, b.matricula].filter(Boolean).join(' · ')"
          option-value="id"
          filter
          :filter-fields="['codigo', 'matricula']"
          show-clear
          fluid
          placeholder="Todos los buses"
          aria-label="Bus"
        />
      </div>
      <div v-if="store.filtrosActivos" class="flex justify-end">
        <Button label="Limpiar filtros" size="small" text @click="store.limpiar" />
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { aDia, deDia, ESTADOS } from '@/core/salida/filtro'
import type { BusOpcion } from '@/core/salida/types'
import RutaSelect from '../RutaSelect.vue'
import { useCalendarioSalidasStore } from './store'

const emit = defineEmits<{ hoy: [] }>()

const OPCIONES_DIAS = [
  { v: 7, l: '7 días' },
  { v: 15, l: '15' },
  { v: 31, l: '31' },
]

const store = useCalendarioSalidasStore()
const abiertos = ref(false)

const fecha = computed<Date | null>({ get: () => deDia(store.fecha), set: (d) => d && store.irA(aDia(d)) })
const esHoy = computed(() => store.fecha === aDia(store.ahora))
const porciento = computed({ get: () => store.modo === 'porciento', set: (v: boolean) => (store.modo = v ? 'porciento' : 'asientos') })
const buses = computed(() => (store.opciones?.buses ?? []).filter((b) => !store.empresa || !b.empresaId || b.empresaId === store.empresa))
</script>

<style scoped>
.controles {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.controles__nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
}
.controles__plegar {
  margin-left: auto;
}
/* Angosto: los filtros se abren con el botón. */
.controles__filtros {
  display: none;
  flex-direction: column;
  gap: 0.75rem;
}
.controles__filtros--abiertos {
  display: flex;
}
@container (min-width: 48rem) {
  .controles__plegar {
    display: none;
  }
  .controles__filtros {
    display: flex;
  }
}
.estado {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.65rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
}
.estado--activo {
  border-color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
  color: var(--p-text-color);
}
.estado__punto {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  background: var(--p-surface-400);
}
.estado__punto--abordando {
  background: var(--c-info);
}
.estado__punto--iniciada {
  background: var(--c-success);
}
.estado__punto--finalizada {
  background: var(--p-surface-600);
}
.estado__punto--cancelada {
  background: var(--c-danger);
}
</style>
