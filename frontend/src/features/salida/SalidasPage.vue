<!--
  Salidas (ADR-024): arriba, los controles del calendario (fecha, días, hora,
  empresa y la ruta fijada en la leyenda) filtran las salidas, que se ven en
  lista o en el calendario (un día por fila, las horas en columnas) según el
  interruptor «Lista / Calendario» (recordado por usuario; `?vista=` lo
  fuerza). En la lista, de la más temprana a la más tardía, sin anuladas; por
  fila: editar, anular (queda como cancelada) y eliminar, con la opción de
  propagarlo a las futuras idénticas. Ver abre el croquis con la ocupación y
  el resumen de venta; el menú PDF genera el manifiesto interno y el del
  piloto. En el calendario, pulsar una salida abre su detalle y «Seleccionar»
  marca varias para eliminarlas juntas.
  Contenido angosto: tarjetas; desde 56rem de ancho del contenido: tabla.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <nav v-if="opciones?.puede.crear" class="flex flex-wrap justify-end gap-1" aria-label="Otras vistas de salidas">
      <RouterLink :to="{ name: 'salidas-programar' }" class="no-underline">
        <Button label="Programar salidas" size="small"
          ><template #icon><icon name="calendar-add-on-outline" class="mr-1" /></template
        ></Button>
      </RouterLink>
    </nav>

    <CalendarioSalidas :grilla="vista === 'calendario'" seleccion :activa="activa?.id ?? null" @abrir="abrirDetalle" @eliminar="confirmarEliminar">
      <!-- Presentación de las salidas: lista o calendario. -->
      <div class="vista" role="group" aria-label="Presentación de las salidas">
        <button v-for="v in VISTAS" :key="v.valor" type="button" class="vista__boton" :class="{ 'vista__boton--activa': vista === v.valor }" :aria-pressed="vista === v.valor" :title="v.titulo" @click="elegirVista(v.valor)">
          <icon :name="v.icono" size="1.1rem" />
          <span class="hidden @md:inline">{{ v.etiqueta }}</span>
        </button>
      </div>
    </CalendarioSalidas>

    <template v-if="vista === 'lista'">
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
      <DataTable :value="datos?.items ?? []" data-key="id" lazy paginator :rows="porPagina" :first="(pagina - 1) * porPagina" :total-records="datos?.total ?? 0" :rows-per-page-options="[25, 50, 100]" size="small" striped-rows @page="alPaginar">
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

    <Paginator v-if="datos && datos.total > porPagina" class="@4xl:hidden" :rows="porPagina" :first="(pagina - 1) * porPagina" :total-records="datos.total" template="PrevPageLink CurrentPageReport NextPageLink" current-page-report-template="{currentPage} / {totalPages}" @page="alPaginar" />
    </template>


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
    <SalidaPopover ref="popover" @ver="(s) => (verSalida = s)" @cerrado="activa = null" />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from "primevue/useconfirm";
import { claveDeUsuario } from "@/core/auth/alcanceSesion";
import { eliminarSalidas, fetchOpciones, fetchSalidas } from "@/core/salida/api";
import { ESTADOS_CALENDARIO } from "@/core/salida/calendario";
import { aQuery, etiquetaEstado, filtroInicial, type FiltroSalidas } from "@/core/salida/filtro";
import { resumen, type Operacion } from "@/core/salida/resultado";
import type { MenuItem } from "primevue/menuitem";
import type { OpcionesSalidas, PaginaSalidas, ResultadoOperacion, SalidaCalendario, SalidaFila } from "@/core/salida/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import CalendarioSalidas from "@/shared/salida-calendario/CalendarioSalidas.vue";
import { useCalendarioSalidasStore } from "@/shared/salida-calendario/store";
import SalidaPopover from "./calendario/SalidaPopover.vue";
import ConfirmarOperacionDialog from "./ConfirmarOperacionDialog.vue";
import EditarSalidaDialog from "./EditarSalidaDialog.vue";
import ResultadoOperacionDialog from "./ResultadoOperacionDialog.vue";
import VerSalidaDialog from "./VerSalidaDialog.vue";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import { anunciarEnPantalla } from "@/shared/chat/integracion";
import { abrirManifiesto, MANIFIESTOS } from "./manifiesto";

const route = useRoute();
const router = useRouter();
const confirm = useConfirm();
const calendario = useCalendarioSalidasStore();

/** Lista o calendario; se recuerda por usuario en este navegador y `?vista=` lo fuerza (enlaces al calendario). */
type Vista = "lista" | "calendario";
const VISTAS: { valor: Vista; icono: string; etiqueta: string; titulo: string }[] = [
  { valor: "lista", icono: "view-list-outline", etiqueta: "Lista", titulo: "Salidas en lista, de la más temprana a la más tardía" },
  { valor: "calendario", icono: "view-week-outline", etiqueta: "Calendario", titulo: "Un día por fila y las horas en columnas" },
];
const PREFERENCIA_VISTA = "salidas.vista";
function leerVista(): Vista {
  if (route.query.vista === "calendario" || route.query.vista === "lista") return route.query.vista;
  try {
    return localStorage.getItem(claveDeUsuario(PREFERENCIA_VISTA)) === "calendario" ? "calendario" : "lista";
  } catch {
    return "lista";
  }
}
const vista = ref<Vista>(leerVista());
function elegirVista(v: Vista) {
  vista.value = v;
  try {
    localStorage.setItem(claveDeUsuario(PREFERENCIA_VISTA), v);
  } catch {
    // Sin almacenamiento: solo dura la visita.
  }
  if (route.query.vista) void router.replace({ query: { ...route.query, vista: undefined } });
}

/** Página de la lista; lo demás del filtro sale de los controles del calendario. */
const pagina = ref(1);
const porPagina = ref(25);
/** Lo mismo que muestra el calendario: sus días, empresa, hora y la ruta fijada en la leyenda, sin anuladas. */
const filtro = computed<FiltroSalidas>(() => {
  const dias = calendario.columnas;
  return {
    ...filtroInicial(),
    estados: [...ESTADOS_CALENDARIO],
    empresa: calendario.empresa,
    ruta: calendario.fijada,
    desde: dias[0] ?? null,
    hasta: dias[dias.length - 1] ?? null,
    hora: calendario.hora,
    orden: "fecha",
    direccion: "asc",
    pagina: pagina.value,
    porPagina: porPagina.value,
  };
});
const datos = shallowRef<PaginaSalidas | null>(null);
const opciones = shallowRef<OpcionesSalidas | null>(null);
const cargando = ref(false);
const error = ref("");
const seleccion = ref<SalidaFila | null>(null);
const operacion = ref<Operacion>("editar");
const resultado = shallowRef<ResultadoOperacion | null>(null);
/** La que muestra "Ver"; también llega por `?ver=id` (enlaces del chat). */
const verSalida = ref<Pick<SalidaFila, "id"> | null>(null);
const reportar = ref<SalidaFila | null>(null);
/** Salida cuya bitácora se está viendo. */
const bitacora = ref<number | null>(null);
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
  void calendario.cargar();
}

function alPaginar(e: { page: number; rows: number }) {
  pagina.value = e.rows === porPagina.value ? e.page + 1 : 1;
  porPagina.value = e.rows;
}

// Cualquier cambio de filtro (salvo la página) vuelve a la primera página.
watch(
  () => aQuery({ ...filtro.value, pagina: 1 }),
  () => (pagina.value = 1),
);

// —— Calendario: el detalle de una salida y eliminar varias ——
const popover = ref<InstanceType<typeof SalidaPopover> | null>(null);
const activa = shallowRef<SalidaCalendario | null>(null);

function abrirDetalle(evento: MouseEvent, s: SalidaCalendario) {
  activa.value = s;
  void popover.value?.abrir(evento, s);
}

/** Pide confirmación y elimina las seleccionadas; las que no se pudieron (con boletos, ya salieron) se listan. */
function confirmarEliminar() {
  const ids = [...calendario.seleccion];
  if (!ids.length) return;
  confirm.require({
    header: "Eliminar salidas",
    message: `${ids.length === 1 ? "Se eliminará 1 salida" : `Se eliminarán ${ids.length} salidas`}. Las que tengan boletos o asientos apartados no se eliminan. No se puede deshacer. ¿Continuar?`,
    acceptProps: { label: "Eliminar", severity: "danger" },
    rejectProps: { label: "Volver", severity: "secondary", outlined: true },
    accept: () => void eliminar(ids),
  });
}

async function eliminar(ids: number[]) {
  try {
    const r = await eliminarSalidas(ids);
    const { texto, severidad } = resumen(r, "eliminar");
    notify[severidad](texto);
    operacion.value = "eliminar";
    if (r.omitidas.length) resultado.value = r;
    calendario.salirDeSeleccion();
  } catch (e) {
    notify.error(e instanceof Error ? e.message : "No se pudieron eliminar las salidas.");
  }
  await calendario.cargar();
}

let pedido = 0;
async function cargar() {
  const n = ++pedido;
  cargando.value = true;
  error.value = "";
  try {
    const r = await fetchSalidas(aQuery(filtro.value));
    if (n === pedido) datos.value = r;
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las salidas (salida.ver)." : e instanceof Error ? e.message : String(e);
  } finally {
    if (n === pedido) cargando.value = false;
  }
}
// La lista se pide mientras se ve (y al volver a ella); los días los pone el calendario al montarse.
watch(
  () => (vista.value === "lista" && filtro.value.desde ? aQuery(filtro.value) : null),
  (q) => q && void cargar(),
  { immediate: true },
);

onMounted(async () => {
  opciones.value = await fetchOpciones().catch(() => null);
});
</script>

<style scoped>
/* Selector de presentación (como en la venta): dos botones unidos, el activo en relieve. */
.vista {
  display: inline-flex;
  padding: 2px;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  background: color-mix(in srgb, var(--p-surface-500) 6%, transparent);
}
.vista__boton {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.65rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--p-text-muted-color);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;
}
.vista__boton:hover {
  color: var(--p-text-color);
}
.vista__boton:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
.vista__boton--activa {
  background: var(--p-content-background);
  color: var(--p-text-color);
  box-shadow: 0 1px 3px color-mix(in srgb, var(--p-surface-900) 18%, transparent);
}
</style>
