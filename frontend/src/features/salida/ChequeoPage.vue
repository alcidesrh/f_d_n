<!--
  Chequear boletos por salida (ADR-032), el "Chequear Boletos por Salida" del
  legado. Se elige una salida (las que este usuario puede atender: desde 24 h
  atrás hasta 6 h adelante), se pone a abordar, se marca cada boleto como
  presente (SI) o no presentado (NO: queda cancelado y libera el asiento) y
  se inicia (los chequeados pasan a tránsito; los no chequeados que subían en
  el origen quedan cancelados). Al llegar, el destino la finaliza (tránsito →
  finalizado; los que nadie chequeó, cancelados). Los manifiestos y la
  bitácora, a mano.
  La lista se refresca sola si se vende o anula durante el abordaje.
  Contenido angosto: tarjetas; desde 56rem de ancho del contenido: tabla.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <section class="panel flex flex-col gap-3">
      <div class="flex items-end gap-2">
        <label class="flex min-w-0 flex-1 flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Salida</span>
          <Select v-model="salidaId" :options="opcionesSalida" option-label="etiqueta" option-value="id" filter filter-match-mode="contains" :loading="cargandoSalidas" placeholder="Elija una salida" empty-message="No hay salidas por atender" empty-filter-message="Ninguna coincide" fluid>
            <template #option="{ option }">
              <div class="flex w-full items-center justify-between gap-2">
                <span class="min-w-0 truncate">{{ option.etiqueta }}</span>
                <Tag :value="etiquetaEstado(option.estado).etiqueta" :severity="etiquetaEstado(option.estado).severidad" class="!text-xs" />
              </div>
            </template>
          </Select>
        </label>
        <Button severity="secondary" outlined class="tap-target shrink-0" aria-label="Actualizar" v-tooltip.top="'Actualizar'" :loading="cargando" @click="refrescar">
          <template #icon><icon name="refresh" /></template>
        </Button>
      </div>
      <Message v-if="errorSalidas" severity="error" :closable="false">{{ errorSalidas }}</Message>
    </section>

    <Skeleton v-if="cargando && !detalle" height="10rem" />

    <template v-if="detalle">
      <section class="panel flex flex-col gap-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <Tag :value="etiquetaEstado(detalle.salida.estado).etiqueta" :severity="etiquetaEstado(detalle.salida.estado).severidad" />
              <span class="font-semibold">{{ fechaHora(detalle.salida.fecha) }}</span>
            </div>
            <div class="mt-1 text-sm">{{ detalle.salida.ruta }}</div>
            <div class="text-xs text-muted-color">
              <template v-if="detalle.salida.bus">Bus {{ detalle.salida.bus }} · </template><template v-if="detalle.salida.empresa">{{ detalle.salida.empresa }} · </template>#{{ detalle.salida.id }}
            </div>
          </div>
          <dl class="m-0 grid grid-cols-3 gap-x-4 gap-y-0 text-center">
            <div><dt class="text-xs text-muted-color">Boletos</dt><dd class="m-0 text-lg font-semibold tabular-nums">{{ cuenta.total }}</dd></div>
            <div><dt class="text-xs text-muted-color">A bordo</dt><dd class="m-0 text-lg font-semibold tabular-nums text-green-700">{{ cuenta.abordo }}</dd></div>
            <div><dt class="text-xs text-muted-color">Sin chequear</dt><dd class="m-0 text-lg font-semibold tabular-nums">{{ cuenta.pendientes }}</dd></div>
          </dl>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="puedeHacer.abordar" label="Abordar" size="small" :loading="operando === 'abordar'" @click="abordar">
            <template #icon><icon name="login" class="mr-1" /></template>
          </Button>
          <Button v-if="puedeHacer.iniciar" label="Iniciar salida" size="small" severity="warn" :disabled="hayMarcas" v-tooltip.top="hayMarcas ? 'Guarde o descarte el chequeo antes de iniciar' : null" :loading="operando === 'iniciar'" @click="iniciar">
            <template #icon><icon name="departure-board-outline" class="mr-1" /></template>
          </Button>
          <Button v-if="puedeHacer.finalizar" label="Finalizar salida" size="small" severity="contrast" :disabled="hayMarcas" v-tooltip.top="hayMarcas ? 'Guarde o descarte el chequeo antes de finalizar' : null" :loading="operando === 'finalizar'" @click="finalizar">
            <template #icon><icon name="flag-outline" class="mr-1" /></template>
          </Button>
          <template v-if="puede?.manifiestos">
            <Button v-for="m in MANIFIESTOS" :key="m.tipo" :label="m.tipo === 'interno' ? 'Manif. interno' : 'Manif. piloto'" size="small" severity="secondary" outlined :aria-label="m.etiqueta" v-tooltip.top="m.etiqueta" @click="abrirManifiesto(detalle.salida.id, m.tipo)">
              <template #icon><icon :name="m.icono" class="mr-1" /></template>
            </Button>
          </template>
          <Button label="Bitácora" size="small" severity="secondary" text @click="bitacora = true">
            <template #icon><icon name="history" class="mr-1" /></template>
          </Button>
        </div>
        <Message v-if="!detalle.chequeable && detalle.motivo" severity="info" :closable="false">{{ detalle.motivo }}</Message>
      </section>

      <section class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="m-0 text-sm font-semibold uppercase tracking-wide text-muted-color">Boletos</h2>
          <div v-if="chequeables > 0 && puede?.chequear" class="flex gap-1">
            <Button label="Todos presentes" size="small" severity="secondary" text @click="marcas = marcarTodos(marcas, detalle.boletos, 'si')">
              <template #icon><icon name="done-all" class="mr-1" /></template>
            </Button>
            <Button v-if="hayMarcas" label="Quitar marcas" size="small" severity="secondary" text @click="marcas = {}" />
          </div>
        </div>

        <p v-if="!detalle.boletos.length" class="panel m-0 text-center text-sm text-muted-color">La salida no tiene boletos por chequear.</p>

        <!-- Angosto: tarjetas -->
        <div v-else class="flex flex-col gap-2 @4xl:hidden">
          <article v-for="b in detalle.boletos" :key="b.id" class="panel !p-3 flex flex-col gap-2" :class="claseFila(b)">
            <div class="flex items-start gap-3">
              <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-surface-100 text-lg font-semibold tabular-nums" :aria-label="`Asiento ${b.asiento}`">{{ b.asiento }}</div>
              <div class="min-w-0 flex-1">
                <div class="truncate font-medium">{{ b.cliente ?? "—" }}</div>
                <div class="text-sm">{{ b.sube }} → {{ b.baja }}</div>
                <div class="text-xs text-muted-color">{{ b.documento ?? "Sin documento" }} · #{{ b.id }}</div>
              </div>
              <Tag v-if="b.estado !== 'emitido'" :value="ESTADO_BOLETO[b.estado]" severity="success" class="!text-xs" />
            </div>
            <div v-if="b.estado === 'emitido' && puede?.chequear" class="grid grid-cols-2 gap-2">
              <Button label="Presente" size="small" :severity="marcas[b.id] === 'si' ? 'success' : 'secondary'" :outlined="marcas[b.id] !== 'si'" :disabled="!b.chequeable" :aria-pressed="marcas[b.id] === 'si'" @click="marcar(b.id, 'si')" />
              <Button label="No se presentó" size="small" :severity="marcas[b.id] === 'no' ? 'danger' : 'secondary'" :outlined="marcas[b.id] !== 'no'" :disabled="!b.chequeable" :aria-pressed="marcas[b.id] === 'no'" @click="marcar(b.id, 'no')" />
            </div>
          </article>
        </div>

        <!-- Ancho: tabla -->
        <div v-if="detalle.boletos.length" class="hidden @4xl:block">
          <DataTable :value="detalle.boletos" data-key="id" size="small" striped-rows :row-class="claseFila">
            <ColumnGroup type="header">
              <Row>
                <Column header="Boleto" :rowspan="2" />
                <Column header="Chequeado" :colspan="2" header-class="[&>div]:justify-center" />
                <Column header="Asiento" :rowspan="2" />
                <Column header="Documento" :rowspan="2" />
                <Column header="Sube" :rowspan="2" />
                <Column header="Baja" :rowspan="2" />
                <Column header="Cliente" :rowspan="2" />
              </Row>
              <Row>
                <Column header="SI" header-class="[&>div]:justify-center" />
                <Column header="NO" header-class="[&>div]:justify-center" />
              </Row>
            </ColumnGroup>
            <Column field="id" class="tabular-nums" />
            <Column class="w-0 text-center">
              <template #body="{ data }">
                <Checkbox :model-value="data.estado !== 'emitido' || marcas[data.id] === 'si'" binary :disabled="!data.chequeable || !puede?.chequear" :input-id="`si-${data.id}`" :aria-label="`Asiento ${data.asiento}: se presentó`" @update:model-value="marcar(data.id, 'si')" />
              </template>
            </Column>
            <Column class="w-0 text-center">
              <template #body="{ data }">
                <Checkbox :model-value="marcas[data.id] === 'no'" binary :disabled="!data.chequeable || !puede?.chequear" :input-id="`no-${data.id}`" :aria-label="`Asiento ${data.asiento}: no se presentó`" @update:model-value="marcar(data.id, 'no')" />
              </template>
            </Column>
            <Column header="Asiento">
              <template #body="{ data }">
                <div class="flex items-center gap-2 whitespace-nowrap">
                  <span class="font-semibold tabular-nums">{{ data.asiento }}</span>
                  <Tag v-if="data.estado !== 'emitido'" :value="ESTADO_BOLETO[data.estado]" severity="success" class="!text-xs" />
                </div>
              </template>
            </Column>
            <Column header="Documento">
              <template #body="{ data }">{{ data.documento ?? "—" }}</template>
            </Column>
            <Column field="sube" header="Sube" />
            <Column field="baja" header="Baja" />
            <Column header="Cliente">
              <template #body="{ data }">{{ data.cliente ?? "—" }}</template>
            </Column>
          </DataTable>
        </div>
      </section>
    </template>

    <p v-else-if="!cargando && !salidaId && !cargandoSalidas" class="panel m-0 text-center text-sm text-muted-color">Elija una salida para ver sus boletos.</p>

    <!-- Barra para guardar el chequeo -->
    <div v-if="detalle && hayMarcas" class="sticky bottom-0 z-10 rounded-lg border border-surface-200 bg-surface-0 px-4 py-3 shadow-lg">
      <div class="flex flex-wrap items-center justify-end gap-3">
        <span class="text-sm">
          <strong class="tabular-nums">{{ envio.si.length }}</strong> presente(s)<template v-if="envio.no.length">
            · <strong class="tabular-nums text-red-600">{{ envio.no.length }}</strong> no se presentó</template
          >
        </span>
        <Button label="Descartar" size="small" severity="secondary" text @click="marcas = {}" />
        <Button label="Guardar chequeo" size="small" :loading="operando === 'chequear'" @click="guardar">
          <template #icon><icon name="check" class="mr-1" /></template>
        </Button>
      </div>
    </div>

    <BitacoraDialog v-if="bitacora && detalle" :visible="true" tipo="salida" :id="detalle.salida.id" @update:visible="(v: boolean) => !v && (bitacora = false)" />
  </div>
</template>

<script setup lang="ts">
import { abordarSalida, chequearBoletos, fetchDetalleAbordaje, fetchSalidasAbordaje, finalizarSalida, iniciarSalida } from "@/core/abordaje/api";
import { acciones, alternar, conteo, etiquetaSalida, marcarTodos, seleccion, vigentes, type Marca, type Marcas } from "@/core/abordaje/chequeo";
import type { BoletoAbordaje, DetalleAbordaje, PuedeAbordaje, SalidaAbordaje } from "@/core/abordaje/types";
import { etiquetaEstado } from "@/core/salida/filtro";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { suscribir } from "@/core/realtime";
import BitacoraDialog from "@/shared/bitacora/BitacoraDialog.vue";
import { useConfirm } from "primevue/useconfirm";
import { abrirManifiesto, MANIFIESTOS } from "./manifiesto";

const ESTADO_BOLETO: Record<string, string> = { chequeado: "Chequeado", transito: "En tránsito", finalizado: "Finalizado" };

const route = useRoute();
const router = useRouter();
const confirm = useConfirm();

const salidas = shallowRef<SalidaAbordaje[]>([]);
const puede = shallowRef<PuedeAbordaje | null>(null);
const cargandoSalidas = ref(false);
const errorSalidas = ref("");
const detalle = shallowRef<DetalleAbordaje | null>(null);
const cargando = ref(false);
const marcas = ref<Marcas>({});
const operando = ref<"abordar" | "chequear" | "iniciar" | "finalizar" | null>(null);
const bitacora = ref(false);

const salidaId = computed<number | null>({
  get: () => (Number(route.query.salida) > 0 ? Number(route.query.salida) : null),
  set: (id) => void router.replace({ query: { ...route.query, salida: id ?? undefined } }),
});

/** Las del selector, más la abierta si no está (llegó por enlace). */
const opcionesSalida = computed(() => {
  const lista = [...salidas.value];
  const actual = detalle.value?.salida;
  if (actual && !lista.some((s) => s.id === actual.id)) lista.unshift(actual);
  return lista.map((s) => ({ ...s, etiqueta: etiquetaSalida(s) }));
});

const cuenta = computed(() => conteo(detalle.value?.boletos ?? []));
const chequeables = computed(() => detalle.value?.boletos.filter((b) => b.chequeable).length ?? 0);
const hayMarcas = computed(() => Object.keys(marcas.value).length > 0);
const envio = computed(() => seleccion(marcas.value));
const puedeHacer = computed(() => (detalle.value && puede.value ? acciones(detalle.value.salida, puede.value) : { abordar: false, iniciar: false, finalizar: false }));

const fechaHora = (iso: string) => new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "2-digit", month: "short", year: "numeric", hour: "numeric", minute: "2-digit" }).format(new Date(iso));

function claseFila(b: BoletoAbordaje) {
  const m = marcas.value[b.id];
  if (m === "si") return "!bg-green-50";
  if (m === "no") return "!bg-red-50";
  return b.estado === "emitido" && !b.chequeable ? "opacity-70" : "";
}

function marcar(id: number, marca: Marca) {
  marcas.value = alternar(marcas.value, id, marca);
}

const mensaje = (e: unknown) => (e instanceof HttpError && e.status === 403 && !(e.body as { error?: string } | null)?.error ? "No tiene permiso para esta operación." : e instanceof Error ? e.message : String(e));

async function cargarSalidas() {
  cargandoSalidas.value = true;
  errorSalidas.value = "";
  try {
    const r = await fetchSalidasAbordaje();
    salidas.value = r.items;
    puede.value = r.puede;
  } catch (e) {
    errorSalidas.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para chequear boletos ni abordar salidas." : mensaje(e);
  } finally {
    cargandoSalidas.value = false;
  }
}

let pedido = 0;
async function cargarDetalle(silencioso = false) {
  const id = salidaId.value;
  const n = ++pedido;
  if (id === null) {
    detalle.value = null;
    return;
  }
  if (!silencioso) cargando.value = true;
  try {
    const d = await fetchDetalleAbordaje(id, silencioso);
    if (n !== pedido) return;
    detalle.value = d;
    marcas.value = vigentes(marcas.value, d.boletos);
  } catch (e) {
    if (n !== pedido) return;
    if (!silencioso) {
      detalle.value = null;
      notify.error(mensaje(e));
    }
  } finally {
    if (n === pedido) cargando.value = false;
  }
}

function refrescar() {
  void cargarSalidas();
  void cargarDetalle();
}

watch(salidaId, (nueva, anterior) => {
  if (nueva !== anterior) marcas.value = {};
  void cargarDetalle();
});

// Una venta, anulación o reasignación durante el abordaje cambia la lista.
let desuscribir: (() => void) | null = null;
watch(
  () => detalle.value?.topico,
  (topico) => {
    desuscribir?.();
    desuscribir = topico ? suscribir(topico, () => void cargarDetalle(true)) : null;
  },
);
onBeforeUnmount(() => desuscribir?.());

async function ejecutar<T>(op: NonNullable<typeof operando.value>, accion: () => Promise<T>): Promise<T | null> {
  operando.value = op;
  try {
    return await accion();
  } catch (e) {
    notify.error(mensaje(e));
    return null;
  } finally {
    operando.value = null;
    void cargarDetalle(true);
    void cargarSalidas();
  }
}

async function abordar() {
  const d = detalle.value;
  if (!d) return;
  const r = await ejecutar("abordar", () => abordarSalida(d.salida.id));
  if (r) notify.success("La salida está abordando: ya se pueden chequear los boletos.");
}

async function enviarChequeo(id: number) {
  const s = envio.value;
  const r = await ejecutar("chequear", () => chequearBoletos(id, s));
  if (!r) return;
  marcas.value = {};
  notify.success([`${r.chequeados} boleto(s) chequeado(s)`, r.cancelados ? `${r.cancelados} no se presentó` : null].filter(Boolean).join(" · "));
}

function guardar() {
  const d = detalle.value;
  if (!d) return;
  const no = envio.value.no.length;
  if (no === 0) {
    void enviarChequeo(d.salida.id);
    return;
  }
  confirm.require({
    header: "Guardar chequeo",
    message: `${no} boleto(s) quedarán como "no se presentó": el asiento se libera y no se puede deshacer desde aquí. ¿Continuar?`,
    acceptProps: { label: "Guardar", severity: "danger" },
    rejectProps: { label: "Volver", severity: "secondary", outlined: true },
    accept: () => void enviarChequeo(d.salida.id),
  });
}

function iniciar() {
  const d = detalle.value;
  if (!d) return;
  const { pendientesOrigen } = cuenta.value;
  confirm.require({
    header: "Iniciar salida",
    message: [
      "Los boletos chequeados pasan a tránsito.",
      pendientesOrigen ? `${pendientesOrigen} boleto(s) sin chequear que suben en el origen quedarán como "no se presentó" y liberan su asiento.` : null,
      "Después ya no se vende en esta salida. ¿Iniciar?",
    ]
      .filter(Boolean)
      .join(" "),
    acceptProps: { label: "Iniciar salida", severity: "warn" },
    rejectProps: { label: "Volver", severity: "secondary", outlined: true },
    accept: async () => {
      const r = await ejecutar("iniciar", () => iniciarSalida(d.salida.id));
      if (r) notify.success([`Salida iniciada: ${r.enTransito} en tránsito`, r.cancelados ? `${r.cancelados} no se presentó` : null].filter(Boolean).join(" · "));
    },
  });
}

function finalizar() {
  const d = detalle.value;
  if (!d) return;
  const { pendientes } = cuenta.value;
  confirm.require({
    header: "Finalizar salida",
    message: [
      "La salida llegó a su destino: los boletos en tránsito quedan finalizados.",
      pendientes ? `${pendientes} boleto(s) que nadie chequeó quedarán como "no se presentó".` : null,
      "Una salida finalizada ya no se modifica. ¿Finalizar?",
    ]
      .filter(Boolean)
      .join(" "),
    acceptProps: { label: "Finalizar salida", severity: "contrast" },
    rejectProps: { label: "Volver", severity: "secondary", outlined: true },
    accept: async () => {
      const r = await ejecutar("finalizar", () => finalizarSalida(d.salida.id));
      if (r) notify.success([`Salida finalizada: ${r.finalizados} viaje(s) finalizado(s)`, r.cancelados ? `${r.cancelados} no se presentó` : null].filter(Boolean).join(" · "));
    },
  });
}

onMounted(() => {
  void cargarSalidas();
  void cargarDetalle();
});
</script>
