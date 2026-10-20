<!--
  Elegir la distribución de asientos (molde de croquis, ADR-027) de una
  salida: un botón con la distribución elegida que abre la galería de
  moldes, con su dibujo y los buses que la tienen (buscar por código de bus,
  filtrar por plantas). La usan las horas del programador y "Editar salida".
-->
<template>
  <div class="flex min-w-0 gap-1">
    <Button type="button" severity="secondary" variant="outlined" fluid class="min-w-0 justify-between bg-surface-0!" :disabled="disabled" :aria-label="ariaLabel ?? 'Distribución de asientos'" @click="abierto = true">
      <span class="truncate" :class="{ 'text-muted-color': !elegido }">{{ elegido ? etiquetaCroquis(elegido) : "Croquis…" }}</span>
      <icon name="mitre" />
    </Button>
    <Dialog v-model:visible="abierto" modal header="Elegir distribución de asientos" :style="{ width: 'min(64rem, 96vw)' }" :content-style="{ paddingTop: '0.25rem' }">
      <div class="galeria__filtros flex flex-wrap items-center gap-3">
        <IconField class="grow">
          <InputIcon><icon name="search" /></InputIcon>
          <InputText v-model="busqueda" placeholder="Buscar por código de bus" size="small" class="w-full" autofocus />
        </IconField>
        <SelectButton v-model="filtroPlantas" :options="OPCIONES_PLANTAS" option-label="label" option-value="value" size="small" :allow-empty="false" />
      </div>
      <p v-if="!visibles.length" class="py-10 text-center text-muted-color">
        {{ croquis.length ? "Ninguna distribución coincide con la búsqueda." : "No hay distribuciones disponibles." }}
      </p>
      <ul v-else class="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
        <li v-for="m in visibles" :key="m.id">
          <button type="button" class="tpl" :class="{ 'tpl--activo': m.id === modelo }" @click="elegir(m)">
            <div class="tpl__map">
              <BusMap :elementos="m.elementos" tamano="xs" :etiquetas="false" />
            </div>
            <div class="tpl__body">
              <div class="flex items-baseline justify-between gap-2">
                <strong class="text-sm">{{ m.asientos }} asientos</strong>
                <span class="text-xs text-muted-color">{{ m.plantas === 2 ? "Dos plantas" : "Una planta" }}</span>
              </div>
              <div class="text-xs text-muted-color">{{ m.asientosB ? `${m.asientos - m.asientosB} clase A · ${m.asientosB} clase B` : "Solo clase A" }}</div>
              <div class="tpl__buses">
                <span v-for="bus in m.codigos.slice(0, MAX_CHIPS)" :key="bus" class="tpl__chip" :class="{ 'tpl__chip--match': coincide(bus) }">{{ bus }}</span>
                <span v-if="m.codigos.length > MAX_CHIPS" class="tpl__chip tpl__chip--more">+{{ m.codigos.length - MAX_CHIPS }}</span>
              </div>
            </div>
          </button>
        </li>
      </ul>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { etiquetaCroquis } from "@/core/salida/buses";
import type { BusOpcion, CroquisMolde } from "@/core/salida/types";
import BusMap from "@/shared/bus-map/BusMap.vue";

const MAX_CHIPS = 8;
const OPCIONES_PLANTAS = [
  { label: "Todas", value: 0 },
  { label: "1 planta", value: 1 },
  { label: "2 plantas", value: 2 },
];

const props = defineProps<{ croquis: CroquisMolde[]; buses?: BusOpcion[]; ariaLabel?: string; disabled?: boolean }>();
/** Id del molde elegido. */
const modelo = defineModel<number | null>({ required: true });

const elegido = computed(() => props.croquis.find((m) => m.id === modelo.value) ?? null);
const abierto = ref(false);
const busqueda = ref("");
const filtroPlantas = ref(0);

const normalizar = (t: string) => t.trim().toLowerCase();
const coincide = (codigo: string) => !!busqueda.value.trim() && normalizar(codigo).includes(normalizar(busqueda.value));

const visibles = computed(() =>
  props.croquis
    .map((m) => ({
      ...m,
      codigos: (props.buses ?? [])
        .filter((b) => b.croquisId === m.id)
        .map((b) => b.codigo)
        .sort((a, b) => Number(coincide(b)) - Number(coincide(a))),
    }))
    .filter((m) => !filtroPlantas.value || m.plantas === filtroPlantas.value)
    .filter((m) => !busqueda.value.trim() || m.codigos.some(coincide)),
);

function elegir(m: CroquisMolde) {
  modelo.value = m.id;
  abierto.value = false;
}
</script>

<style scoped>
/* Buscador y plantas fijos arriba: solo se desplaza la galería. */
.galeria__filtros {
  position: sticky;
  top: 0;
  z-index: 1;
  margin: 0 -0.25rem 1rem;
  padding: 0.25rem 0.25rem 0.75rem;
  background: var(--p-dialog-background);
  border-bottom: 1px solid var(--p-content-border-color);
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
.tpl:focus-visible,
.tpl--activo {
  border-color: var(--p-primary-color);
}
.tpl:hover,
.tpl:focus-visible {
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
  gap: 0.3rem;
  padding: 0.7rem 0.8rem 0.8rem;
}
</style>
