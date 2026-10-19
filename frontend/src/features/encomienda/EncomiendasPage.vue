<!--
  Encomiendas (el "Buscar" del legado, ADR-035): filtros arriba (se aplican
  al cambiar; el texto, al dejar de escribir), atajos de la estación
  («En bodega aquí», «Por entregar aquí»), el panel de acciones sobre la
  elegida y la lista paginada en el servidor. Cada acción dice por qué no se
  puede si no se puede. `?id=` abre esa encomienda.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <section class="panel grid grid-cols-2 items-end gap-x-3 gap-y-2 @2xl:grid-cols-4 @5xl:grid-cols-6" aria-label="Filtros">
      <label class="campo col-span-2">
        <span>Código o cliente</span>
        <IconField>
          <InputIcon><icon name="barcode-scanner" /></InputIcon>
          <InputText v-model="borrador.q" placeholder="Código, nombre o NIT" size="small" fluid />
        </IconField>
      </label>
      <label class="campo col-span-2">
        <span>Registrada <span class="font-normal">(rango)</span></span>
        <DatePicker v-model="rango" selection-mode="range" :manual-input="false" date-format="dd/mm/yy" show-icon show-button-bar size="small" placeholder="Cualquier fecha" fluid @hide="completarRango" />
      </label>
      <label class="campo">
        <span>Origen</span>
        <estacion-select v-model="borrador.origen" :estaciones="contexto?.estaciones ?? []" placeholder="Todos" size="small" />
      </label>
      <label class="campo">
        <span>Destino</span>
        <estacion-select v-model="borrador.destino" :estaciones="contexto?.estaciones ?? []" placeholder="Todos" size="small" />
      </label>
      <label class="campo col-span-2 @2xl:col-span-1">
        <span>En bodega en</span>
        <estacion-select v-model="borrador.ubicacion" :estaciones="contexto?.estaciones ?? []" placeholder="Cualquiera" size="small" />
      </label>
      <div class="campo col-span-2 @2xl:col-span-3 @5xl:col-span-3">
        <span>Estado</span>
        <div class="chips" role="group" aria-label="Estado">
          <button v-for="e in ESTADOS" :key="e.valor" type="button" class="chip" :class="{ 'chip-on': borrador.estados.includes(e.valor) }" :aria-pressed="borrador.estados.includes(e.valor)" @click="alternarEstado(e.valor)">{{ e.etiqueta }}</button>
        </div>
      </div>
      <div class="campo col-span-2">
        <span>Modalidad</span>
        <div class="chips" role="group" aria-label="Modalidad">
          <button v-for="m in MODALIDADES" :key="m.valor" type="button" class="chip" :class="{ 'chip-on': borrador.modalidad === m.valor }" :aria-pressed="borrador.modalidad === m.valor" @click="borrador.modalidad = borrador.modalidad === m.valor ? null : m.valor">{{ m.etiqueta }}</button>
        </div>
      </div>
      <div class="col-span-2 flex flex-wrap justify-end gap-1 @2xl:col-span-4 @5xl:col-span-6">
        <template v-if="contexto?.estacion">
          <Button label="En bodega aquí" size="small" text @click="atajo('bodega')"><template #icon><icon name="warehouse-outline" class="mr-1" /></template></Button>
          <Button label="Por entregar aquí" size="small" text @click="atajo('entregar')"><template #icon><icon name="move-to-inbox-outline" class="mr-1" /></template></Button>
        </template>
        <Button label="Limpiar" severity="secondary" text size="small" :disabled="!modificado" @click="limpiar"><template #icon><icon name="filter-alt-off-outline" class="mr-1" /></template></Button>
      </div>
    </section>

    <div class="acciones" role="toolbar" aria-label="Acciones de la encomienda elegida">
      <div v-if="elegida" class="flex min-w-0 items-center gap-2">
        <span class="font-mono font-semibold">{{ elegida.codigo }}</span>
        <span class="truncate text-sm text-muted-color">{{ elegida.origen.nombre }} → {{ elegida.destino.nombre }} · {{ elegida.destinatario.nombre }}</span>
        <Button text rounded size="small" severity="secondary" aria-label="Quitar selección" @click="elegida = null"><template #icon><icon name="close" /></template></Button>
      </div>
      <div class="flex flex-wrap gap-1.5">
        <template v-for="b in BOTONES" :key="b.accion">
          <span v-if="acciones[b.accion].visible" v-tooltip.bottom="acciones[b.accion].motivo ?? b.ayuda">
            <Button :severity="b.severidad ?? 'secondary'" :outlined="!b.severidad" size="small" :disabled="!acciones[b.accion].habilitada" :aria-label="b.etiqueta" @click="ejecutar(b.accion)">
              <icon :name="b.icono" />
              <span class="hidden @xl:inline">{{ b.etiqueta }}</span>
            </Button>
          </span>
        </template>
        <Button size="small" class="ml-auto" @click="router.push({ name: 'encomienda-registrar' })"><icon name="add" /><span class="hidden @xl:inline">Registrar</span></Button>
      </div>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataGrid :columns="COLUMNAS" :rows="datos?.items ?? []" :row-key="(r) => String((r as FilaEncomienda).id)" :loading="cargando" :selected-keys="seleccion" density="compact" :card-fields="4" max-height="calc(100dvh - 18rem)" row-clickable :row-class="claseFila" @row-click="elegir($event as FilaEncomienda)">
      <template #cell="{ row, column }">
        <span v-if="column.key === 'estado'" class="estado" :class="`estado--${f(row).estado}`" :title="etiquetaEstado(f(row).estado)">{{ inicialEstado(f(row).estado) }}</span>
        <GridText v-else :text="texto(f(row), column.key)" />
      </template>
      <template #card="{ row }">
        <div class="tarjeta">
          <div class="flex items-center justify-between gap-2">
            <span class="font-mono font-semibold">{{ f(row).codigo }}</span>
            <span class="estado" :class="`estado--${f(row).estado}`" :title="etiquetaEstado(f(row).estado)">{{ inicialEstado(f(row).estado) }}</span>
          </div>
          <div class="truncate text-sm">{{ f(row).origen.nombre }} → {{ f(row).destino.nombre }}</div>
          <div class="truncate text-xs text-muted-color">{{ texto(f(row), "donde") }}</div>
          <div class="flex justify-between gap-2 text-xs">
            <span class="truncate">{{ f(row).destinatario.nombre }}</span>
            <span class="shrink-0 tabular-nums">{{ texto(f(row), "total") }}</span>
          </div>
        </div>
      </template>
      <template #empty>
        <icon name="inventory-2-outline" size="2rem" />
        <span>No hay encomiendas con estos filtros.</span>
      </template>
    </DataGrid>

    <div class="flex justify-end">
      <Paginator v-if="datos" :rows="porPagina" :first="(pagina - 1) * porPagina" :total-records="datos.total" :rows-per-page-options="contexto?.porPagina ?? [10, 25, 50, 100]" template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown" current-page-report-template="{first}–{last} de {totalRecords}" @page="paginar" />
    </div>

    <ConsultarEncomiendaDialog :visible="dialogo === 'consultar'" :id="consultarId" @update:visible="cerrar" @bitacora="bitacoraId = consultarId" />
    <EmbarcarEncomiendaDialog :visible="dialogo === 'embarcar'" :encomienda="elegida" @update:visible="cerrar" @listo="cargar" />
    <DesembarcarEncomiendaDialog :visible="dialogo === 'desembarcar'" :encomienda="elegida" :estacion-usuario="contexto?.estacion ?? null" :estaciones="contexto?.estaciones ?? []" @update:visible="cerrar" @listo="cargar" />
    <EntregarEncomiendaDialog :visible="dialogo === 'entregar'" :encomienda="elegida" :tipos-pago="contexto?.tiposPago ?? []" :monedas="contexto?.monedas ?? []" @update:visible="cerrar" @listo="cargar" />
    <AnularEncomiendaDialog :visible="dialogo === 'anular'" :encomienda="elegida" @update:visible="cerrar" @listo="cargar" />
    <BitacoraDialog v-if="bitacoraId !== null" :visible="true" tipo="encomienda" :id="bitacoraId" @update:visible="(v: boolean) => !v && (bitacoraId = null)" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, toRaw, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { fetchContextoEncomienda, fetchEncomienda, fetchEncomiendas } from "@/core/encomienda/api";
import { aQuery, disponibilidad, ESTADOS, etiquetaEstado, etiquetaModalidad, filtroInicial, inicialEstado, MODALIDADES, type AccionEncomienda, type FiltroEncomiendas } from "@/core/encomienda/reglas";
import type { ContextoEncomienda, EstadoEncomienda, FilaEncomienda, PaginaEncomiendas } from "@/core/encomienda/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { deDia } from "@/core/salida/filtro";
import { diaISO, fecha, hora } from "@/core/venta/modelo";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import { imprimirGuias, imprimirRecibo } from "@/shared/encomienda/impresion";
import AnularEncomiendaDialog from "./AnularEncomiendaDialog.vue";
import ConsultarEncomiendaDialog from "./ConsultarEncomiendaDialog.vue";
import DesembarcarEncomiendaDialog from "./DesembarcarEncomiendaDialog.vue";
import EmbarcarEncomiendaDialog from "./EmbarcarEncomiendaDialog.vue";
import EntregarEncomiendaDialog from "./EntregarEncomiendaDialog.vue";

const COLUMNAS: GridColumn[] = [
  { key: "codigo", label: "Código", width: "6.5rem" },
  { key: "creada", label: "Registrada", width: "9.5rem" },
  { key: "estado", label: "Estado", width: "5rem", align: "center" },
  { key: "modalidad", label: "Modalidad", width: "7rem" },
  { key: "origen", label: "Origen", width: "9rem" },
  { key: "destino", label: "Destino", width: "9rem" },
  { key: "donde", label: "Dónde está", width: "11rem" },
  { key: "remitente", label: "Remitente", width: "12rem" },
  { key: "destinatario", label: "Destinatario", width: "12rem" },
  { key: "bultos", label: "Bultos", width: "4.5rem", align: "end" },
  { key: "contenido", label: "Contenido", width: "2fr", minWidth: "12rem" },
  { key: "total", label: "Total", width: "7rem", align: "end" },
  { key: "empresa", label: "Empresa", width: "8rem" },
  { key: "usuario", label: "Usuario", width: "8rem" },
];

const BOTONES: Array<{ accion: AccionEncomienda; etiqueta: string; icono: string; ayuda: string; severidad?: "danger" }> = [
  { accion: "consultar", etiqueta: "Consultar", icono: "visibility-outline", ayuda: "Ver todos los datos de la encomienda" },
  { accion: "embarcar", etiqueta: "Embarcar", icono: "upload", ayuda: "Subirla a una salida" },
  { accion: "desembarcar", etiqueta: "Desembarcar", icono: "download", ayuda: "Bajarla del bus en esta estación" },
  { accion: "entregar", etiqueta: "Entregar", icono: "how-to-reg-outline", ayuda: "Entregarla a quien la retira (y cobrarla si es por cobrar)" },
  { accion: "anular", etiqueta: "Anular", icono: "cancel-outline", ayuda: "Anularla (y su factura) si no ha viajado" },
  { accion: "recibo", etiqueta: "Recibo", icono: "receipt-long-outline", ayuda: "Reimprimir el recibo" },
  { accion: "guias", etiqueta: "Guías", icono: "label-outline", ayuda: "Reimprimir las guías de los bultos" },
  { accion: "bitacora", etiqueta: "Bitácora", icono: "history", ayuda: "Historial de la encomienda" },
];

type Dialogo = "consultar" | "embarcar" | "desembarcar" | "entregar" | "anular";

const route = useRoute();
const router = useRouter();

const contexto = shallowRef<ContextoEncomienda | null>(null);
const datos = shallowRef<PaginaEncomiendas | null>(null);
const cargando = ref(false);
const error = ref("");
const borrador = reactive<FiltroEncomiendas>(filtroInicial());
const aplicado = ref<FiltroEncomiendas>(filtroInicial());
const pagina = ref(1);
const porPagina = ref(25);
const elegida = ref<FilaEncomienda | null>(null);
const dialogo = ref<Dialogo | null>(null);
const consultarId = ref<number | null>(null);
const bitacoraId = ref<number | null>(null);

// —— Filtros ——
const rango = computed<Array<Date | null> | null>({
  get: () => (borrador.desde ? [deDia(borrador.desde), borrador.hasta ? deDia(borrador.hasta) : null] : null),
  set: (v) => {
    borrador.desde = v?.[0] ? diaISO(v[0]) : null;
    borrador.hasta = v?.[1] ? diaISO(v[1]) : null;
  },
});
function completarRango() {
  if (borrador.desde && !borrador.hasta) borrador.hasta = borrador.desde;
}
const modificado = computed(() => JSON.stringify(borrador) !== JSON.stringify(filtroInicial()));
function alternarEstado(e: EstadoEncomienda) {
  borrador.estados = borrador.estados.includes(e) ? borrador.estados.filter((x) => x !== e) : [...borrador.estados, e];
}
function limpiar() {
  Object.assign(borrador, filtroInicial());
}
/** Los "pendientes de envío / de entrega" del legado, en la estación del usuario. */
function atajo(cual: "bodega" | "entregar") {
  const aqui = contexto.value?.estacion?.id ?? null;
  Object.assign(borrador, filtroInicial(), cual === "bodega" ? { ubicacion: aqui, estados: ["recibida", "desembarcada"] } : { ubicacion: aqui, destino: aqui, estados: ["desembarcada"] });
}
function aplicar() {
  const nuevo = structuredClone(toRaw(borrador));
  if (JSON.stringify(nuevo) === JSON.stringify(aplicado.value)) return;
  aplicado.value = nuevo;
  pagina.value = 1;
}
let espera: ReturnType<typeof setTimeout> | undefined;
watch(
  () => [borrador.desde, borrador.hasta, borrador.origen, borrador.destino, borrador.ubicacion, borrador.modalidad, borrador.estados.join(",")],
  () => {
    if (borrador.desde && !borrador.hasta) return;
    clearTimeout(espera);
    aplicar();
  },
);
watch(
  () => borrador.q,
  () => {
    clearTimeout(espera);
    espera = setTimeout(aplicar, 450);
  },
);
onBeforeUnmount(() => clearTimeout(espera));
function paginar(e: { page: number; rows: number }) {
  pagina.value = e.rows === porPagina.value ? e.page + 1 : 1;
  porPagina.value = e.rows;
}

// —— Datos ——
const consulta = computed(() => aQuery(aplicado.value, pagina.value, porPagina.value));
let pedido = 0;
async function cargar() {
  const n = ++pedido;
  cargando.value = true;
  error.value = "";
  try {
    const r = await fetchEncomiendas(consulta.value);
    if (n !== pedido) return;
    datos.value = r;
    elegida.value = elegida.value ? (r.items.find((x) => x.id === elegida.value!.id) ?? null) : null;
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver encomiendas (encomienda.ver)." : e instanceof Error ? e.message : String(e);
  } finally {
    if (n === pedido) cargando.value = false;
  }
}
watch(consulta, cargar);

// —— Selección y acciones ——
const seleccion = computed(() => new Set(elegida.value ? [String(elegida.value.id)] : []));
const claseFila = (r: unknown) => ({ "fila-cerrada": ["entregada", "anulada"].includes((r as FilaEncomienda).estado) });
const sinPermisos = { ver: false, registrar: false, interna: false, embarcar: false, entregar: false, anular: false, especiales: false, sinFactura: false };
const acciones = computed(() => disponibilidad(elegida.value, contexto.value?.permisos ?? sinPermisos, contexto.value?.estacion?.id ?? null));
function elegir(e: FilaEncomienda) {
  elegida.value = elegida.value?.id === e.id ? null : e;
}

async function ejecutar(accion: AccionEncomienda) {
  const e = elegida.value;
  if (!e || !acciones.value[accion].habilitada) return;
  if (accion === "consultar") {
    consultarId.value = e.id;
    dialogo.value = "consultar";
  } else if (accion === "bitacora") {
    bitacoraId.value = e.id;
  } else if (accion === "recibo" || accion === "guias") {
    try {
      const d = await fetchEncomienda(e.id);
      await (accion === "recibo" ? imprimirRecibo(d) : imprimirGuias(d));
    } catch (x) {
      notify.error(x instanceof Error ? x.message : "No se pudo imprimir.");
    }
  } else {
    dialogo.value = accion;
  }
}
function cerrar(visible: boolean) {
  if (visible) return;
  dialogo.value = null;
  if (route.query.id) void router.replace({ query: { ...route.query, id: undefined } });
}

// —— Celdas ——
const f = (r: unknown) => r as FilaEncomienda;
const cuando = (iso: string) => `${fecha(iso)} ${hora(iso)}`;
function texto(e: FilaEncomienda, key: string): string {
  switch (key) {
    case "creada":
      return cuando(e.creada);
    case "modalidad":
      return e.porCobrar ? "Por cobrar" : etiquetaModalidad(e.modalidad);
    case "origen":
    case "destino":
      return e[key].nombre;
    case "donde":
      if (e.ubicacion) return `Bodega ${e.ubicacion.nombre}`;
      if (e.salida && (e.estado === "embarcada" || e.estado === "transito")) return `Bus${e.salida.bus ? ` ${e.salida.bus}` : ""} · ${cuando(e.salida.fecha)}`;
      return etiquetaEstado(e.estado);
    case "remitente":
    case "destinatario":
      return e[key].nombre;
    case "total":
      return e.modalidad === "interna" ? "Sin cobro" : e.total.texto;
    case "empresa":
      return e.empresa.alias ?? e.empresa.nombre;
    default: {
      const v = (e as unknown as Record<string, unknown>)[key];
      return v === null || v === undefined ? "" : String(v);
    }
  }
}

// `?id=`: busca esa encomienda y la abre.
function desdeRuta() {
  const id = Number(route.query.id);
  if (!Number.isInteger(id) || id <= 0) return false;
  Object.assign(borrador, { ...filtroInicial(), q: String(id) });
  aplicado.value = structuredClone(toRaw(borrador));
  consultarId.value = id;
  dialogo.value = "consultar";
  return true;
}
watch(() => route.query.id, desdeRuta);

onMounted(async () => {
  if (!desdeRuta()) void cargar();
  contexto.value = await fetchContextoEncomienda().catch(() => null);
});
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
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}
.chip {
  border: 1px solid var(--p-surface-300);
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-color);
  padding: 0.125rem 0.625rem;
  font: inherit;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
}
.chip-on {
  border-color: var(--p-primary-color);
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}
.acciones {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}
.acciones :deep(.p-button) {
  gap: 0.375rem;
}
.estado {
  display: inline-flex;
  min-width: 1.75rem;
  height: 1.75rem;
  padding: 0 0.25rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: default;
}
.estado--recibida {
  background: var(--p-blue-100);
  color: var(--p-blue-700);
}
.estado--embarcada {
  background: var(--p-sky-100);
  color: var(--p-sky-700);
}
.estado--transito {
  background: var(--p-amber-100);
  color: var(--p-amber-700);
}
.estado--desembarcada {
  background: var(--p-violet-100);
  color: var(--p-violet-700);
}
.estado--entregada {
  background: var(--p-emerald-100);
  color: var(--p-emerald-700);
}
.estado--anulada {
  background: var(--p-surface-200);
  color: var(--p-surface-600);
  box-shadow: inset 0 0 0 1px var(--p-surface-300);
  text-decoration: line-through;
}
.tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  width: 100%;
}
:deep(.fila-cerrada) {
  color: var(--p-text-muted-color);
}
</style>
