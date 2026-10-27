<!--
  Procesar encomiendas por salida (el "Procesar por Salida" del legado,
  ADR-035): la estación (la del usuario; de oficina, se elige) y el día dan
  las salidas que pasan por ahí. Elegida una, dos listas:

  - En bodega aquí: lo que esa salida puede llevar (de su empresa), primero
    lo que va a su destino; se marcan y se embarcan.
  - A bordo: lo que lleva; lo que baja aquí, resaltado y ya marcado para
    desembarcar.

  El lector de código de barras (o escribir el código + Enter) marca la
  encomienda en la lista donde esté. Angosto: una lista debajo de la otra.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <section class="panel flex flex-wrap items-end gap-3" aria-label="Estación y día">
      <label v-if="!contexto?.estacion" class="campo min-w-56 flex-1">
        <span>Estación</span>
        <estacion-select v-model="estacion" :estaciones="contexto?.estaciones ?? []" :show-clear="false" placeholder="Elija la estación" @update:model-value="cargarSalidas" />
      </label>
      <div v-else class="campo">
        <span>Estación</span>
        <span class="font-semibold">{{ contexto.estacion.nombre }}</span>
      </div>
      <div class="campo">
        <span>Día</span>
        <div class="flex items-center gap-1">
          <Button text rounded size="small" severity="secondary" aria-label="Día anterior" @click="moverDia(-1)"><template #icon><icon name="chevron-left" /></template></Button>
          <DatePicker v-model="dia" date-format="dd/mm/yy" show-icon size="small" class="w-40" @update:model-value="cargarSalidas" />
          <Button text rounded size="small" severity="secondary" aria-label="Día siguiente" @click="moverDia(1)"><template #icon><icon name="chevron-right" /></template></Button>
        </div>
      </div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <Fieldset :legend="`Salidas que pasan por ${nombreEstacion}`">
      <Skeleton v-if="cargandoSalidas" height="6rem" />
      <p v-else-if="!estacionId" class="m-0 text-sm text-muted-color">Elija la estación.</p>
      <p v-else-if="!salidas.length" class="m-0 text-sm text-muted-color">No hay salidas ese día.</p>
      <ul v-else class="salidas m-0 list-none p-0" role="listbox" aria-label="Salidas">
        <li v-for="s in salidas" :key="s.id">
          <button type="button" class="salida" :class="{ 'salida-on': salidaId === s.id }" role="option" :aria-selected="salidaId === s.id" @click="elegirSalida(s.id)">
            <span class="whitespace-nowrap font-semibold tabular-nums">{{ hora(s.hora) }}</span>
            <span class="min-w-0">
              <span class="block truncate">{{ s.ruta }}</span>
              <span class="block truncate text-xs text-muted-color">{{ s.empresa?.alias ?? s.empresa?.nombre }}<template v-if="s.bus"> · bus {{ s.bus }}</template> · {{ s.estado }}</span>
            </span>
            <span class="flex shrink-0 flex-col items-end gap-0.5 text-xs">
              <span v-if="s.bajanAqui" class="conteo conteo-baja" v-tooltip.left="'Bajan aquí'"><icon name="download" xs />{{ s.bajanAqui }}</span>
              <span v-if="s.porEmbarcar && s.sigue" class="conteo conteo-sube" v-tooltip.left="'Esperan en bodega para su destino'"><icon name="upload" xs />{{ s.porEmbarcar }}</span>
              <span v-if="s.aBordo" class="conteo" v-tooltip.left="'A bordo'"><icon name="inventory-2-outline" xs />{{ s.aBordo }}</span>
            </span>
          </button>
        </li>
      </ul>
    </Fieldset>

    <template v-if="salidaId">
      <section class="panel flex flex-wrap items-center gap-2" aria-label="Escanear">
        <IconField class="min-w-56 flex-1">
          <InputIcon><icon name="barcode-scanner" /></InputIcon>
          <InputText ref="lector" v-model="codigo" placeholder="Escanee o escriba el código y Enter" fluid @keydown.enter.prevent="escanear" />
        </IconField>
        <span v-if="avisoLector" class="text-sm" :class="avisoLector.ok ? 'text-green-700' : 'text-red-600'" aria-live="polite">{{ avisoLector.texto }}</span>
        <Button class="ml-auto" label="Manifiesto" size="small" severity="secondary" outlined :loading="descargando" v-tooltip.top="'Manifiesto de carga de la salida (PDF)'" @click="manifiesto">
          <template #icon><icon name="picture-as-pdf-outline" class="mr-1.5" color="text-primary" /></template>
        </Button>
      </section>

      <Skeleton v-if="cargandoProceso && !proceso" height="12rem" />
      <div v-else-if="proceso" class="grid grid-cols-1 items-start gap-3 @4xl:grid-cols-2">
        <!-- En bodega -->
        <Fieldset :legend="`En bodega aquí (${proceso.enBodega.length})`">
            <div class="flex flex-col gap-2">
            <div class="flex justify-end">
              <Button :label="`Embarcar (${marcadasBodega.length})`" size="small" :disabled="!marcadasBodega.length || !proceso.pasaPorAqui" :loading="enviando === 'embarcar'" @click="embarcar"><template #icon><icon name="upload" class="mr-1" /></template></Button>
            </div>
            <p v-if="!proceso.enBodega.length" class="m-0 py-3 text-center text-sm text-muted-color">Nada que esta salida pueda llevar desde aquí.</p>
            <ListaProceso v-else v-model="marcadas" :filas="bodegaOrdenada" modo="bodega" />
          </div>
        </Fieldset>

        <!-- A bordo -->
        <Fieldset :legend="`A bordo (${proceso.aBordo.length})`">
            <div class="flex flex-col gap-2">
            <div class="flex justify-end">
              <Button :label="`Desembarcar (${marcadasBordo.length})`" size="small" severity="secondary" :disabled="!marcadasBordo.length || !proceso.pasaPorAqui" :loading="enviando === 'desembarcar'" @click="desembarcar"><template #icon><icon name="download" class="mr-1" /></template></Button>
            </div>
            <p v-if="!proceso.aBordo.length" class="m-0 py-3 text-center text-sm text-muted-color">La salida no lleva encomiendas.</p>
            <ListaProceso v-else v-model="marcadas" :filas="bordoOrdenado" modo="bordo" />
          </div>
        </Fieldset>
      </div>
    </template>

    <Dialog :visible="fallidas.length > 0" modal header="Algunas no se pudieron procesar" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="!$event && (fallidas = [])">
      <ul class="m-0 flex list-none flex-col gap-2 p-0 text-sm">
        <li v-for="x in fallidas" :key="x.id"><b class="font-mono">{{ x.codigo }}</b>: {{ x.error }}</li>
      </ul>
      <template #footer><Button label="Entendido" @click="fallidas = []" /></template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, shallowRef } from "vue";
import { desembarcarEncomiendas, embarcarEncomiendas, fetchContextoEncomienda, fetchProcesoSalida, fetchSalidasEstacion } from "@/core/encomienda/api";
import { codigoAId } from "@/core/encomienda/reglas";
import type { ContextoEncomienda, Fallida, ProcesoSalida, SalidaEstacion } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { diaISO, hora } from "@/core/venta/modelo";
import { notify } from "@/core/notify";
import { fetchArchivoEncomienda } from "@/core/reporte/encomienda";
import ListaProceso from "./ListaProceso.vue";

const contexto = shallowRef<ContextoEncomienda | null>(null);
const estacion = ref<number | null>(null);
const dia = ref(new Date());
const salidas = ref<SalidaEstacion[]>([]);
const salidaId = ref<number | null>(null);
const proceso = ref<ProcesoSalida | null>(null);
const marcadas = ref<number[]>([]);
const cargandoSalidas = ref(false);
const cargandoProceso = ref(false);
const enviando = ref<"embarcar" | "desembarcar" | null>(null);
const error = ref("");
const fallidas = ref<Fallida[]>([]);
const codigo = ref("");
const avisoLector = ref<{ ok: boolean; texto: string } | null>(null);
const lector = ref<{ $el: HTMLInputElement } | null>(null);
const descargando = ref(false);

const estacionId = computed(() => contexto.value?.estacion?.id ?? estacion.value);
const nombreEstacion = computed(() => contexto.value?.estacion?.nombre ?? contexto.value?.estaciones.find((e) => e.id === estacion.value)?.nombre ?? "la estación");
/** Para la oficina, la estación elegida va en la consulta; con estación, el backend usa la del usuario. */
const estacionConsulta = computed(() => (contexto.value?.estacion ? null : estacion.value));

const bodegaOrdenada = computed(() => [...(proceso.value?.enBodega ?? [])].sort((a, b) => Number(b.llegaADestino) - Number(a.llegaADestino) || a.id - b.id));
const bordoOrdenado = computed(() => [...(proceso.value?.aBordo ?? [])].sort((a, b) => Number(b.bajaAqui) - Number(a.bajaAqui) || a.id - b.id));
const marcadasBodega = computed(() => marcadas.value.filter((id) => proceso.value?.enBodega.some((e) => e.id === id)));
const marcadasBordo = computed(() => marcadas.value.filter((id) => proceso.value?.aBordo.some((e) => e.id === id)));

onMounted(async () => {
  try {
    contexto.value = await fetchContextoEncomienda();
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
    return;
  }
  await cargarSalidas();
});

async function cargarSalidas() {
  salidaId.value = null;
  proceso.value = null;
  salidas.value = [];
  if (!estacionId.value) return;
  cargandoSalidas.value = true;
  error.value = "";
  try {
    salidas.value = await fetchSalidasEstacion(diaISO(dia.value), estacionConsulta.value);
  } catch (e) {
    error.value = errorVenta(e)?.error ?? (e instanceof Error ? e.message : String(e));
  } finally {
    cargandoSalidas.value = false;
  }
}

function moverDia(delta: number) {
  const d = new Date(dia.value);
  d.setDate(d.getDate() + delta);
  dia.value = d;
  void cargarSalidas();
}

async function elegirSalida(id: number) {
  salidaId.value = id;
  marcadas.value = [];
  await cargarProceso();
  // Lo que baja aquí ya va marcado para desembarcar.
  marcadas.value = proceso.value?.aBordo.filter((e) => e.bajaAqui).map((e) => e.id) ?? [];
  await nextTick();
  lector.value?.$el?.focus();
}

async function cargarProceso(silencioso = false) {
  if (!salidaId.value) return;
  cargandoProceso.value = true;
  try {
    proceso.value = await fetchProcesoSalida(salidaId.value, estacionConsulta.value, silencioso);
    const vivas = new Set([...proceso.value.enBodega, ...proceso.value.aBordo].map((e) => e.id));
    marcadas.value = marcadas.value.filter((id) => vivas.has(id));
  } catch (e) {
    error.value = errorVenta(e)?.error ?? (e instanceof Error ? e.message : String(e));
  } finally {
    cargandoProceso.value = false;
  }
}

/** Marca (o desmarca) la encomienda escaneada en la lista donde esté. */
function escanear() {
  const id = codigoAId(codigo.value);
  codigo.value = "";
  if (!id || !proceso.value) {
    avisoLector.value = { ok: false, texto: "Código inválido." };
    return;
  }
  const fila = [...proceso.value.enBodega, ...proceso.value.aBordo].find((e) => e.id === id);
  if (!fila) {
    avisoLector.value = { ok: false, texto: `La ${String(id).padStart(8, "0")} no está en bodega aquí ni en esta salida.` };
    return;
  }
  const yaMarcada = marcadas.value.includes(id);
  marcadas.value = yaMarcada ? marcadas.value.filter((x) => x !== id) : [...marcadas.value, id];
  avisoLector.value = { ok: true, texto: `${fila.codigo} ${yaMarcada ? "desmarcada" : "marcada"} (${fila.destino.nombre}).` };
}

async function embarcar() {
  if (!salidaId.value) return;
  enviando.value = "embarcar";
  try {
    const r = await embarcarEncomiendas(marcadasBodega.value, salidaId.value);
    if (r.embarcadas.length) notify.success(`${r.embarcadas.length} ${r.embarcadas.length === 1 ? "encomienda embarcada" : "encomiendas embarcadas"}`);
    fallidas.value = r.fallidas;
    marcadas.value = marcadas.value.filter((id) => !r.embarcadas.includes(id));
    await Promise.all([cargarProceso(true), refrescarConteos()]);
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? "No se pudo embarcar.");
  } finally {
    enviando.value = null;
  }
}

async function desembarcar() {
  enviando.value = "desembarcar";
  try {
    const r = await desembarcarEncomiendas(marcadasBordo.value, estacionConsulta.value);
    if (r.desembarcadas.length) notify.success(`${r.desembarcadas.length} ${r.desembarcadas.length === 1 ? "encomienda desembarcada" : "encomiendas desembarcadas"}`);
    fallidas.value = r.fallidas;
    marcadas.value = marcadas.value.filter((id) => !r.desembarcadas.includes(id));
    await Promise.all([cargarProceso(true), refrescarConteos()]);
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? "No se pudo desembarcar.");
  } finally {
    enviando.value = null;
  }
}

/** Manifiesto de carga de la salida elegida, en otra pestaña. */
async function manifiesto() {
  if (!salidaId.value) return;
  descargando.value = true;
  try {
    const url = URL.createObjectURL(await fetchArchivoEncomienda("manifiesto", `salida=${salidaId.value}`, "pdf"));
    window.open(url, "_blank", "noopener");
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  } catch (e) {
    notify.error(e instanceof Error && e.message ? e.message : "No se pudo generar el manifiesto.");
  } finally {
    descargando.value = false;
  }
}

/** Los contadores de cada salida, sin perder la elegida. */
async function refrescarConteos() {
  try {
    salidas.value = await fetchSalidasEstacion(diaISO(dia.value), estacionConsulta.value);
  } catch {
    // Los contadores se ven viejos hasta la próxima carga.
  }
}
</script>

<style scoped>
.panel {
  padding: 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}
.campo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.campo > span:first-child {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.salidas {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: 0.375rem;
  max-height: 18rem;
  overflow-y: auto;
}
.salida {
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
.salida:hover {
  background: var(--p-content-hover-background);
}
.salida-on {
  border-color: var(--p-primary-color);
  box-shadow: inset 0 0 0 1px var(--p-primary-color);
}
.conteo {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  border-radius: 999px;
  padding: 0 0.375rem;
  background: var(--p-surface-100);
  font-variant-numeric: tabular-nums;
}
.conteo-baja {
  background: var(--p-violet-100);
  color: var(--p-violet-700);
}
.conteo-sube {
  background: var(--p-blue-100);
  color: var(--p-blue-700);
}
</style>
