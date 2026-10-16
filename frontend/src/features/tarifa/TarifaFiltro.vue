<template>
  <div class="tarifa-filtro">
    <div class="tarifa-filtro__trayecto" role="group" aria-label="Trayecto">
      <label class="tarifa-filtro__campo">
        <span>Origen</span>
        <AutoComplete :model-value="filtro.origen" :suggestions="sugerencias.origen" option-label="label" dropdown force-selection show-clear :delay="300" placeholder="Enclave…" empty-search-message="Sin coincidencias" size="small" fluid aria-label="Enclave de origen del trayecto" @complete="buscar('origen', 'Enclave', $event.query)" @update:model-value="elegir('origen', $event)" />
      </label>
      <button type="button" class="tarifa-filtro__invertir tap-target" aria-label="Invertir origen y destino" v-tooltip.bottom="'Invertir'" :disabled="!filtro.origen && !filtro.destino" @click="invertir">
        <icon name="swap-horiz" size="1.25rem" />
      </button>
      <label class="tarifa-filtro__campo">
        <span>Destino</span>
        <AutoComplete :model-value="filtro.destino" :suggestions="sugerencias.destino" option-label="label" dropdown force-selection show-clear :delay="300" placeholder="Enclave…" empty-search-message="Sin coincidencias" size="small" fluid aria-label="Enclave de destino del trayecto" @complete="buscar('destino', 'Enclave', $event.query)" @update:model-value="elegir('destino', $event)" />
      </label>
    </div>

    <div class="tarifa-filtro__resto">
      <label v-for="campo in CATALOGOS" :key="campo.key" class="tarifa-filtro__campo">
        <span>{{ campo.label }}</span>
        <Select :model-value="filtro[campo.key]?.value ?? null" :options="opciones[campo.key]" option-label="label" option-value="value" placeholder="Cualquiera" :filter="opciones[campo.key].length > 8" show-clear size="small" fluid :loading="cargando" :aria-label="campo.label" @update:model-value="elegirDeCatalogo(campo.key, $event)" />
      </label>

      <label class="tarifa-filtro__campo">
        <span>Usuario</span>
        <AutoComplete :model-value="filtro.usuario" :suggestions="sugerencias.usuario" option-label="label" dropdown force-selection show-clear :delay="300" placeholder="Escriba para buscar…" empty-search-message="Sin coincidencias" size="small" fluid aria-label="Usuario que registró la tarifa" @complete="buscar('usuario', 'Usuario', $event.query)" @update:model-value="elegir('usuario', $event)" />
      </label>

      <div class="tarifa-filtro__campo">
        <span id="tarifa-filtro-horario">Horario</span>
        <div class="tarifa-filtro__horario" role="group" aria-labelledby="tarifa-filtro-horario">
          <InputText type="time" :model-value="filtro.horaDesde ?? ''" size="small" class="tabular-nums" aria-label="Hora desde" @change="elegirHora('horaDesde', $event)" />
          <span aria-hidden="true">–</span>
          <InputText type="time" :model-value="filtro.horaHasta ?? ''" size="small" class="tabular-nums" aria-label="Hora hasta" @change="elegirHora('horaHasta', $event)" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Filtro a la medida del listado de tarifas (contrato
 * `core/entities/listCustomFilter`): enclaves de origen y destino del
 * trayecto, empresa, clase de bus, bus, usuario y horario. Cada cambio se
 * aplica al instante (las horas al terminar de escribirlas); todos los
 * términos se cumplen a la vez. Traducción y resumen en `core/tarifa/filtro`.
 */
import { onMounted, reactive, ref, watch } from "vue";
import type { CustomListFilterState } from "@/core/entities/listCustomFilter";
import { getEntity } from "@/core/entities/registry";
import { graphql } from "@/core/graphql/client";
import type { AgnosticOption } from "@/core/graphql/types";
import { estadoFiltroTarifa, filtroTarifaDe, type FiltroTarifa, type OpcionFiltro } from "@/core/tarifa/filtro";

type Catalogo = "empresa" | "busClase" | "bus";
type Buscable = "origen" | "destino" | "usuario";

const CATALOGOS: Array<{ key: Catalogo; label: string; entidad: string }> = [
  { key: "empresa", label: "Empresa", entidad: "Empresa" },
  { key: "busClase", label: "Clase de bus", entidad: "BusClase" },
  { key: "bus", label: "Bus", entidad: "Bus" },
];

const props = defineProps<{ entity?: string; applied: Record<string, unknown> | null }>();
const emit = defineEmits<{ apply: [state: CustomListFilterState | null] }>();

const filtro = ref<FiltroTarifa>(filtroTarifaDe(props.applied));
// Lo aplicado cambió desde fuera (filtro de columna, restablecer): el panel lo refleja.
watch(
  () => props.applied,
  (applied) => (filtro.value = filtroTarifaDe(applied)),
);

function aplicar(cambio: Partial<FiltroTarifa>) {
  filtro.value = { ...filtro.value, ...cambio };
  emit("apply", estadoFiltroTarifa(filtro.value));
}

const aOpcion = (option: AgnosticOption): OpcionFiltro => ({ value: option.value ?? option.id ?? "", label: option.label });

// Catálogos pequeños: la lista completa ---------------------------------------
const opciones = reactive<Record<Catalogo, OpcionFiltro[]>>({ empresa: [], busClase: [], bus: [] });
const cargando = ref(true);

onMounted(async () => {
  try {
    await Promise.all(
      CATALOGOS.map(async ({ key, entidad }) => {
        opciones[key] = (await getEntity(entidad).loadFullList()).map(aOpcion);
      }),
    );
  } finally {
    cargando.value = false;
  }
});

function elegirDeCatalogo(campo: Catalogo, value: string | null) {
  aplicar({ [campo]: value ? (opciones[campo].find((o) => o.value === value) ?? null) : null });
}

// Muchos registros: se buscan en el servidor al teclear -------------------------
const sugerencias = reactive<Record<Buscable, OpcionFiltro[]>>({ origen: [], destino: [], usuario: [] });
const ultima: Partial<Record<Buscable, string>> = {};

async function buscar(campo: Buscable, entidad: string, query: string) {
  ultima[campo] = query;
  const encontradas = (await graphql.agnosticSearch(entidad, { search: query.trim() })).map(aOpcion);
  if (ultima[campo] === query) sugerencias[campo] = encontradas;
}

/** Solo una opción elegida (o quitarla) cambia el filtro; el tecleo no. */
function elegir(campo: Buscable, valor: unknown) {
  if (valor == null || valor === "") {
    if (filtro.value[campo]) aplicar({ [campo]: null });
  } else if (typeof valor === "object") aplicar({ [campo]: valor as OpcionFiltro });
}

function invertir() {
  aplicar({ origen: filtro.value.destino, destino: filtro.value.origen });
}

function elegirHora(campo: "horaDesde" | "horaHasta", event: Event) {
  const valor = (event.target as HTMLInputElement).value || null;
  if (valor !== filtro.value[campo]) aplicar({ [campo]: valor });
}
</script>

<style scoped>
.tarifa-filtro {
  display: flex;
  flex-wrap: wrap;
  gap: 0.625rem 1rem;
}
.tarifa-filtro__trayecto {
  display: flex;
  min-width: 0;
  flex: 1 1 26rem;
  align-items: flex-end;
  gap: 0.25rem;
}
.tarifa-filtro__trayecto > .tarifa-filtro__campo {
  flex: 1 1 0;
}
.tarifa-filtro__resto {
  display: grid;
  min-width: 0;
  flex: 999 1 36rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 10rem), 1fr));
  gap: 0.625rem 0.75rem;
}
.tarifa-filtro__campo {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.tarifa-filtro__invertir {
  display: inline-grid;
  height: 2rem;
  width: 2rem;
  flex: none;
  place-items: center;
  border-radius: 999px;
  color: var(--p-primary-color);
  cursor: pointer;
}
.tarifa-filtro__invertir:hover:not(:disabled) {
  background: color-mix(in srgb, var(--p-primary-color) 12%, transparent);
}
.tarifa-filtro__invertir:disabled {
  opacity: 0.4;
  cursor: default;
}
.tarifa-filtro__horario {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}
.tarifa-filtro__horario :deep(input) {
  min-width: 0;
  flex: 1 1 0;
}
</style>
