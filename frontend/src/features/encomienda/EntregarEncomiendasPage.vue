<!--
  Entregar encomiendas (la «Entrega múltiple» y «Pendientes de entrega» del
  legado, ADR-035): lo que se puede entregar en la estación —desembarcado
  aquí o en tránsito en un bus que llega aquí— con buscador por código o
  cliente y lector de código de barras (marca la escaneada). Se marcan las
  que retira una misma persona y se entregan juntas: quien retira y, si hay
  por cobrar, a quién se factura (cada una con su factura). Al terminar
  imprime los comprobantes de lo cobrado.

  Ancho: lista a la izquierda y la entrega fija a la derecha; angosto, la
  entrega debajo.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <section class="panel flex flex-wrap items-end gap-3" aria-label="Estación y búsqueda">
      <label v-if="!contexto?.estacion" class="campo min-w-56 flex-1 @3xl:max-w-xs">
        <span>Estación</span>
        <estacion-select v-model="estacion" :estaciones="contexto?.estaciones ?? []" :show-clear="false" placeholder="Elija la estación" @update:model-value="cargar()" />
      </label>
      <div v-else class="campo">
        <span>Estación</span>
        <span class="font-semibold">{{ contexto.estacion.nombre }}</span>
      </div>
      <div class="campo min-w-64 flex-[2]">
        <span>Código, cliente o NIT</span>
        <IconField>
          <InputIcon><icon name="barcode-scanner" /></InputIcon>
          <InputText ref="lector" v-model="busqueda" placeholder="Escanee para marcar o escriba y Enter para buscar" fluid @keydown.enter.prevent="buscar" />
          <InputIcon v-if="filtro" class="cursor-pointer" role="button" aria-label="Quitar búsqueda" @click="limpiarBusqueda"><icon name="close" /></InputIcon>
        </IconField>
      </div>
      <Button severity="secondary" text :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar()">
        <template #icon><icon name="refresh" /></template>
      </Button>
      <p v-if="aviso" class="m-0 basis-full text-sm" :class="aviso.ok ? 'text-green-700' : 'text-red-600'" aria-live="polite">{{ aviso.texto }}</p>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <div class="grid grid-cols-1 items-start gap-3 @4xl:grid-cols-[minmax(0,1fr)_22rem]">
      <Fieldset :legend="`${filtro ? `Resultados de «${filtro}»` : `Por entregar en ${nombreEstacion}`} (${filas.length})`" class="min-w-0">
          <div class="flex flex-col gap-2">
          <div v-if="filas.length" class="flex justify-end">
            <label class="flex items-center gap-2 text-sm text-muted-color">
              <Checkbox :model-value="todas" :indeterminate="marcadas.length > 0 && !todas" binary @update:model-value="alternarTodas" />
              Todas
            </label>
          </div>
          <Skeleton v-if="cargando && !filas.length" height="10rem" />
          <p v-else-if="!estacionId" class="m-0 py-6 text-center text-sm text-muted-color">Elija la estación.</p>
          <div v-else-if="!filas.length" class="flex flex-col items-center gap-2 py-8 text-muted-color">
            <icon name="inventory-2-outline" size="2rem" />
            <span class="text-sm">{{ filtro ? "Nada por entregar coincide con la búsqueda." : "No hay encomiendas por entregar aquí." }}</span>
          </div>
          <ul v-else class="m-0 flex max-h-[min(36rem,65dvh)] list-none flex-col gap-1 overflow-y-auto p-0">
            <li v-for="f in filas" :key="f.id">
              <label class="fila" :class="{ 'fila-on': marcadas.includes(f.id) }">
                <Checkbox :model-value="marcadas.includes(f.id)" binary @update:model-value="alternar(f.id)" />
                <span class="min-w-0">
                  <span class="flex flex-wrap items-baseline gap-x-2">
                    <b class="font-mono">{{ f.codigo }}</b>
                    <span class="truncate font-medium">{{ f.destinatario.nombre }}</span>
                    <span class="truncate text-xs text-muted-color">de {{ f.remitente.nombre }}</span>
                  </span>
                  <span class="block truncate text-xs text-muted-color">
                    {{ f.origen.nombre }} · {{ f.bultos }} {{ f.bultos === 1 ? "bulto" : "bultos" }} · {{ f.contenido }}
                  </span>
                </span>
                <span class="flex shrink-0 flex-col items-end gap-1">
                  <Tag v-if="f.estado === 'transito'" severity="info" class="!text-xs" :value="f.salida?.bus ? `En bus ${f.salida.bus}` : 'En tránsito'" v-tooltip.left="'Llega en un bus: se desembarca al entregarla'" />
                  <Tag v-if="f.porCobrar" severity="warn" class="!text-xs tabular-nums" :value="`Cobrar ${f.total.texto}`" />
                  <span v-else-if="f.modalidad === 'interna'" class="text-xs text-muted-color">Interna</span>
                  <span class="text-xs text-muted-color">{{ dias(f) }}</span>
                </span>
              </label>
            </li>
          </ul>
          <div v-if="sugerencia" class="flex flex-wrap items-center gap-2 rounded-lg bg-emphasis px-3 py-2 text-sm">
            <icon name="lightbulb-outline" />
            <span class="min-w-0 flex-1">{{ sugerencia.nombre }} tiene {{ sugerencia.ids.length }} {{ sugerencia.ids.length === 1 ? "encomienda más" : "encomiendas más" }} por retirar aquí.</span>
            <Button size="small" text label="Marcarlas" @click="marcadas = [...marcadas, ...sugerencia.ids]" />
          </div>
        </div>
      </Fieldset>

      <Fieldset legend="Entrega" class="entrega">
          <div class="flex flex-col gap-3">
          <p v-if="!resumen.cantidad" class="m-0 text-sm text-muted-color">Marque las encomiendas que retira una misma persona.</p>
          <template v-else>
            <div class="grid grid-cols-2 gap-2">
              <div class="cifra"><span>Encomiendas</span><strong>{{ resumen.cantidad }}</strong></div>
              <div class="cifra"><span>Bultos</span><strong>{{ resumen.bultos }}</strong></div>
            </div>
            <div class="flex flex-col gap-1">
              <label for="ent-receptor" class="text-sm font-medium">La retira</label>
              <ClienteBuscador v-model="receptor" input-id="ent-receptor" :placeholder="placeholderReceptor" />
              <small v-if="!receptor && resumen.destinatarios.length > 1" class="text-muted-color">Sin elegir, cada una la retira su destinatario.</small>
            </div>
            <template v-if="resumen.porCobrar">
              <div class="flex items-baseline justify-between rounded-lg bg-emphasis p-3">
                <span class="text-sm text-muted-color">Por cobrar ({{ resumen.porCobrar }})</span>
                <span class="text-2xl font-semibold tabular-nums">{{ importe(resumen.cobrar) }}</span>
              </div>
              <div class="flex flex-col gap-1">
                <label for="ent-factura" class="text-sm font-medium">Facturar a</label>
                <ClienteBuscador v-model="clienteFactura" input-id="ent-factura" placeholder="Quien la retira" />
                <small class="text-muted-color">Cada encomienda por cobrar lleva su propia factura.</small>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label class="flex flex-col gap-1">
                  <span class="text-sm font-medium">Tipo de pago</span>
                  <Select v-model="tipoPago" :options="contexto?.tiposPago ?? []" option-label="nombre" option-value="id" size="small" fluid />
                </label>
                <label class="flex flex-col gap-1">
                  <span class="text-sm font-medium">Moneda</span>
                  <Select v-model="moneda" :options="contexto?.monedas ?? []" option-label="sigla" option-value="id" size="small" fluid />
                </label>
              </div>
            </template>
            <Button :label="resumen.porCobrar ? `Cobrar y entregar (${resumen.cantidad})` : `Entregar (${resumen.cantidad})`" :loading="enviando" :disabled="!contexto?.permisos.entregar" @click="entregar(marcadas, false)">
              <template #icon><icon name="handshake-outline" class="mr-1.5" /></template>
            </Button>
          </template>
        </div>
      </Fieldset>
    </div>

    <Dialog :visible="fallidas.length > 0" modal header="Algunas no se entregaron" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="!$event && (fallidas = [])">
      <ul class="m-0 flex list-none flex-col gap-2 p-0 text-sm">
        <li v-for="x in fallidas" :key="x.id"><b class="font-mono">{{ x.codigo }}</b>: {{ x.error }}</li>
      </ul>
      <p v-if="contingencia.length" class="mb-0 mt-3 text-sm text-muted-color">
        El certificador no respondió. Puede reintentar o entregarlas en contingencia: se cobran ahora y la factura se certifica después.
      </p>
      <template #footer>
        <Button v-if="reintentables.length" label="Reintentar" severity="secondary" outlined :loading="enviando" @click="entregar(reintentables, false)" />
        <Button v-if="contingencia.length" label="Entregar en contingencia" severity="warn" :loading="enviando" @click="entregar(contingencia, true)" />
        <Button label="Cerrar" text @click="fallidas = []" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, shallowRef } from "vue";
import { entregarEncomiendas, fetchContextoEncomienda, fetchEncomienda, fetchPendientesEntrega } from "@/core/encomienda/api";
import { antiguedad, codigoAId, resumenEntrega } from "@/core/encomienda/reglas";
import type { ContextoEncomienda, FallidaEntrega, FilaEncomienda } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import type { Cliente } from "@/core/venta/types";
import { importe as formatear } from "@/core/reporte/filtro";
import { notify } from "@/core/notify";
import ClienteBuscador from "@/shared/cliente/ClienteBuscador.vue";
import { imprimirRecibos } from "@/shared/encomienda/impresion";

const contexto = shallowRef<ContextoEncomienda | null>(null);
const estacion = ref<number | null>(null);
const filas = ref<FilaEncomienda[]>([]);
const marcadas = ref<number[]>([]);
const busqueda = ref("");
/** Búsqueda aplicada (la lista muestra solo lo que coincide). */
const filtro = ref("");
const cargando = ref(false);
const enviando = ref(false);
const error = ref("");
const aviso = ref<{ ok: boolean; texto: string } | null>(null);
const fallidas = ref<FallidaEntrega[]>([]);
const receptor = ref<Cliente | null>(null);
const clienteFactura = ref<Cliente | null>(null);
const tipoPago = ref<number | null>(null);
const moneda = ref<number | null>(null);
const lector = ref<{ $el: HTMLInputElement } | null>(null);

const estacionId = computed(() => contexto.value?.estacion?.id ?? estacion.value);
const estacionConsulta = computed(() => (contexto.value?.estacion ? null : estacion.value));
const nombreEstacion = computed(() => contexto.value?.estacion?.nombre ?? contexto.value?.estaciones.find((e) => e.id === estacion.value)?.nombre ?? "la estación");
const resumen = computed(() => resumenEntrega(filas.value, marcadas.value));
const todas = computed(() => filas.value.length > 0 && filas.value.every((f) => marcadas.value.includes(f.id)));
const placeholderReceptor = computed(() => (resumen.value.destinatarios.length === 1 ? `${resumen.value.destinatarios[0]!.nombre} (destinatario)` : "El destinatario de cada una"));
const reintentables = computed(() => fallidas.value.filter((x) => x.facturacion && x.recuperable).map((x) => x.id));
const contingencia = computed(() => (contexto.value?.permisos.sinFactura ? reintentables.value : []));
const importe = (centavos: number) => formatear(centavos, "Q");

/** Otras del mismo destinatario que no están marcadas (para retirarlas juntas). */
const sugerencia = computed(() => {
  if (resumen.value.destinatarios.length !== 1) return null;
  const d = resumen.value.destinatarios[0]!;
  const ids = filas.value.filter((f) => f.destinatario.id === d.id && !marcadas.value.includes(f.id)).map((f) => f.id);
  return ids.length ? { nombre: d.nombre, ids } : null;
});

const dias = (f: FilaEncomienda) => {
  const n = antiguedad(f.creada);
  return n === 0 ? "hoy" : n === 1 ? "ayer" : `hace ${n} días`;
};

onMounted(async () => {
  try {
    contexto.value = await fetchContextoEncomienda();
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e);
    return;
  }
  tipoPago.value = contexto.value.tiposPago.find((t) => /efectivo/i.test(t.nombre))?.id ?? contexto.value.tiposPago[0]?.id ?? null;
  moneda.value = contexto.value.monedas.find((m) => m.sigla === "GTQ")?.id ?? contexto.value.monedas[0]?.id ?? null;
  await cargar();
  lector.value?.$el?.focus();
});

async function cargar(silencioso = false) {
  if (!estacionId.value) {
    filas.value = [];
    return;
  }
  cargando.value = true;
  error.value = "";
  try {
    filas.value = await fetchPendientesEntrega(estacionConsulta.value, filtro.value, silencioso);
    const vivas = new Set(filas.value.map((f) => f.id));
    marcadas.value = marcadas.value.filter((id) => vivas.has(id));
  } catch (e) {
    error.value = errorVenta(e)?.error ?? (e instanceof Error ? e.message : String(e));
  } finally {
    cargando.value = false;
  }
}

/** Un código de la lista se marca (escáner); cualquier otro texto filtra la lista en el servidor. */
async function buscar() {
  const texto = busqueda.value.trim();
  const id = codigoAId(texto);
  const fila = id ? filas.value.find((f) => f.id === id) : undefined;
  if (fila) {
    busqueda.value = "";
    const ya = marcadas.value.includes(fila.id);
    marcadas.value = ya ? marcadas.value.filter((x) => x !== fila.id) : [...marcadas.value, fila.id];
    aviso.value = { ok: true, texto: `${fila.codigo} ${ya ? "desmarcada" : "marcada"} (${fila.destinatario.nombre}).` };
    return;
  }
  aviso.value = null;
  filtro.value = texto;
  await cargar();
  if (id && !filas.value.length) aviso.value = { ok: false, texto: `La ${String(id).padStart(8, "0")} no está por entregar aquí.` };
}

async function limpiarBusqueda() {
  busqueda.value = "";
  filtro.value = "";
  aviso.value = null;
  await cargar();
}

function alternar(id: number) {
  marcadas.value = marcadas.value.includes(id) ? marcadas.value.filter((x) => x !== id) : [...marcadas.value, id];
}

function alternarTodas(si: boolean) {
  marcadas.value = si ? filas.value.map((f) => f.id) : [];
}

async function entregar(ids: number[], sinFactura: boolean) {
  if (!ids.length) return;
  const cobradas = filas.value.filter((f) => ids.includes(f.id) && f.porCobrar).map((f) => f.id);
  enviando.value = true;
  try {
    const r = await entregarEncomiendas(ids, {
      receptor: receptor.value?.id ?? null,
      clienteFactura: clienteFactura.value?.id ?? null,
      tipoPago: cobradas.length ? tipoPago.value : null,
      moneda: cobradas.length ? moneda.value : null,
      sinFacturaElectronica: sinFactura,
    });
    fallidas.value = r.fallidas;
    if (r.entregadas.length) {
      aviso.value = null;
      notify.success(`${r.entregadas.length} ${r.entregadas.length === 1 ? "encomienda entregada" : "encomiendas entregadas"}`);
      marcadas.value = marcadas.value.filter((id) => !r.entregadas.includes(id));
      if (!marcadas.value.length) {
        receptor.value = null;
        clienteFactura.value = null;
      }
      // Lo cobrado lleva su comprobante (la factura), todo en un solo trabajo de impresión.
      const imprimir = r.entregadas.filter((id) => cobradas.includes(id));
      if (imprimir.length) void Promise.all(imprimir.map((id) => fetchEncomienda(id))).then(imprimirRecibos).catch(() => notify.error("No se pudieron imprimir los comprobantes."));
    }
    await cargar(true);
    await nextTick();
    lector.value?.$el?.focus();
  } catch (e) {
    notify.error(errorVenta(e)?.error ?? "No se pudo entregar.");
  } finally {
    enviando.value = false;
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
.fila {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  cursor: pointer;
}
.fila:hover {
  background: var(--p-content-hover-background);
}
.fila-on {
  border-color: var(--p-primary-color);
  background: var(--p-highlight-background);
}
.cifra {
  display: flex;
  flex-direction: column;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.cifra span {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.cifra strong {
  font-size: 1.25rem;
  font-variant-numeric: tabular-nums;
}
@container (width >= 56rem) {
  .entrega {
    position: sticky;
    top: 0.75rem;
  }
}
</style>
