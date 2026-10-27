<!--
  Encomiendas pendientes de envío (ADR-035): lo que espera en la bodega de la
  estación para salir —recibidas y transbordos—, agrupado por destino y con
  los días que lleva esperando. Desde aquí se cambia la ruta por la que
  viajará (el «Primera ruta» del legado; la empresa no cambia) y se va a
  «Procesar por salida» a embarcarlas.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <section class="panel flex flex-wrap items-end gap-3" aria-label="Estación">
      <label v-if="!contexto?.estacion" class="campo min-w-56 flex-1 @3xl:max-w-xs">
        <span>Estación</span>
        <estacion-select v-model="estacion" :estaciones="contexto?.estaciones ?? []" :show-clear="false" placeholder="Elija la estación" @update:model-value="cargar()" />
      </label>
      <div v-else class="campo">
        <span>Estación</span>
        <span class="font-semibold">{{ contexto.estacion.nombre }}</span>
      </div>
      <IconField v-if="filas.length > 5" class="min-w-56 flex-1 @3xl:max-w-sm">
        <InputIcon><icon name="search" /></InputIcon>
        <InputText v-model="busqueda" placeholder="Filtrar por código, cliente o destino" aria-label="Filtrar" fluid />
      </IconField>
      <div class="ml-auto flex items-center gap-1">
        <Button as="router-link" to="/encomiendas/salida" label="Procesar por salida" size="small" severity="secondary" outlined>
          <template #icon><icon name="local-shipping-outline" class="mr-1.5" color="text-primary" /></template>
        </Button>
        <Button severity="secondary" text :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar()">
          <template #icon><icon name="refresh" /></template>
        </Button>
      </div>
    </section>

    <section v-if="filas.length" class="totales panel" aria-label="Totales">
      <div><span>Encomiendas</span><strong>{{ filas.length }}</strong></div>
      <div><span>Bultos</span><strong>{{ totalBultos }}</strong></div>
      <div><span>Destinos</span><strong>{{ grupos.length }}</strong></div>
      <div :class="{ alerta: atrasadas }"><span>Con más de {{ DIAS_ALERTA }} días</span><strong>{{ atrasadas }}</strong></div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="cargando && !filas.length" height="10rem" />
    <div v-else-if="!error && !visibles.length" class="flex flex-col items-center gap-2 p-6 text-muted-color">
      <icon name="inventory-2-outline" size="2rem" />
      <span class="text-sm">{{ !estacionId ? "Elija la estación." : filas.length ? "Nada coincide con el filtro." : "No hay encomiendas esperando salir." }}</span>
    </div>

    <Fieldset v-for="g in grupos" :key="g.destino.id" :legend="`${g.destino.nombre} · ${g.filas.length} ${g.filas.length === 1 ? 'encomienda' : 'encomiendas'}`">
      <ul class="m-0 flex list-none flex-col gap-1 p-0">
        <li v-for="f in g.filas" :key="f.id" class="fila">
          <span class="min-w-0">
            <span class="flex flex-wrap items-baseline gap-x-2">
              <b class="font-mono">{{ f.codigo }}</b>
              <span class="truncate">{{ f.remitente.nombre }} → {{ f.destinatario.nombre }}</span>
            </span>
            <span class="block truncate text-xs text-muted-color">{{ f.bultos }} {{ f.bultos === 1 ? "bulto" : "bultos" }} · {{ f.contenido }} · {{ etiquetaModalidad(f.modalidad) }}<template v-if="f.estado === 'desembarcada'"> · transbordo</template></span>
          </span>
          <span class="ruta text-xs">
            <span class="text-muted-color">{{ f.empresa.alias ?? f.empresa.nombre }}</span>
            <span class="truncate">{{ f.ruta?.nombre ?? "Sin ruta" }}</span>
          </span>
          <span class="flex shrink-0 items-center gap-1">
            <Tag :severity="antiguedad(f.creada) > DIAS_ALERTA ? 'danger' : antiguedad(f.creada) > 0 ? 'warn' : 'secondary'" class="!text-xs" :value="dias(f)" />
            <Button v-if="contexto?.permisos.registrar" text rounded size="small" severity="secondary" aria-label="Cambiar ruta" v-tooltip.left="'Cambiar ruta'" @click="abrirRuta(f)">
              <template #icon><icon name="alt-route" /></template>
            </Button>
          </span>
        </li>
      </ul>
    </Fieldset>

    <Dialog :visible="cambio !== null" modal header="Cambiar ruta" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="!$event && (cambio = null)">
      <div v-if="cambio" class="flex flex-col gap-3">
        <p class="m-0 text-sm">
          <b class="font-mono">{{ cambio.fila.codigo }}</b> de {{ cambio.fila.ubicacion?.nombre }} a {{ cambio.fila.destino.nombre }}, de
          <b>{{ cambio.fila.empresa.nombre }}</b>. Viaja solo en salidas de su empresa.
        </p>
        <Skeleton v-if="cambio.cargando" height="6rem" />
        <p v-else-if="!cambio.rutas.length" class="m-0 text-sm text-muted-color">Ninguna ruta pasa por aquí y luego por su destino.</p>
        <div v-else class="flex flex-col gap-1.5" role="radiogroup" aria-label="Rutas">
          <label v-for="r in cambio.rutas" :key="r.id" class="opcion" :class="{ 'opcion-on': cambio.ruta === r.id }">
            <RadioButton v-model="cambio.ruta" :value="r.id" name="ruta-encomienda" />
            <span class="min-w-0 flex-1">
              <span class="block truncate">{{ r.nombre }}<span v-if="r.id === cambio.fila.ruta?.id" class="text-xs text-muted-color"> (actual)</span></span>
              <span class="block truncate text-xs" :class="operaSuEmpresa(r) ? 'text-muted-color' : 'text-amber-700'">
                {{ operaSuEmpresa(r) ? `Con salidas de ${cambio.fila.empresa.alias ?? cambio.fila.empresa.nombre} esta semana` : "Su empresa no tiene salidas en esta ruta esta semana" }}
              </span>
            </span>
          </label>
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="cambio = null" />
        <Button label="Guardar" :loading="guardando" :disabled="!cambio?.ruta || cambio.ruta === cambio.fila.ruta?.id" @click="guardarRuta" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from "vue";
import { cambiarRutaEncomienda, fetchContextoEncomienda, fetchPendientesEnvio, fetchRutasEncomienda } from "@/core/encomienda/api";
import { antiguedad, etiquetaModalidad, porDestino } from "@/core/encomienda/reglas";
import type { ContextoEncomienda, FilaEncomienda, RutaOpcion } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import { notify } from "@/core/notify";

/** Desde cuántos días esperando se resalta. */
const DIAS_ALERTA = 2;

const contexto = shallowRef<ContextoEncomienda | null>(null);
const estacion = ref<number | null>(null);
const filas = ref<FilaEncomienda[]>([]);
const busqueda = ref("");
const cargando = ref(false);
const guardando = ref(false);
const error = ref("");
const cambio = ref<{ fila: FilaEncomienda; rutas: RutaOpcion[]; ruta: number | null; cargando: boolean } | null>(null);

const estacionId = computed(() => contexto.value?.estacion?.id ?? estacion.value);
const estacionConsulta = computed(() => (contexto.value?.estacion ? null : estacion.value));
const visibles = computed(() => {
  const q = busqueda.value.trim().toLowerCase();
  if (!q) return filas.value;
  return filas.value.filter((f) => [f.codigo, f.remitente.nombre, f.destinatario.nombre, f.destino.nombre, f.contenido].some((t) => t.toLowerCase().includes(q)));
});
const grupos = computed(() => porDestino(visibles.value));
const totalBultos = computed(() => filas.value.reduce((n, f) => n + f.bultos, 0));
const atrasadas = computed(() => filas.value.filter((f) => antiguedad(f.creada) > DIAS_ALERTA).length);

const dias = (f: FilaEncomienda) => {
  const n = antiguedad(f.creada);
  return n === 0 ? "Hoy" : n === 1 ? "Ayer" : `${n} días`;
};
const operaSuEmpresa = (r: RutaOpcion) => r.empresas.some((e) => e.id === cambio.value?.fila.empresa.id);

onMounted(async () => {
  try {
    contexto.value = await fetchContextoEncomienda();
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
    return;
  }
  await cargar();
});

async function cargar(silencioso = false) {
  if (!estacionId.value) return;
  cargando.value = true;
  error.value = "";
  try {
    filas.value = await fetchPendientesEnvio(estacionConsulta.value, silencioso);
  } catch (e) {
    error.value = errorVenta(e)?.error ?? (e instanceof Error ? e.message : String(e));
  } finally {
    cargando.value = false;
  }
}

async function abrirRuta(f: FilaEncomienda) {
  const desde = f.ubicacion?.id ?? estacionId.value;
  if (!desde) return;
  cambio.value = { fila: f, rutas: [], ruta: f.ruta?.id ?? null, cargando: true };
  try {
    const rutas = await fetchRutasEncomienda(desde, f.destino.id);
    if (cambio.value?.fila.id === f.id) cambio.value = { ...cambio.value, rutas, cargando: false };
  } catch {
    notify.error("No se pudieron cargar las rutas.");
    cambio.value = null;
  }
}

async function guardarRuta() {
  const c = cambio.value;
  if (!c?.ruta) return;
  guardando.value = true;
  try {
    await cambiarRutaEncomienda(c.fila.id, c.ruta);
    notify.success(`Ruta de ${c.fila.codigo} cambiada`);
    cambio.value = null;
    await cargar(true);
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? "No se pudo cambiar la ruta.");
  } finally {
    guardando.value = false;
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
.totales {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.5rem;
}
.totales > div {
  display: flex;
  flex-direction: column;
}
.totales span {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.totales strong {
  font-size: 1.25rem;
  font-variant-numeric: tabular-nums;
}
.totales .alerta strong {
  color: var(--p-red-600);
}
.fila {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.25rem 0.75rem;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.ruta {
  display: none;
  flex-direction: column;
  min-width: 0;
}
@container (width >= 48rem) {
  .fila {
    grid-template-columns: minmax(0, 1fr) minmax(0, 16rem) auto;
  }
  .ruta {
    display: flex;
  }
}
.opcion {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  cursor: pointer;
}
.opcion-on {
  border-color: var(--p-primary-color);
  background: var(--p-highlight-background);
}
</style>
