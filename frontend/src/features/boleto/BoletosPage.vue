<!--
  Boletos (el "Boletos" del legado): arriba, el calendario de salidas como
  filtro (pulsar una salida deja solo sus boletos; otra vez, o la ✕, la
  suelta), con la empresa, el id y la clase de asiento (se aplican al
  cambiar; el id, con retardo). Sin salida elegida, los de 30 días antes y
  después de hoy. Debajo, el panel de acciones sobre el boleto elegido y la
  lista paginada en el servidor. Se elige un boleto con un clic (o Enter) y el panel
  ofrece Consultar, Cancelar, Revertir, Anular, Chequear, Reasignar, Correo, Imp.
  factura, Bitácora y Chat; cada acción explica por qué no se puede si no se
  puede. Columnas ordenables, redimensionables y ocultables (se recuerdan en
  el navegador). `?id=` abre ese boleto (enlaces del chat).
  Contenido angosto: tarjetas.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <!-- Filtro: el calendario de salidas (pulsar una salida deja solo sus boletos), la empresa, el id y la clase. -->
    <CalendarioSalidas :activa="borrador.salida" :ocupacion="false" max-alto="18rem" @abrir="(_, s) => elegirSalida(s)">
      <label class="filtro-id">
        <span class="text-sm font-medium">Id</span>
        <InputText :model-value="String(borrador.id ?? '')" inputmode="numeric" size="small" class="w-24" aria-label="Id del boleto" @input="borrador.id = soloNumero($event)" />
      </label>
      <div class="chips" role="group" aria-label="Clase de asiento">
        <span class="text-sm font-medium">Clase</span>
        <button v-for="c in CLASES" :key="c" type="button" class="chip" :class="{ 'chip-on': borrador.clase === c }" :aria-pressed="borrador.clase === c" @click="borrador.clase = borrador.clase === c ? null : c">{{ c }}</button>
      </div>
      <Button type="button" label="Limpiar" severity="secondary" text size="small" :disabled="!borradorModificado" @click="limpiar"
        ><template #icon><icon name="filter-alt-off-outline" class="mr-1" /></template
      ></Button>
    </CalendarioSalidas>

    <!-- La salida elegida en el calendario: sus boletos, en cualquier fecha de venta. -->
    <div v-if="borrador.salida" class="salida-elegida">
      <icon name="directions-bus-outline" />
      <span class="truncate"
        >Boletos de la salida <b>#{{ borrador.salida }}</b
        ><template v-if="salidaElegida"> · {{ salidaElegida }}</template></span
      >
      <Button text rounded size="small" severity="secondary" class="tap-target" aria-label="Ver todas las salidas" v-tooltip.top="'Ver los de todas las salidas'" @click="borrador.salida = null"
        ><template #icon><icon name="close" /></template
      ></Button>
    </div>

    <!-- Acciones sobre el boleto elegido (izquierda) y columnas (derecha) -->
    <div class="acciones" role="toolbar" aria-label="Acciones del boleto elegido">
      <div class="acciones-izq">
        <div v-if="elegido" class="acciones-elegido">
          <span class="font-semibold">#{{ elegido.id }}</span>
          <span class="truncate text-sm text-muted-color">asiento {{ elegido.asiento }} · {{ elegido.clienteBoleto?.nombre ?? "sin pasajero" }}</span>
          <Button text rounded size="small" severity="secondary" class="tap-target" aria-label="Quitar selección" v-tooltip.top="'Quitar selección'" @click="elegido = null"
            ><template #icon><icon name="close" /></template
          ></Button>
        </div>
        <!-- Teléfono: las acciones en un desplegable; desde 36rem, botones. -->
        <AccionesSelect class="@xl:!hidden" :acciones="accionesMenu" @ejecutar="ejecutar($event as AccionBoleto)" />
        <div class="acciones-botones">
          <template v-for="a in botones" :key="a.accion">
            <span v-if="estadoAcciones[a.accion].visible" v-tooltip.bottom="estadoAcciones[a.accion].motivo ?? a.ayuda">
              <Button :severity="a.severidad ?? 'secondary'" :outlined="a.severidad === undefined" size="small" :disabled="!estadoAcciones[a.accion].habilitada || ocupado === a.accion" :loading="ocupado === a.accion" :aria-label="a.etiqueta" @click="ejecutar(a.accion)">
                <icon :name="a.icono" />
                <span class="hidden @xl:inline">{{ a.etiqueta }}</span>
                <!-- Se intenta y, sin una autorización aprobada, se ofrece pedirla (ADR-040). -->
                <icon v-if="estadoAcciones[a.accion].conAutorizacion" name="lock-outline" size=".85rem" class="opacity-70" />
              </Button>
            </span>
          </template>
        </div>
      </div>
      <MultiSelect v-model="visibles" :options="COLUMNAS" option-label="label" option-value="key" :max-selected-labels="0" selected-items-label="Columnas ({0})" placeholder="Columnas" size="small" class="w-36 shrink-0 @xl:w-44">
        <template #dropdownicon><icon name="view-column-outline" /></template>
      </MultiSelect>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataGrid :columns="columnasGrid" :rows="datos?.items ?? []" :row-key="(r) => String((r as BoletoFila).id)" :loading="cargando" :selected-keys="seleccion" density="compact" :card-fields="4" max-height="calc(100dvh - 14rem)" row-clickable resizable hideable :row-class="claseFila" @row-click="elegir($event as BoletoFila)" @sort="ordenar" @resize="redimensionar" @hide="ocultar">
      <template #cell="{ row, column }">
        <span v-if="column.key === 'estado'" class="estado" :class="`estado--${f(row).estado}`" :title="etiquetaEstado(f(row).estado).etiqueta">{{ inicialEstado(f(row).estado) }}</span>
        <span v-else-if="column.key === 'documento'" class="estado" :class="`documento--${f(row).documento.tipo}`" :title="texto(f(row), 'documento')">{{ inicialDocumento(f(row).documento.tipo) }}</span>
        <span v-else-if="corta(f(row), column.key) !== null" class="block truncate" v-tooltip.top="texto(f(row), column.key)">{{ corta(f(row), column.key) }}</span>
        <GridText v-else :text="texto(f(row), column.key)" />
      </template>
      <template #card="{ row }">
        <div class="tarjeta">
          <div class="flex items-center justify-between gap-2">
            <span class="font-semibold">#{{ f(row).id }} · asiento {{ f(row).asiento }}</span>
            <span class="estado" :class="`estado--${f(row).estado}`" :title="etiquetaEstado(f(row).estado).etiqueta">{{ inicialEstado(f(row).estado) }}</span>
          </div>
          <div class="truncate text-sm">{{ f(row).sube }} → {{ f(row).baja }}</div>
          <div class="truncate text-xs text-muted-color">{{ texto(f(row), "fecha") }} · {{ documentoTexto(f(row).documento) }}</div>
          <div class="flex justify-between gap-2 text-xs">
            <span class="truncate">{{ f(row).clienteBoleto?.nombre ?? "—" }}</span>
            <span class="shrink-0 tabular-nums">{{ texto(f(row), "precio") }}</span>
          </div>
        </div>
      </template>
      <template #empty>
        <icon name="confirmation-number-outline" size="2rem" />
        <span>No hay boletos con estos filtros.</span>
      </template>
    </DataGrid>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <Paginator v-if="datos" :rows="pagina.porPagina" :first="(pagina.pagina - 1) * pagina.porPagina" :total-records="datos.total" :rows-per-page-options="opciones?.porPagina ?? [10, 15, 25, 50, 100]" template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown" current-page-report-template="{first}–{last} de {totalRecords}" @page="paginar" />
    </div>

    <ConsultarBoletoDialog :visible="dialogo === 'consultar'" :id="consultarId" @update:visible="cerrar" @ver="(id) => (consultarId = id)" @bitacora="bitacoraId = consultarId" />
    <CancelarBoletoDialog :visible="dialogo === 'cancelar'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <RevertirBoletoDialog :visible="dialogo === 'revertir'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <ChequearBoletoDialog :visible="dialogo === 'chequear'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <CorreoBoletoDialog :visible="dialogo === 'correo'" :boleto="elegido" @update:visible="cerrar" />
    <AnularBoletosDialog :visible="dialogo === 'anular'" :ids="elegido ? [elegido.id] : []" @update:visible="cerrar" @listo="cargar" />
    <BitacoraDialog v-if="bitacoraId !== null" :visible="true" tipo="boleto" :id="bitacoraId" @update:visible="(v: boolean) => !v && (bitacoraId = null)" />
    <EnviarPorChatDialog :visible="dialogo === 'chat'" :referencias="elegido ? [{ tipo: 'BoletoAsiento', id: elegido.id }] : []" @update:visible="cerrar" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, toRaw, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { claveDeUsuario } from "@/core/auth/alcanceSesion";
import { fetchBoletosListado, fetchOpcionesBoletos } from "@/core/boleto/api";
import { aQuery, alternarOrden, disponibilidad, documentoTexto, etiquetaEstado, filtroInicial, paginaInicial, type AccionBoleto, type FiltroBoletos, type Pagina } from "@/core/boleto/listado";
import type { EstadoBoleto, TipoDocumento } from "@/core/boleto/types";
import type { BoletoFila, OpcionesBoletos, OrdenBoletos, PaginaBoletos } from "@/core/boleto/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { puntas } from "@/core/salida/calendario";
import type { SalidaCalendario } from "@/core/salida/types";
import { fecha, hora } from "@/core/venta/modelo";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import AnularBoletosDialog from "@/shared/boleto/AnularBoletosDialog.vue";
import { descargarBoletoPdf } from "@/shared/boleto/pdf";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import { anunciarEnPantalla } from "@/shared/chat/integracion";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import CalendarioSalidas from "@/shared/salida-calendario/CalendarioSalidas.vue";
import { useCalendarioSalidasStore } from "@/shared/salida-calendario/store";
import CancelarBoletoDialog from "@/shared/boleto/CancelarBoletoDialog.vue";
import RevertirBoletoDialog from "./RevertirBoletoDialog.vue";
import ChequearBoletoDialog from "./ChequearBoletoDialog.vue";
import ConsultarBoletoDialog from "./ConsultarBoletoDialog.vue";
import CorreoBoletoDialog from "./CorreoBoletoDialog.vue";

interface Columna extends GridColumn {
  /** Orden del backend al hacer clic en el título. */
  orden?: OrdenBoletos;
  /** Oculta hasta que se elija en «Columnas» (como en el legado). */
  oculta?: boolean;
}

/**
 * Las del legado: quién y dónde se vendió junto al asiento, la ruta tras el
 * cliente, y estado y documento al final. Estaciones con alias y usuario con
 * su username (el nombre completo, en el tooltip).
 */
const COLUMNAS: Columna[] = [
  { key: "id", label: "ID", width: "5.5rem", orden: "id", align: "end" },
  { key: "fecha", label: "Fecha salida", width: "10rem", orden: "fecha" },
  { key: "asiento", label: "Asiento", width: "5.5rem", orden: "numero", align: "end" },
  { key: "usuario", label: "User", width: "8rem" },
  { key: "estacion", label: "Estación", width: "6.5rem" },
  { key: "sube", label: "Sube en", width: "6.5rem" },
  { key: "baja", label: "Baja en", width: "6.5rem" },
  { key: "clase", label: "Clase", width: "4.5rem", align: "center", oculta: true },
  { key: "referenciaExterna", label: "Ref. externa", width: "9rem", oculta: true },
  { key: "autorizacion", label: "Autorización tarjeta", width: "9rem", oculta: true },
  { key: "clienteDocumento", label: "Cliente documento", width: "14rem", orden: "clienteDocumento", oculta: true },
  { key: "clienteBoleto", label: "Cliente boleto", width: "14rem", orden: "clienteBoleto" },
  { key: "ruta", label: "Ruta", width: "2fr", minWidth: "10rem", orden: "ruta" },
  { key: "tipoPago", label: "Tipo pago", width: "7rem", oculta: true },
  { key: "precio", label: "Precio", width: "7rem", align: "end" },
  { key: "reasignadoDe", label: "Reasignado de", width: "7rem", align: "end", oculta: true },
  { key: "empresa", label: "Empresa", width: "8rem", oculta: true },
  { key: "creada", label: "Fecha creación", width: "10rem", orden: "creada", oculta: true },
  { key: "estado", label: "Estado", width: "5.5rem", align: "center" },
  { key: "documento", label: "Documento", width: "6.5rem", align: "center" },
];

const BOTONES: Array<{ accion: AccionBoleto; etiqueta: string; icono: string; ayuda: string; severidad?: "warn" | "danger" }> = [
  { accion: "consultar", etiqueta: "Consultar", icono: "visibility-outline", ayuda: "Ver todos los datos del boleto" },
  { accion: "cancelar", etiqueta: "Cancelar", icono: "person-off-outline", ayuda: "El pasajero no viaja: libera el asiento, conserva la venta y la factura" },
  { accion: "revertir", etiqueta: "Revertir", icono: "undo", ayuda: "El pasajero sí viaja: el cancelado vuelve a emitido si su asiento sigue libre" },
  { accion: "anular", etiqueta: "Anular", icono: "cancel-outline", ayuda: "Anula el boleto y su factura" },
  { accion: "chequear", etiqueta: "Chequear", icono: "how-to-reg-outline", ayuda: "El pasajero se presentó a abordar" },
  { accion: "reasignar", etiqueta: "Reasignar", icono: "swap-horiz", ayuda: "Cambiar de asiento o de salida" },
  { accion: "correo", etiqueta: "Correo", icono: "mail-outline", ayuda: "Enviar el boleto por correo" },
  { accion: "factura", etiqueta: "Imp. factura", icono: "print-outline", ayuda: "Reimprimir el ticket con la factura" },
  { accion: "pdf", etiqueta: "PDF", icono: "picture-as-pdf-outline", ayuda: "Descargar el boleto en PDF" },
  { accion: "bitacora", etiqueta: "Bitácora", icono: "history", ayuda: "Historial del boleto" },
  { accion: "chat", etiqueta: "Chat", icono: "forum-outline", ayuda: "Enviar el boleto por el chat" },
];
const botones = BOTONES;
/** Las mismas acciones para el desplegable del teléfono. */
const accionesMenu = computed(() =>
  BOTONES.filter((a) => estadoAcciones.value[a.accion].visible).map((a) => ({
    valor: a.accion,
    etiqueta: a.etiqueta,
    icono: a.icono,
    deshabilitada: !estadoAcciones.value[a.accion].habilitada || ocupado.value === a.accion,
    motivo: estadoAcciones.value[a.accion].motivo,
    bloqueada: estadoAcciones.value[a.accion].conAutorizacion,
    peligro: a.severidad === "danger",
  })),
);

/** `.v2`: columnas renombradas y otras ocultas por defecto; lo guardado antes no aplica. */
const PREFERENCIAS = "boletos.columnas.v2";
type Dialogo = "consultar" | "cancelar" | "revertir" | "chequear" | "correo" | "anular" | "chat";

const route = useRoute();
const router = useRouter();

const opciones = shallowRef<OpcionesBoletos | null>(null);
const datos = shallowRef<PaginaBoletos | null>(null);
const cargando = ref(false);
const error = ref("");
const borrador = reactive<FiltroBoletos>(filtroInicial());
const aplicado = ref<FiltroBoletos>(filtroInicial());
const pagina = ref<Pagina>(paginaInicial());
const elegido = ref<BoletoFila | null>(null);
const dialogo = ref<Dialogo | null>(null);
const consultarId = ref<number | null>(null);
const bitacoraId = ref<number | null>(null);
const ocupado = ref<AccionBoleto | null>(null);

// —— Columnas: visibles y anchos, recordados por usuario ——
function leerPreferencias(): { visibles?: string[]; anchos?: Record<string, string> } {
  try {
    return JSON.parse(localStorage.getItem(claveDeUsuario(PREFERENCIAS)) ?? "{}");
  } catch {
    return {};
  }
}
const preferencias = leerPreferencias();
const visibles = ref<string[]>(preferencias.visibles?.filter((k) => COLUMNAS.some((c) => c.key === k)) ?? COLUMNAS.filter((c) => !c.oculta).map((c) => c.key));
const anchos = ref<Record<string, string>>(preferencias.anchos ?? {});
watch(
  [visibles, anchos],
  () => {
    try {
      localStorage.setItem(claveDeUsuario(PREFERENCIAS), JSON.stringify({ visibles: visibles.value, anchos: anchos.value }));
    } catch {
      // Sin almacenamiento: solo dura la visita.
    }
  },
  { deep: true },
);

const columnasGrid = computed<GridColumn[]>(() =>
  COLUMNAS.filter((c) => visibles.value.includes(c.key)).map((c) => ({
    ...c,
    width: anchos.value[c.key] ?? c.width,
    sortable: !!c.orden,
    sort: c.orden && c.orden === pagina.value.orden ? pagina.value.dir : null,
  })),
);

function ordenar(key: string) {
  const orden = COLUMNAS.find((c) => c.key === key)?.orden;
  if (orden) pagina.value = alternarOrden(pagina.value, orden);
}
function redimensionar(key: string, ancho: string | null) {
  const resto = Object.fromEntries(Object.entries(anchos.value).filter(([k]) => k !== key));
  anchos.value = ancho ? { ...resto, [key]: ancho } : resto;
}
function ocultar(key: string) {
  if (visibles.value.length > 1) visibles.value = visibles.value.filter((k) => k !== key);
}

// —— Filtros ——
const calendario = useCalendarioSalidasStore();
const borradorModificado = computed(() => borrador.id !== null || borrador.clase !== null || borrador.salida !== null);

/** Pulsar una salida filtra sus boletos; pulsarla otra vez la suelta. */
function elegirSalida(s: SalidaCalendario) {
  borrador.salida = borrador.salida === s.id ? null : s.id;
}
/** «06:30 · Guatemala → Petén» de la salida elegida, si está en el calendario. */
const salidaElegida = computed(() => {
  const s = calendario.datos?.items.find((x) => x.id === borrador.salida);
  if (!s) return "";
  const r = calendario.rutaPorId.get(s.rutaId);
  return [`${fecha(s.fecha)} ${hora(s.fecha)}`, r ? `${r.nombre} (${puntas(r)})` : null].filter(Boolean).join(" · ");
});
// La empresa sale de los controles del calendario.
watch(
  () => calendario.empresa,
  (e) => (borrador.empresa = e),
  { immediate: true },
);

/** Pasa el borrador a filtro aplicado (si cambió algo) y vuelve a la primera página. */
function aplicar() {
  const nuevo = structuredClone(toRaw(borrador));
  if (JSON.stringify(nuevo) === JSON.stringify(aplicado.value)) return;
  aplicado.value = nuevo;
  pagina.value = { ...pagina.value, pagina: 1 };
}
/** Valor numérico de lo escrito (null si está vacío o no hay dígitos): así cada tecla actualiza el filtro. */
function soloNumero(e: Event): number | null {
  const t = (e.target as HTMLInputElement).value.replace(/\D/g, "");
  return t ? Number(t) : null;
}
const CLASES = ["A", "B"] as const;

/** La columna Estado muestra solo esta inicial (como el estado de las salidas en la venta). */
const INICIAL_ESTADO: Record<EstadoBoleto, string> = { emitido: "E", chequeado: "Ch", transito: "T", finalizado: "F", cancelado: "Ca", anulado: "A", reasignado: "R", pospuesto: "P" };
const inicialEstado = (e: string) => INICIAL_ESTADO[e as EstadoBoleto] ?? e.charAt(0).toUpperCase();
/** Igual para el tipo de documento (dos letras donde la inicial se repite). */
const INICIAL_DOCUMENTO: Record<TipoDocumento, string> = { factura: "F", pendiente: "FP", sin_factura: "SF", agencia: "A", cortesia: "C", voucher: "V" };
const inicialDocumento = (d: TipoDocumento) => INICIAL_DOCUMENTO[d] ?? d.charAt(0).toUpperCase();

function limpiar() {
  Object.assign(borrador, { id: null, clase: null, salida: null });
}

// Los filtros se aplican al cambiar; el id espera a que se deje de escribir.
const ESPERA_TEXTO = 500;
let espera: ReturnType<typeof setTimeout> | undefined;
watch(
  () => [borrador.salida, borrador.clase, borrador.empresa],
  () => {
    clearTimeout(espera);
    aplicar();
  },
);
watch(
  () => borrador.id,
  () => {
    clearTimeout(espera);
    espera = setTimeout(aplicar, ESPERA_TEXTO);
  },
);
onBeforeUnmount(() => clearTimeout(espera));

function paginar(e: { page: number; rows: number }) {
  pagina.value = { ...pagina.value, pagina: e.rows === pagina.value.porPagina ? e.page + 1 : 1, porPagina: e.rows };
}

// —— Datos ——
const consulta = computed(() => aQuery(aplicado.value, pagina.value));
let pedido = 0;
async function cargar() {
  const n = ++pedido;
  cargando.value = true;
  error.value = "";
  try {
    const r = await fetchBoletosListado(consulta.value);
    if (n !== pedido) return;
    datos.value = r;
    // El elegido se refresca con sus datos nuevos (o se suelta si ya no está).
    elegido.value = elegido.value ? (r.items.find((b) => b.id === elegido.value!.id) ?? null) : null;
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver los boletos (boleto.ver)." : e instanceof Error ? e.message : String(e);
  } finally {
    if (n === pedido) cargando.value = false;
  }
}
watch(consulta, cargar);

// —— Selección y acciones ——
const seleccion = computed(() => new Set(elegido.value ? [String(elegido.value.id)] : []));
const claseFila = (r: unknown) => ({ "fila-liberada": ["anulado", "reasignado", "cancelado", "pospuesto"].includes((r as BoletoFila).estado) });
const estadoAcciones = computed(() => disponibilidad(elegido.value, opciones.value?.puede ?? { cancelar: false, anular: false, reasignar: false, chequear: false }));

function elegir(b: BoletoFila) {
  elegido.value = elegido.value?.id === b.id ? null : b;
}

async function ejecutar(accion: AccionBoleto) {
  const b = elegido.value;
  if (!b || !estadoAcciones.value[accion].habilitada) return;
  switch (accion) {
    case "consultar":
      consultarId.value = b.id;
      dialogo.value = "consultar";
      break;
    case "bitacora":
      bitacoraId.value = b.id;
      break;
    case "reasignar":
      await router.push({ name: "venta", query: { reasignar: String(b.id) } });
      break;
    case "factura":
      ocupado.value = "factura";
      try {
        const { reimprimirBoletos } = await import("@/shared/boleto/reimprimir");
        await reimprimirBoletos([b.id]);
      } catch (e) {
        notify.error(e instanceof Error ? e.message : "No se pudo reimprimir.");
      } finally {
        ocupado.value = null;
      }
      break;
    case "pdf":
      ocupado.value = "pdf";
      await descargarBoletoPdf(b.id);
      ocupado.value = null;
      break;
    default:
      dialogo.value = accion;
  }
}

function cerrar(visible: boolean) {
  if (visible) return;
  dialogo.value = null;
  if (route.query.id) void router.replace({ query: { ...route.query, id: undefined } });
}

// Con el chat encima, el boleto elegido se ofrece para adjuntar.
anunciarEnPantalla(() => (elegido.value ? [{ tipo: "BoletoAsiento", id: elegido.value.id, etiqueta: `Boleto ${elegido.value.id} · asiento ${elegido.value.asiento} · ${elegido.value.sube} → ${elegido.value.baja}` }] : []));

// —— Celdas ——
/** Columnas con valor corto (alias, username) y el completo en el tooltip; null = se muestra `texto`. */
function corta(b: BoletoFila, key: string): string | null {
  switch (key) {
    case "usuario":
      return b.username ?? null;
    case "estacion":
      return b.estacionAlias ?? null;
    case "sube":
      return b.subeAlias ?? null;
    case "baja":
      return b.bajaAlias ?? null;
    case "ruta":
      return b.rutaCorta ?? null;
    default:
      return null;
  }
}
const f = (r: unknown) => r as BoletoFila;
const cuando = (iso: string | null) => (iso ? `${fecha(iso)} ${hora(iso)}` : "—");
function texto(b: BoletoFila, key: string): string {
  switch (key) {
    case "fecha":
    case "creada":
      return cuando(b[key]);
    case "documento":
      return documentoTexto(b.documento);
    case "clienteDocumento":
      return b.clienteDocumento ? `${b.clienteDocumento.nombre} (${b.clienteDocumento.documento})` : "—";
    case "clienteBoleto":
      return b.clienteBoleto?.nombre ?? "—";
    case "precio":
      return b.precio ? `${b.precio.texto}${b.sinCobro ? " *" : ""}` : "—";
    default: {
      const v = (b as unknown as Record<string, unknown>)[key];
      return v === null || v === undefined || v === "" ? "" : String(v);
    }
  }
}

// `?id=`: filtra por ese boleto y lo abre.
function desdeRuta() {
  const id = Number(route.query.id);
  if (!Number.isInteger(id) || id <= 0) return false;
  Object.assign(borrador, { ...filtroInicial(), empresa: calendario.empresa, id });
  aplicado.value = structuredClone(toRaw(borrador));
  consultarId.value = id;
  dialogo.value = "consultar";
  return true;
}
watch(() => route.query.id, desdeRuta);

onMounted(async () => {
  if (!desdeRuta()) void cargar();
  opciones.value = await fetchOpcionesBoletos().catch(() => null);
});
</script>

<style scoped>
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
/* Teléfono: acciones y columnas en la misma línea, al pie del bloque. */
.acciones {
  display: flex;
  flex-wrap: nowrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}
.acciones-izq {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
  flex: 1 1 0;
}
/* Antes del @container: con la misma especificidad gana la regla posterior. */
.acciones-botones {
  display: none;
  flex-wrap: wrap;
  gap: 0.375rem;
}
@container (width >= 36rem) {
  .acciones {
    flex-wrap: wrap;
    align-items: flex-start;
  }
  .acciones-izq {
    flex-basis: 18rem;
  }
  .acciones-botones {
    display: flex;
  }
}
.acciones-elegido {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.acciones-botones :deep(.p-button) {
  gap: 0.375rem;
}
/* Estado: inicial en un círculo del color del estado (fondo 100, letra 700), igual que en la venta. */
.estado {
  display: inline-flex;
  width: 1.75rem;
  height: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: var(--p-surface-200);
  color: var(--p-surface-700);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: default;
}
.estado--emitido {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--chequeado {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--transito {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--finalizado {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--cancelado {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--reasignado {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--pospuesto {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--anulado {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
/* Tipo de documento: mismo círculo, un color por tipo. */
.documento--factura {
  background: var(--p-surface-200);
  color: var(--p-surface-700);
}
.documento--pendiente {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.documento--sin_factura {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.documento--agencia {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.documento--cortesia {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.documento--voucher {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.chips {
  align-items: center;
}
.filtro-id {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.salida-elegida {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  padding: 0.25rem 0.25rem 0.25rem 0.75rem;
  border: 1px solid var(--p-primary-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
  background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
  font-size: 0.875rem;
}
.tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  width: 100%;
}
:deep(.fila-liberada) {
  color: var(--p-text-muted-color);
}
</style>
