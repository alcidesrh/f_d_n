<!--
  Salidas (ADR-024): por defecto sin finalizadas ni anuladas, primero las
  que están en ruta, luego las que abordan y después las programadas de la
  más próxima a la más lejana. Filtros por estado (con conteo), fechas,
  empresa, ruta y bus. Por fila: editar, anular (queda como cancelada)
  y eliminar, con la opción de propagarlo a las futuras idénticas. Ver abre el
  croquis con la ocupación y el resumen de venta; el menú PDF genera el
  manifiesto interno y el del piloto.
  Contenido angosto: tarjetas; desde 56rem de ancho del contenido: tabla.
-->
<template>
  <div class="@container flex flex-col gap-4 mt-5 relative">
    <!-- Contenido angosto: en su propia fila (flotando taparía los chips de estado). -->
    <div class="flex flex-wrap justify-end gap-2 @3xl:absolute @3xl:right-0">
      <RouterLink :to="{ name: 'salidas-calendario' }" class="no-underline">
        <Button label="Calendario" size="small" severity="secondary" outlined
          ><template #icon><icon name="calendar-view-week" class="mr-1" /></template
        ></Button>
      </RouterLink>
      <RouterLink v-if="opciones?.puede.crear" :to="{ name: 'salidas-programar' }" class="no-underline">
        <Button label="Programar salidas" size="small"
          ><template #icon><icon name="calendar-add-on-outline" class="mr-1" /></template
        ></Button>
      </RouterLink>
    </div>

    <section class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-muted-color">Estado:</span>
        <button v-for="e in ESTADOS" :key="e.valor" type="button" class="rounded-full border px-2.5 py-1 text-xs font-medium" :class="filtro.estados.includes(e.valor) ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 bg-transparent text-color'" :aria-pressed="filtro.estados.includes(e.valor)" @click="alternarEstado(e.valor)">
          {{ e.etiqueta }}<span v-if="datos?.porEstado[e.valor]" class="ml-1 opacity-75">{{ datos.porEstado[e.valor] }}</span>
        </button>
      </div>
      <div class="grid grid-cols-2 gap-3 @3xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1.4fr)_auto] @3xl:items-end">
        <div class="relative col-span-2 flex flex-col gap-1 @3xl:col-span-1">
          <span class="text-xs font-medium text-muted-color">{{ porRango ? "Rango de salida" : "Fecha de salida" }}</span>
          <DatePicker v-if="!porRango" ref="selector" v-model="borradorDia" :manual-input="false" date-format="dd/mm/yy" show-time hour-format="24" :step-minute="5" show-icon fluid placeholder="Todas" @hide="descartar">
            <template #footer>
              <div class="flex gap-2">
                <Button label="Quitar" size="small" severity="secondary" text class="flex-1" @click="quitarFecha" />
                <Button label="Aplicar" size="small" class="flex-1" :disabled="!borradorDia" @click="aplicarDia" />
              </div>
            </template>
          </DatePicker>
          <DatePicker v-else ref="selector" v-model="borrador" selection-mode="range" :manual-input="false" date-format="dd/mm/yy" show-time hour-format="24" :step-minute="5" show-icon fluid placeholder="Desde – Hasta" @hide="descartar">
            <template #footer>
              <div class="flex gap-2">
                <Button label="Quitar" size="small" severity="secondary" text class="flex-1" @click="quitarFecha" />
                <Button label="Aplicar" size="small" class="flex-1" :disabled="!borrador?.[0]" @click="aplicarRango" />
              </div>
            </template>
          </DatePicker>
          <label class="absolute right-0 top-0 flex cursor-pointer items-center gap-1 text-[0.75rem] leading-none text-muted-color">
            <ToggleSwitch v-model="porRango" class="scale-[0.8] origin-right" @update:model-value="alCambiarModo" />
            Rango
          </label>
        </div>
        <label class="col-span-2 flex flex-col gap-1 @3xl:col-span-1">
          <span class="text-xs font-medium text-muted-color">Ruta</span>
          <RutaSelect v-model="filtro.ruta" show-clear placeholder="Todas" />
        </label>
        <Button severity="secondary" outlined class="col-span-2 @3xl:col-span-1" @click="masFiltros = true">
          <icon name="filter-alt-outline" class="mr-1" />Más filtros
          <Badge v-if="otrosActivos" :value="otrosActivos" class="ml-1.5" />
        </Button>
      </div>
      <div v-if="estaModificado" class="flex justify-end"><Button label="Limpiar filtros" size="small" text @click="limpiar" /></div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Angosto: tarjetas -->
    <div class="flex flex-col gap-3 @4xl:hidden">
      <Skeleton v-if="cargando && !datos" height="8rem" />
      <p v-else-if="datos && !datos.items.length" class="m-0 text-center text-sm text-muted-color">No hay salidas con estos filtros.</p>
      <article v-for="s in datos?.items ?? []" :key="s.id" class="!p-3 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="font-semibold">{{ fechaHora(s.fecha) }}</div>
            <div class="text-sm">{{ s.ruta.nombre }}</div>
            <div class="text-xs text-muted-color">
              <template v-if="s.bus">Bus {{ s.bus.codigo }}</template
              ><template v-else>Sin bus</template> <template v-if="s.empresa"> · {{ s.empresa.nombre }}</template> · #{{ s.id }}
            </div>
          </div>
          <div class="flex flex-col items-end gap-1">
            <Tag :value="etiquetaEstado(s.estado).etiqueta" :severity="etiquetaEstado(s.estado).severidad" />
            <Tag v-if="s.atrasada" value="Atrasada" severity="warn" class="!text-xs" />
            <span class="text-xs tabular-nums text-muted-color">{{ ocupacion(s) }}</span>
          </div>
        </div>
        <div class="flex items-center justify-end gap-1">
          <Button label="Ver" severity="secondary" text size="small" @click="verSalida = s">
            <template #icon><icon name="visibility-outline" class="mr-1" /></template>
          </Button>
          <Button severity="secondary" text rounded size="small" class="tap-target" aria-label="Más acciones" aria-haspopup="menu" @click="abrirAcciones($event, s)">
            <template #icon><icon name="more-vert" /></template>
          </Button>
        </div>
      </article>
    </div>

    <!-- Ancho: tabla -->
    <div class="hidden @4xl:block">
      <DataTable :value="datos?.items ?? []" data-key="id" lazy paginator :rows="filtro.porPagina" :first="(filtro.pagina - 1) * filtro.porPagina" :total-records="datos?.total ?? 0" :rows-per-page-options="[25, 50, 100]" size="small" striped-rows @page="alPaginar">
        <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">No hay salidas con estos filtros.</p></template>
        <Column header="Salida">
          <template #body="{ data }">
            <div class="whitespace-nowrap font-medium">{{ fechaHora(data.fecha) }}</div>
            <div class="text-xs text-muted-color">#{{ data.id }}</div>
          </template>
        </Column>
        <Column header="Ruta">
          <template #body="{ data }">{{ data.ruta.nombre }}</template>
        </Column>
        <Column header="Bus">
          <template #body="{ data }">
            <template v-if="data.bus"
              ><div>{{ data.bus.codigo }}</div>
              <div class="text-xs text-muted-color">{{ data.bus.matricula }}</div></template
            >
            <span v-else class="text-muted-color">—</span>
          </template>
        </Column>
        <Column header="Empresa">
          <template #body="{ data }"
            ><span class="text-sm">{{ data.empresa?.nombre ?? "—" }}</span></template
          >
        </Column>
        <Column header="Vendidos" class="text-right">
          <template #body="{ data }"
            ><span class="whitespace-nowrap tabular-nums">{{ ocupacion(data) }}</span></template
          >
        </Column>
        <Column header="Estado">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Tag :value="etiquetaEstado(data.estado).etiqueta" :severity="etiquetaEstado(data.estado).severidad" />
              <Tag v-if="data.atrasada" value="Atrasada" severity="warn" />
            </div>
          </template>
        </Column>
        <Column header="" class="w-0">
          <template #body="{ data }">
            <div class="flex justify-end gap-0.5">
              <Button severity="secondary" text rounded size="small" aria-label="Ver" v-tooltip.top="'Ver croquis y resumen'" @click="verSalida = data">
                <template #icon><icon name="visibility-outline" /></template>
              </Button>
              <Button severity="secondary" text rounded size="small" aria-label="Más acciones" aria-haspopup="menu" @click="abrirAcciones($event, data)">
                <template #icon><icon name="more-vert" /></template>
              </Button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Paginator v-if="datos && datos.total > filtro.porPagina" class="@4xl:hidden" :rows="filtro.porPagina" :first="(filtro.pagina - 1) * filtro.porPagina" :total-records="datos.total" template="PrevPageLink CurrentPageReport NextPageLink" current-page-report-template="{currentPage} / {totalPages}" @page="alPaginar" />

    <Drawer v-model:visible="masFiltros" position="right" header="Más filtros" class="!w-[min(26rem,100vw)]">
      <div class="flex flex-col gap-4">
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Empresa</span><Select v-model="filtro.empresa" :options="opciones?.empresas ?? []" option-label="nombre" option-value="id" show-clear filter fluid placeholder="Todas" /></label>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Bus</span><Select v-model="filtro.bus" :options="opciones?.buses ?? []" option-label="codigo" option-value="id" :filter-fields="['codigo', 'matricula']" show-clear filter fluid placeholder="Todos" /></label>
        <div class="flex flex-col gap-1">
          <span class="text-xs font-medium">Orden</span>
          <SelectButton v-model="orden" :options="ORDENES" option-label="l" option-value="v" :allow-empty="false" class="flex-wrap" />
        </div>
        <div class="flex gap-2">
          <Button label="Limpiar" severity="secondary" outlined class="flex-1" @click="limpiarAvanzados" />
          <Button label="Ver resultados" class="flex-1" @click="masFiltros = false" />
        </div>
      </div>
    </Drawer>

    <Menu ref="menuAcciones" :model="itemsAcciones" popup>
      <template #item="{ item, props: enlace }">
        <a v-bind="enlace.action" class="flex items-center gap-2" :class="item.clase">
          <icon :name="String(item.iconName)" size="1.1rem" class="text-inherit" />
          <span>{{ item.label }}</span>
        </a>
      </template>
    </Menu>
    <VerSalidaDialog :salida="verSalida" @cerrar="cerrarVer" />
    <BitacoraDialog v-if="bitacora !== null" :visible="true" tipo="salida" :id="bitacora" @update:visible="(v: boolean) => !v && (bitacora = null)" />
    <EnviarPorChatDialog :visible="!!reportar" :referencias="reportar ? [{ tipo: 'Salida', id: reportar.id }] : []" @update:visible="(v: boolean) => !v && (reportar = null)" />
    <EditarSalidaDialog v-if="opciones" :salida="operacion === 'editar' ? seleccion : null" :opciones="opciones" @cerrar="seleccion = null" @hecho="alTerminar" />
    <ConfirmarOperacionDialog :salida="operacion !== 'editar' ? seleccion : null" :operacion="operacion === 'eliminar' ? 'eliminar' : 'anular'" @cerrar="seleccion = null" @hecho="alTerminar" />
    <ResultadoOperacionDialog :resultado="resultado" :operacion="operacion" @cerrar="resultado = null" />
  </div>
</template>

<script setup lang="ts">
import { fetchOpciones, fetchSalidas } from "@/core/salida/api";
import { aDia, aFechaHora, aQuery, deFechaHora, ESTADOS, redondearMinutos, etiquetaEstado, filtroInicial, type FiltroSalidas } from "@/core/salida/filtro";
import { resumen, type Operacion } from "@/core/salida/resultado";
import type { MenuItem } from "primevue/menuitem";
import type { EstadoSalida, OpcionesSalidas, PaginaSalidas, ResultadoOperacion, SalidaFila } from "@/core/salida/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import ConfirmarOperacionDialog from "./ConfirmarOperacionDialog.vue";
import EditarSalidaDialog from "./EditarSalidaDialog.vue";
import RutaSelect from "./RutaSelect.vue";
import ResultadoOperacionDialog from "./ResultadoOperacionDialog.vue";
import VerSalidaDialog from "./VerSalidaDialog.vue";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import { anunciarEnPantalla } from "@/shared/chat/integracion";
import { abrirManifiesto, MANIFIESTOS } from "./manifiesto";

const ORDENES = [
  { v: "proximas", l: "Próximas primero" },
  { v: "fecha-asc", l: "Fecha ↑" },
  { v: "fecha-desc", l: "Fecha ↓" },
];

const filtro = reactive<FiltroSalidas>(filtroInicial());
const datos = shallowRef<PaginaSalidas | null>(null);
const opciones = shallowRef<OpcionesSalidas | null>(null);
const cargando = ref(false);
const error = ref("");
const masFiltros = ref(false);
const seleccion = ref<SalidaFila | null>(null);
const operacion = ref<Operacion>("editar");
const resultado = shallowRef<ResultadoOperacion | null>(null);
/** La que muestra "Ver"; también llega por `?ver=id` (enlaces del chat). */
const verSalida = ref<Pick<SalidaFila, "id"> | null>(null);
const reportar = ref<SalidaFila | null>(null);
/** Salida cuya bitácora se está viendo. */
const bitacora = ref<number | null>(null);
const route = useRoute();
const router = useRouter();
watch(
  () => Number(route.query.ver),
  (id) => {
    if (Number.isInteger(id) && id > 0) verSalida.value = { id };
  },
  { immediate: true },
);
// Con el chat encima, la salida abierta se ofrece para adjuntar.
anunciarEnPantalla(() => {
  const v = verSalida.value;
  if (!v) return [];
  const fila = "ruta" in v ? (v as SalidaFila) : datos.value?.items.find((s) => s.id === v.id);
  return [{ tipo: "Salida", id: v.id, etiqueta: fila ? `${fila.ruta.nombre} · ${new Date(fila.fecha).toLocaleString("es-GT", { dateStyle: "short", timeStyle: "short" })}` : `Salida ${v.id}` }];
});
function cerrarVer() {
  verSalida.value = null;
  if (route.query.ver) void router.replace({ query: { ...route.query, ver: undefined } });
}
const menuAcciones = ref<{ toggle: (e: Event) => void } | null>(null);
const itemsAcciones = ref<MenuItem[]>([]);

const estaModificado = computed(() => aQuery({ ...filtro, pagina: 1 }) !== aQuery(filtroInicial()));
const otrosActivos = computed(() => [filtro.empresa, filtro.bus, filtro.orden !== "proximas" ? 1 : null].filter((v) => v !== null).length);

/**
 * Un día desde una hora (a las 00:00, todo el día) o, con «Rango», desde–hasta
 * con hora. Se elige en el selector y solo se busca al pulsar «Aplicar».
 */
const porRango = ref(false);
const borradorDia = ref<Date | null>(null);
const borrador = ref<(Date | null)[] | null>(null);
const selector = ref<{ overlayVisible: boolean } | null>(null);

/** Los borradores vuelven a lo aplicado (al abrir otro modo, limpiar o cerrar sin aplicar). */
function descartar() {
  const d = filtro.desde ? deFechaHora(filtro.desde) : null;
  const h = filtro.hasta ? deFechaHora(filtro.hasta) : null;
  // Sin hora, el fin es todo el día.
  if (h && !filtro.hasta!.includes("T")) h.setHours(23, 59);
  borradorDia.value = d;
  borrador.value = d ? [d, h] : null;
}
// Al elegir otro día, la hora empieza en 00:00 (todo el día), no en la de ahora.
watch(borradorDia, (n, o) => {
  if (n && aDia(n) !== (o ? aDia(o) : null) && (n.getHours() || n.getMinutes()) && aDia(n) !== filtro.desde?.slice(0, 10)) borradorDia.value = new Date(n.getFullYear(), n.getMonth(), n.getDate());
});
function cerrar() {
  if (selector.value) selector.value.overlayVisible = false;
}
function alCambiarModo(activo: boolean) {
  // Un día con hora pasa a rango hasta el final de ese día; un rango, a su primer día.
  if (!activo && filtro.desde) filtro.hasta = filtro.desde.slice(0, 10);
  descartar();
}
function aplicarDia() {
  const d = borradorDia.value;
  if (!d) return;
  const r = redondearMinutos(d);
  filtro.desde = r.getHours() || r.getMinutes() ? aFechaHora(r) : aDia(r);
  filtro.hasta = aDia(r);
  cerrar();
}
function aplicarRango() {
  const [d, h] = borrador.value ?? [];
  if (!d) return;
  // Sin fin, o con el fin a 00:00, el último día entra completo.
  let fin = h ?? d;
  fin = fin.getHours() === 0 && fin.getMinutes() === 0 ? new Date(fin.getFullYear(), fin.getMonth(), fin.getDate(), 23, 59) : redondearMinutos(fin);
  const ini = redondearMinutos(d);
  const [desde, hasta] = fin < ini ? [fin, ini] : [ini, fin];
  filtro.desde = aFechaHora(desde);
  filtro.hasta = aFechaHora(hasta);
  cerrar();
}
function quitarFecha() {
  filtro.desde = filtro.hasta = null;
  cerrar();
}
const orden = computed({
  get: () => (filtro.orden === "proximas" ? "proximas" : `fecha-${filtro.direccion}`),
  set: (v: string) => {
    filtro.orden = v === "proximas" ? "proximas" : "fecha";
    filtro.direccion = v === "fecha-desc" ? "desc" : "asc";
  },
});

const fechaHora = (iso: string) => new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "2-digit", month: "short", year: "numeric", hour: "numeric", minute: "2-digit" }).format(new Date(iso));
const ocupacion = (s: SalidaFila) => (s.capacidad ? `${s.vendidos} / ${s.capacidad}` : String(s.vendidos));

/** Acciones de la fila: habilitadas según el estado de la salida y los permisos del usuario. */
function acciones(s: SalidaFila) {
  const p = opciones.value?.puede;
  const programada = s.estado === "programada";
  return [
    { op: "editar" as const, icono: "edit-outline", etiqueta: "Editar", severidad: "secondary" as const, habilitada: !!p?.editar && programada },
    { op: "anular" as const, icono: "block", etiqueta: "Anular", severidad: "warn" as const, habilitada: !!p?.anular && programada },
    { op: "eliminar" as const, icono: "delete-outline", etiqueta: "Eliminar", severidad: "danger" as const, habilitada: !!p?.eliminar && (programada || s.estado === "cancelada") },
  ];
}

/** Se chequean boletos (ADR-032) mientras la salida no ha terminado. */
const chequeable = (s: SalidaFila) => ["programada", "abordando", "iniciada"].includes(s.estado);

/** Lo que se le puede hacer a la salida, con su nombre: «Ver» queda a la vista en la fila. */
function abrirAcciones(e: Event, s: SalidaFila) {
  const operaciones = acciones(s).filter((a) => a.habilitada);
  itemsAcciones.value = [
    { label: "Reportar por chat", iconName: "forum-outline", command: () => (reportar.value = s) },
    { label: "Bitácora", iconName: "history", command: () => (bitacora.value = s.id) },
    ...(chequeable(s) ? [{ label: "Chequear boletos", iconName: "how-to-reg-outline", command: () => void router.push({ name: "salidas-chequeo", query: { salida: s.id } }) }] : []),
    ...MANIFIESTOS.map((m) => ({ label: m.etiqueta, iconName: "picture-as-pdf-outline", command: () => void abrirManifiesto(s.id, m.tipo) })),
    ...(operaciones.length ? [{ separator: true }] : []),
    ...operaciones.map((a) => ({ label: a.etiqueta, iconName: a.icono, clase: a.severidad === "danger" ? "text-red-600" : "", command: () => abrir(a.op, s) })),
  ];
  menuAcciones.value?.toggle(e);
}

function abrir(op: Operacion, s: SalidaFila) {
  operacion.value = op;
  seleccion.value = s;
}

function alTerminar(r: ResultadoOperacion) {
  seleccion.value = null;
  const { texto, severidad } = resumen(r, operacion.value);
  notify[severidad](texto);
  if (r.omitidas.length) resultado.value = r;
  void cargar();
}

function alternarEstado(e: EstadoSalida) {
  filtro.estados = filtro.estados.includes(e) ? filtro.estados.filter((x) => x !== e) : [...filtro.estados, e];
}

function limpiarAvanzados() {
  Object.assign(filtro, { empresa: null, bus: null, orden: "proximas", direccion: "asc" });
}

function limpiar() {
  Object.assign(filtro, filtroInicial());
  descartar();
}

function alPaginar(e: { page: number; rows: number }) {
  filtro.pagina = e.page + 1;
  filtro.porPagina = e.rows;
}

// Cualquier cambio de filtro (salvo la página) vuelve a la primera página.
watch(
  () => aQuery({ ...filtro, pagina: 1 }),
  () => (filtro.pagina = 1),
);

let pedido = 0;
async function cargar() {
  const n = ++pedido;
  cargando.value = true;
  error.value = "";
  try {
    const r = await fetchSalidas(aQuery(filtro));
    if (n === pedido) datos.value = r;
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las salidas (salida.ver)." : e instanceof Error ? e.message : String(e);
  } finally {
    if (n === pedido) cargando.value = false;
  }
}
watch(() => aQuery(filtro), cargar, { immediate: true });

onMounted(async () => {
  opciones.value = await fetchOpciones().catch(() => null);
});
</script>
