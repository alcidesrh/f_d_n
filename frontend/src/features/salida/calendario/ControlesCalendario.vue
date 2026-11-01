<!--
  Panel de control del calendario de salidas: la fecha y hora (los días van
  de esa fecha en adelante y la grilla se desplaza a esa hora; el botón de al
  lado vuelve al momento actual), cuántos días se ven (con - y + se pasa a
  los anteriores o siguientes), ocupación en asientos o en porciento, la
  empresa y la selección de salidas para eliminarlas.
-->
<template>
  <section class="controles" aria-label="Controles del calendario">
    <div class="controles__nav">
      <div class="flex items-center gap-1">
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-time hour-format="24" :step-minute="5" show-icon icon-display="input" class="w-[11.5rem]" input-class="tabular-nums" aria-label="Fecha y hora" />
        <Button severity="secondary" text rounded class="tap-target" aria-label="Volver a ahora" v-tooltip.bottom="'Ahora'" @click="emit('hoy')">
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

      <label class="flex items-center gap-2 text-sm">
        <ToggleSwitch v-model="porciento" input-id="cal-porciento" />
        <span>En porciento</span>
      </label>

      <div v-if="puedeEliminar" class="controles__seleccion" role="group" aria-label="Selección">
        <Button :label="store.seleccionando ? 'Terminar' : 'Seleccionar'" size="small" :severity="store.seleccionando ? 'primary' : 'secondary'" :outlined="!store.seleccionando" :aria-pressed="store.seleccionando" @click="store.seleccionando ? store.salirDeSeleccion() : (store.seleccionando = true)">
          <template #icon><icon :name="store.seleccionando ? 'close' : 'check-box-outline'" class="mr-1" /></template>
        </Button>
        <Button :label="todasMarcadas ? 'Ninguna' : 'Todas'" size="small" severity="secondary" outlined :disabled="!store.visibles.length" v-tooltip.bottom="'Todas las salidas a la vista'" @click="store.seleccionarTodas()">
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

const fecha = computed<Date | null>({
  get: () => {
    const d = deDia(store.fecha);
    const [h = 0, m = 0] = store.hora.split(":").map(Number);
    d.setHours(h, m);
    return d;
  },
  set: (d) => {
    if (!d) return;
    store.hora = `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
    store.irA(aDia(d));
  },
});
const etiquetaDias = computed(() => OPCIONES_DIAS.find((o) => o.v === store.dias)?.l ?? `${store.dias} días`);
const porciento = computed({ get: () => store.modo === "porciento", set: (v: boolean) => (store.modo = v ? "porciento" : "asientos") });
const puedeEliminar = computed(() => !!store.opciones?.puede.eliminar);
const todasMarcadas = computed(() => store.seleccionando && store.visibles.length > 0 && store.visibles.every((s) => store.seleccion.has(s.id)));
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
