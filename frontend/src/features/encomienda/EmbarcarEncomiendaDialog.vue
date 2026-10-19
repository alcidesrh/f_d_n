<!--
  Embarcar una encomienda en bodega: las salidas de su empresa que pasan por
  la estación donde está ese día (por la hora en esa parada), primero las que
  la llevan hasta su destino; las demás la acercan (transbordo).
-->
<template>
  <Dialog :visible="visible" modal header="Embarcar encomienda" class="w-[min(36rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <div v-if="encomienda" class="flex flex-col gap-3">
      <p class="m-0 text-sm">
        <b class="font-mono">{{ encomienda.codigo }}</b> · en {{ encomienda.ubicacion?.nombre }} → {{ encomienda.destino.nombre }} · {{ encomienda.bultos }} {{ encomienda.bultos === 1 ? "bulto" : "bultos" }}
      </p>
      <div class="flex items-center gap-2">
        <Button text rounded size="small" severity="secondary" aria-label="Día anterior" @click="moverDia(-1)"><template #icon><icon name="chevron-left" /></template></Button>
        <DatePicker v-model="dia" date-format="dd/mm/yy" show-icon size="small" class="flex-1" @update:model-value="cargar" />
        <Button text rounded size="small" severity="secondary" aria-label="Día siguiente" @click="moverDia(1)"><template #icon><icon name="chevron-right" /></template></Button>
      </div>
      <Skeleton v-if="cargando" height="8rem" />
      <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
      <p v-else-if="!opciones.length" class="m-0 py-4 text-center text-sm text-muted-color">No hay salidas de {{ encomienda.empresa.alias ?? encomienda.empresa.nombre }} que sigan desde {{ encomienda.ubicacion?.nombre }} ese día.</p>
      <ul v-else class="m-0 flex max-h-80 list-none flex-col gap-1 overflow-y-auto p-0" role="listbox" aria-label="Salidas">
        <li v-for="s in opciones" :key="s.id">
          <button type="button" class="opcion" :class="{ 'opcion-on': elegida === s.id }" role="option" :aria-selected="elegida === s.id" @click="elegida = s.id">
            <span class="whitespace-nowrap font-semibold tabular-nums">{{ hora(s.hora) }}</span>
            <span class="min-w-0 truncate">{{ s.ruta }}<span v-if="s.bus" class="text-muted-color"> · bus {{ s.bus }}</span></span>
            <Tag v-if="s.llegaADestino" value="Llega al destino" severity="success" class="shrink-0" />
            <Tag v-else value="Transbordo" severity="warn" class="shrink-0" />
          </button>
        </li>
      </ul>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button label="Embarcar" :disabled="!elegida" :loading="enviando" @click="embarcar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { embarcarEncomiendas, fetchSalidasEstacion } from "@/core/encomienda/api";
import type { FilaEncomienda, SalidaEstacion } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { diaISO, hora } from "@/core/venta/modelo";
import { notify } from "@/core/notify";

const props = defineProps<{ visible: boolean; encomienda: FilaEncomienda | null }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; listo: [] }>();

const dia = ref(new Date());
const salidas = ref<SalidaEstacion[]>([]);
const elegida = ref<number | null>(null);
const cargando = ref(false);
const enviando = ref(false);
const error = ref("");

/** De su empresa, que sigan desde ahí y no cerradas; primero las que llegan al destino. */
const opciones = computed(() =>
  salidas.value
    .filter((s) => s.sigue && s.empresa?.id === props.encomienda?.empresa.id && !["finalizada", "cancelada"].includes(s.estado))
    .sort((a, b) => Number(b.llegaADestino) - Number(a.llegaADestino) || a.hora.localeCompare(b.hora)),
);

async function cargar() {
  const e = props.encomienda;
  if (!e?.ubicacion) return;
  cargando.value = true;
  error.value = "";
  elegida.value = null;
  try {
    salidas.value = await fetchSalidasEstacion(diaISO(dia.value), e.ubicacion.id, e.destino.id);
    elegida.value = opciones.value.find((s) => s.llegaADestino && new Date(s.hora).getTime() > Date.now())?.id ?? null;
  } catch (x) {
    error.value = errorVenta(x)?.error ?? "No se pudieron cargar las salidas.";
  } finally {
    cargando.value = false;
  }
}

function moverDia(delta: number) {
  const d = new Date(dia.value);
  d.setDate(d.getDate() + delta);
  dia.value = d;
  void cargar();
}

async function embarcar() {
  if (!props.encomienda || !elegida.value) return;
  enviando.value = true;
  try {
    const r = await embarcarEncomiendas([props.encomienda.id], elegida.value);
    if (r.fallidas.length) {
      notify.error(r.fallidas[0]?.error ?? "No se pudo.");
      return;
    }
    notify.success(`Encomienda ${props.encomienda.codigo} embarcada`);
    emit("listo");
    emit("update:visible", false);
  } catch (x) {
    notify.error(errorVenta(x)?.error ?? "No se pudo embarcar.");
  } finally {
    enviando.value = false;
  }
}
</script>

<style scoped>
.opcion {
  display: grid;
  grid-template-columns: 4.75rem minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.opcion:hover {
  background: var(--p-content-hover-background);
}
.opcion-on {
  border-color: var(--p-primary-color);
  box-shadow: inset 0 0 0 1px var(--p-primary-color);
}
</style>
