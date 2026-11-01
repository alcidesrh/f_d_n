<!--
  Panel de control del calendario de salidas: la fecha (los días van de esa
  fecha en adelante; el botón de al lado vuelve a hoy), cuántos días se ven
  (con - y + se pasa a los anteriores o siguientes), una hora exacta (solo
  las salidas a esa hora, en todos los días), ocupación en asientos o en
  porciento, la empresa y la selección de salidas para eliminarlas («Todas»
  marca las que cumplen el filtro y la ruta fijada en la leyenda).
-->
<template>
  <section class="controles" aria-label="Controles del calendario">
    <div class="controles__nav">
      <div class="flex items-center gap-1">
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-icon icon-display="input" class="w-[9.5rem]" input-class="tabular-nums" aria-label="Fecha" />
        <Button severity="secondary" text rounded class="tap-target" aria-label="Volver a hoy" v-tooltip.bottom="'Hoy'" @click="emit('hoy')">
          <template #icon><icon name="refresh" /></template>
        </Button>
      </div>

      <div class="flex items-center gap-1" role="group" aria-label="Días visibles">
        <Button severity="secondary" outlined size="small" class="tap-target" :aria-label="`${etiquetaDias} antes`" v-tooltip.bottom="`${etiquetaDias} antes`" @click="store.mover(-1)">
          <template #icon><icon name="remove" /></template>
        </Button>
        <Select v-model="store.dias" :options="OPCIONES_DIAS" option-label="l" option-value="v" size="small" class="w-[7.5rem]" aria-label="Días visibles" />
        <Button severity="secondary" outlined size="small" class="tap-target" :aria-label="`${etiquetaDias} después`" v-tooltip.bottom="`${etiquetaDias} después`" @click="store.mover(1)">
          <template #icon><icon name="add" /></template>
        </Button>
      </div>

      <Select v-model="store.hora" :options="horas" option-label="l" option-value="v" filter show-clear size="small" class="w-[9rem]" placeholder="Todas las horas" empty-message="Sin salidas" empty-filter-message="Ninguna" aria-label="Hora de salida">
        <template #dropdownicon><icon name="schedule-outline" /></template>
      </Select>

      <label class="flex items-center gap-2 text-sm">
        <ToggleSwitch v-model="porciento" input-id="cal-porciento" />
        <span>En porciento</span>
      </label>

      <div v-if="puedeEliminar" class="controles__seleccion" role="group" aria-label="Selección">
        <Button :label="store.seleccionando ? 'Terminar' : 'Seleccionar'" size="small" :severity="store.seleccionando ? 'primary' : 'secondary'" :outlined="!store.seleccionando" :aria-pressed="store.seleccionando" @click="store.seleccionando ? store.salirDeSeleccion() : (store.seleccionando = true)">
          <template #icon><icon :name="store.seleccionando ? 'close' : 'check-box-outline'" class="mr-1" /></template>
        </Button>
        <Button :label="store.todasMarcadas ? 'Ninguna' : 'Todas'" size="small" severity="secondary" outlined :disabled="!store.seleccionables.length" v-tooltip.bottom="ayudaTodas" @click="store.seleccionarTodas()">
          <template #icon><icon name="select-all" class="mr-1" /></template>
        </Button>
        <Button v-if="store.seleccion.size" :label="`Eliminar (${store.seleccion.size})`" size="small" severity="danger" @click="emit('eliminar')">
          <template #icon><icon name="delete-outline" class="mr-1" /></template>
        </Button>
      </div>
    </div>

    <div v-if="(store.opciones?.empresas.length ?? 0) > 1" class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Empresas">
      <button v-for="e in store.opciones?.empresas ?? []" :key="e.id" type="button" class="estado" :class="{ 'estado--activo': store.empresa === e.id }" :aria-pressed="store.empresa === e.id" @click="store.empresa = store.empresa === e.id ? null : e.id">
        {{ e.nombre }}
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { horaMinuto } from "@/core/salida/calendario";
import { aDia, deDia } from "@/core/salida/filtro";
import { useCalendarioSalidasStore } from "./store";

const emit = defineEmits<{ hoy: []; eliminar: [] }>();

const OPCIONES_DIAS = [
  { v: 1, l: "1 día" },
  { v: 7, l: "7 días" },
  { v: 15, l: "15 días" },
  { v: 30, l: "30 días" },
];

const store = useCalendarioSalidasStore();

const fecha = computed<Date | null>({ get: () => deDia(store.fecha), set: (d) => d && store.irA(aDia(d)) });
/** Las horas con salidas en los días cargados, con cuántas hay («06:30 · 4»). */
const horas = computed(() => {
  const n = new Map<string, number>();
  for (const s of store.datos?.items ?? []) n.set(horaMinuto(s.fecha), (n.get(horaMinuto(s.fecha)) ?? 0) + 1);
  if (store.hora && !n.has(store.hora)) n.set(store.hora, 0);
  return [...n].sort(([a], [b]) => a.localeCompare(b)).map(([v, c]) => ({ v, l: `${v} · ${c}` }));
});
const ayudaTodas = computed(() => {
  const ruta = store.fijada !== null ? store.rutaPorId.get(store.fijada)?.nombre : null;
  return `Las ${store.seleccionables.length} salidas a la vista${ruta ? ` de ${ruta}` : ""}${store.hora ? ` a las ${store.hora}` : ""}`;
});
const etiquetaDias = computed(() => OPCIONES_DIAS.find((o) => o.v === store.dias)?.l ?? `${store.dias} días`);
const porciento = computed({ get: () => store.modo === "porciento", set: (v: boolean) => (store.modo = v ? "porciento" : "asientos") });
const puedeEliminar = computed(() => !!store.opciones?.puede.eliminar);
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
.controles__seleccion {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  margin-left: auto;
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
</style>
