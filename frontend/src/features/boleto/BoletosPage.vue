<!--
  Boletos (el "Boletos" del legado): filtros arriba (siempre visibles, se
  aplican al cambiar), el panel de acciones sobre el boleto elegido y la lista
  paginada en el servidor. Se elige un boleto con un clic (o Enter) y el panel
  ofrece Consultar, Cancelar, Revertir, Anular, Chequear, Reasignar, Correo, Imp.
  factura, Bitácora y Chat; cada acción explica por qué no se puede si no se
  puede. Columnas ordenables, redimensionables y ocultables (se recuerdan en
  el navegador). `?id=` abre ese boleto (enlaces del chat).
  Contenido angosto: tarjetas; filtros en una o dos columnas.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <!-- Filtros: siempre visibles; se aplican al cambiar (los de texto, con retardo) -->
    <Accordion class="mb-6">
      <AccordionPanel value="0">
        <AccordionHeader>
          <div class="flex items-center gap-1 font-medium text-xl text-surface-700"><icon name="manage_search" size="1.8rem" :weight="300" />Filtros</div>
        </AccordionHeader>
        <AccordionContent>
          <section class="panel grid grid-cols-2 items-end gap-x-3 gap-y-2 !p-3 @2xl:grid-cols-4 @5xl:grid-cols-6" aria-label="Filtros">
            <label class="campo col-span-2">
              <span>Rango de fechas <span class="font-normal">(salida o venta)</span></span>
              <DatePicker v-model="rango" selection-mode="range" :manual-input="false" date-format="dd/mm/yy" show-icon show-button-bar size="small" :placeholder="`${rangoPorDefecto}`" fluid @hide="completarRango" />
            </label>
            <label class="campo col-span-2">
              <span>Origen (sube en)</span>
              <estacion-select v-model="borrador.origen" :estaciones="opciones?.estaciones ?? []" placeholder="Todas" size="small" />
            </label>
            <label class="campo col-span-2">
              <span>Destino (baja en)</span>
              <estacion-select v-model="borrador.destino" :estaciones="opciones?.estaciones ?? []" placeholder="Todas" size="small" />
            </label>
            <label class="campo col-span-2 @5xl:col-span-3">
              <span
                >Salida <span class="font-normal">({{ rango ? "del rango elegido" : "de hoy" }})</span></span
              >
              <SalidaSelect v-model="borrador.salida" :desde="borrador.desde" :hasta="borrador.hasta ?? borrador.desde" placeholder="Buscar salida" size="small" />
            </label>
            <label class="campo">
              <span>Id</span>
              <InputText :model-value="String(borrador.id ?? '')" inputmode="numeric" size="small" fluid @input="borrador.id = soloNumero($event)" />
            </label>
            <label class="campo">
              <span>Asiento #</span>
              <InputText :model-value="String(borrador.asiento ?? '')" inputmode="numeric" size="small" fluid @input="borrador.asiento = soloNumero($event)" />
            </label>
            <div class="campo col-span-2 @5xl:col-span-1">
              <span>Clase</span>
              <div class="chips" role="group" aria-label="Clase de asiento">
                <button v-for="c in CLASES" :key="c" type="button" class="chip" :class="{ 'chip-on': borrador.clase === c }" :aria-pressed="borrador.clase === c" @click="borrador.clase = borrador.clase === c ? null : c">{{ c }}</button>
              </div>
            </div>
            <div class="campo col-span-2 @2xl:col-span-4 @5xl:col-span-6">
              <span>Estado</span>
              <div class="chips" role="group" aria-label="Estado">
                <button v-for="e in ESTADOS" :key="e.valor" type="button" class="chip" :class="{ 'chip-on': borrador.estados.includes(e.valor) }" :aria-pressed="borrador.estados.includes(e.valor)" @click="alternarEstado(e.valor)">
                  {{ e.etiqueta }} <span class="opacity-70">({{ inicialEstado(e.valor) }})</span>
                </button>
              </div>
            </div>
            <div class="campo col-span-2 @2xl:col-span-3 @5xl:col-span-5">
              <span>Tipo de documento</span>
              <div class="chips" role="group" aria-label="Tipo de documento">
                <button v-for="d in DOCUMENTOS" :key="d.valor" type="button" class="chip" :class="{ 'chip-on': borrador.documento === d.valor }" :aria-pressed="borrador.documento === d.valor" @click="borrador.documento = borrador.documento === d.valor ? null : d.valor">
                  {{ d.etiqueta }} <span class="opacity-70">({{ inicialDocumento(d.valor) }})</span>
                </button>
              </div>
            </div>
            <div class="col-span-2 flex justify-end @2xl:col-span-1">
              <Button type="button" label="Limpiar" severity="secondary" text size="small" :disabled="!borradorModificado" @click="limpiar"
                ><template #icon><icon name="filter-alt-off-outline" class="mr-1" /></template
              ></Button>
            </div>
          </section>
        </AccordionContent>
      </AccordionPanel>
    </Accordion>

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
        <div class="acciones-botones">
          <template v-for="a in botones" :key="a.accion">
            <span v-if="estadoAcciones[a.accion].visible" v-tooltip.bottom="estadoAcciones[a.accion].motivo ?? a.ayuda">
              <Button :severity="a.severidad ?? 'secondary'" :outlined="a.severidad === undefined" size="small" :disabled="!estadoAcciones[a.accion].habilitada || ocupado === a.accion" :loading="ocupado === a.accion" :aria-label="a.etiqueta" @click="ejecutar(a.accion)">
                <icon :name="a.icono" />
                <span class="hidden @xl:inline">{{ a.etiqueta }}</span>
              </Button>
            </span>
          </template>
        </div>
      </div>
      <MultiSelect v-model="visibles" :options="COLUMNAS" option-label="label" option-value="key" :max-selected-labels="0" selected-items-label="Columnas ({0})" placeholder="Columnas" size="small" class="w-44 shrink-0">
        <template #dropdownicon><icon name="view-column-outline" /></template>
      </MultiSelect>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataGrid :columns="columnasGrid" :rows="datos?.items ?? []" :row-key="(r) => String((r as BoletoFila).id)" :loading="cargando" :selected-keys="seleccion" density="compact" :card-fields="4" max-height="calc(100dvh - 14rem)" row-clickable resizable hideable :row-class="claseFila" @row-click="elegir($event as BoletoFila)" @sort="ordenar" @resize="redimensionar" @hide="ocultar">
      <template #cell="{ row, column }">
        <span v-if="column.key === 'estado'" class="estado" :class="`estado--${f(row).estado}`" :title="etiquetaEstado(f(row).estado).etiqueta">{{ inicialEstado(f(row).estado) }}</span>
        <span v-else-if="column.key === 'documento'" class="estado" :class="`documento--${f(row).documento.tipo}`" :title="texto(f(row), 'documento')">{{ inicialDocumento(f(row).documento.tipo) }}</span>
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
import { fetchBoletosListado, fetchOpcionesBoletos } from "@/core/boleto/api";
import { aQuery, alternarOrden, disponibilidad, DOCUMENTOS, documentoTexto, ESTADOS, etiquetaEstado, filtroInicial, paginaInicial, type AccionBoleto, type FiltroBoletos, type Pagina } from "@/core/boleto/listado";
import type { EstadoBoleto, TipoDocumento } from "@/core/boleto/types";
import SalidaSelect from "@/shared/ui/SalidaSelect.vue";
import type { BoletoFila, OpcionesBoletos, OrdenBoletos, PaginaBoletos } from "@/core/boleto/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { deDia } from "@/core/salida/filtro";
import { diaISO, fecha, hora } from "@/core/venta/modelo";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import AnularBoletosDialog from "@/shared/boleto/AnularBoletosDialog.vue";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import { anunciarEnPantalla } from "@/shared/chat/integracion";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import CancelarBoletoDialog from "./CancelarBoletoDialog.vue";
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

/** Las del legado, en su orden. */
const COLUMNAS: Columna[] = [
  { key: "id", label: "ID", width: "5.5rem", orden: "id", align: "end" },
  { key: "fecha", label: "Fecha salida", width: "10rem", orden: "fecha" },
  { key: "asiento", label: "Número", width: "5.5rem", orden: "numero", align: "end" },
  { key: "estado", label: "Estado", width: "5.5rem", align: "center" },
  { key: "documento", label: "Documento", width: "6.5rem", align: "center" },
  { key: "ruta", label: "Ruta", width: "2fr", minWidth: "14rem", orden: "ruta" },
  { key: "sube", label: "Sube en", width: "10rem" },
  { key: "baja", label: "Baja en", width: "10rem" },
  { key: "clase", label: "Clase", width: "4.5rem", align: "center", oculta: true },
  { key: "referenciaExterna", label: "Ref. externa", width: "9rem", oculta: true },
  { key: "autorizacion", label: "Autorización tarjeta", width: "9rem", oculta: true },
  { key: "clienteDocumento", label: "Cliente documento", width: "14rem", orden: "clienteDocumento" },
  { key: "clienteBoleto", label: "Cliente boleto", width: "14rem", orden: "clienteBoleto" },
  { key: "tipoPago", label: "Tipo pago", width: "7rem" },
  { key: "precio", label: "Precio", width: "7rem", align: "end" },
  { key: "reasignadoDe", label: "Reasignado de", width: "7rem", align: "end", oculta: true },
  { key: "empresa", label: "Empresa", width: "8rem", oculta: true },
  { key: "creada", label: "Fecha creación", width: "10rem", orden: "creada" },
  { key: "usuario", label: "Usuario creación", width: "10rem" },
  { key: "estacion", label: "Estación creación", width: "10rem" },
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
  { accion: "bitacora", etiqueta: "Bitácora", icono: "history", ayuda: "Historial del boleto" },
  { accion: "chat", etiqueta: "Chat", icono: "forum-outline", ayuda: "Enviar el boleto por el chat" },
];
const botones = BOTONES;

const PREFERENCIAS = "boletos.columnas";
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

// —— Columnas: visibles y anchos, recordados en el navegador ——
function leerPreferencias(): { visibles?: string[]; anchos?: Record<string, string> } {
  try {
    return JSON.parse(localStorage.getItem(PREFERENCIAS) ?? "{}");
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
      localStorage.setItem(PREFERENCIAS, JSON.stringify({ visibles: visibles.value, anchos: anchos.value }));
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
/** Mientras se elige el rango el fin queda vacío; al filtrar, un solo día es desde y hasta. */
const rango = computed<Array<Date | null> | null>({
  get: () => (borrador.desde ? [deDia(borrador.desde), borrador.hasta ? deDia(borrador.hasta) : null] : null),
  set: (v) => {
    borrador.desde = v?.[0] ? diaISO(v[0]) : null;
    borrador.hasta = v?.[1] ? diaISO(v[1]) : null;
  },
});
const corta = (dia: string) => dia.split("-").reverse().join("/");
const rangoPorDefecto = computed(() => (opciones.value ? `${corta(opciones.value.desde)} – ${corta(opciones.value.hasta)}` : "±30 días"));
const rangoTexto = computed(() => {
  if (aplicado.value.id !== null) return `Boleto ${aplicado.value.id}`;
  return datos.value ? `${corta(datos.value.desde)} – ${corta(datos.value.hasta)}` : "";
});
const borradorModificado = computed(() => JSON.stringify(borrador) !== JSON.stringify(filtroInicial()));

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
/** Un solo día es desde y hasta: se completa al cerrar el calendario. */
function completarRango() {
  if (borrador.desde && !borrador.hasta) borrador.hasta = borrador.desde;
}
const CLASES = ["A", "B"] as const;
function alternarEstado(e: EstadoBoleto) {
  borrador.estados = borrador.estados.includes(e) ? borrador.estados.filter((x) => x !== e) : [...borrador.estados, e];
}

/** La columna Estado muestra solo esta inicial (como el estado de las salidas en la venta). */
const INICIAL_ESTADO: Record<EstadoBoleto, string> = { emitido: "E", chequeado: "Ch", transito: "T", finalizado: "F", cancelado: "Ca", anulado: "A", reasignado: "R" };
const inicialEstado = (e: string) => INICIAL_ESTADO[e as EstadoBoleto] ?? e.charAt(0).toUpperCase();
/** Igual para el tipo de documento (dos letras donde la inicial se repite). */
const INICIAL_DOCUMENTO: Record<TipoDocumento, string> = { factura: "F", pendiente: "FP", sin_factura: "SF", agencia: "A", cortesia: "C", voucher: "V" };
const inicialDocumento = (d: TipoDocumento) => INICIAL_DOCUMENTO[d] ?? d.charAt(0).toUpperCase();

function limpiar() {
  Object.assign(borrador, filtroInicial());
}

// Los filtros se aplican al cambiar; los de texto (id, asiento) esperan a que se deje de escribir.
// Con el rango a medias (solo «desde») se espera al segundo día.
const ESPERA_TEXTO = 500;
let espera: ReturnType<typeof setTimeout> | undefined;
watch(
  () => [borrador.desde, borrador.hasta, borrador.salida, borrador.origen, borrador.destino, borrador.clase, borrador.documento, borrador.estados.join(",")],
  () => {
    if (borrador.desde && !borrador.hasta) return;
    clearTimeout(espera);
    aplicar();
  },
);
watch(
  () => [borrador.id, borrador.asiento],
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
const claseFila = (r: unknown) => ({ "fila-liberada": ["anulado", "reasignado", "cancelado"].includes((r as BoletoFila).estado) });
const estadoAcciones = computed(() => disponibilidad(elegido.value, opciones.value?.puede ?? { cancelar: false, anular: false, reasignar: false, chequear: false }, new Date()));

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
  Object.assign(borrador, { ...filtroInicial(), id });
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
.campo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.campo > span:first-child,
.campo > label:first-child {
  font-size: 0.75rem;
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
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem 1rem;
}
.acciones-izq {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
  flex: 1 1 18rem;
}
.acciones-elegido {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.acciones-botones {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
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
  background: var(--p-blue-100);
  color: var(--p-blue-700);
}
.estado--chequeado {
  background: var(--p-emerald-100);
  color: var(--p-emerald-700);
}
.estado--transito {
  background: var(--p-amber-100);
  color: var(--p-amber-700);
}
.estado--finalizado {
  background: var(--p-slate-100);
  color: var(--p-slate-700);
}
.estado--cancelado {
  background: var(--p-orange-100);
  color: var(--p-orange-700);
}
.estado--reasignado {
  background: var(--p-neutral-200);
  color: var(--p-neutral-700);
  box-shadow: inset 0 0 0 1px var(--p-neutral-300);
}
.estado--anulado {
  background: var(--p-surface-200);
  color: var(--p-surface-600);
  box-shadow: inset 0 0 0 1px var(--p-surface-300);
  text-decoration: line-through;
}
/* Tipo de documento: mismo círculo, un color por tipo. */
.documento--factura {
  background: var(--p-teal-100);
  color: var(--p-teal-700);
}
.documento--pendiente {
  background: var(--p-yellow-100);
  color: var(--p-yellow-700);
}
.documento--sin_factura {
  background: var(--p-slate-100);
  color: var(--p-slate-700);
}
.documento--agencia {
  background: var(--p-violet-100);
  color: var(--p-violet-700);
}
.documento--cortesia {
  background: var(--p-pink-100);
  color: var(--p-pink-700);
}
.documento--voucher {
  background: var(--p-red-100);
  color: var(--p-red-700);
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
