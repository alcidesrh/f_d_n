<!--
  Registrar una encomienda sin boleto (ADR-035), el "Registrar" del legado:

  1. Origen (la estación del usuario), destino, ruta prevista y empresa.
  2. Remitente y destinatario (buscar o registrar).
  3. Artículos: paquetes por peso o especiales, con el precio al instante.
  4. Pagada (cobro y factura ahora), por cobrar (la paga quien la retira) o
     interna (sin cobro, con permiso). Registrar imprime el recibo (con el
     contrato que firma el remitente) y una guía por bulto.

  Ancho: formulario a la izquierda y el resumen con el cobro fijo a la
  derecha; angosto, todo en una columna.
-->
<template>
  <div class="@container">
    <Message v-if="errorCarga" severity="error" :closable="false" class="mb-3">
      {{ errorCarga }}
      <Button label="Reintentar" size="small" text @click="iniciar" />
    </Message>

    <div class="grid grid-cols-1 items-start gap-4 @4xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="flex min-w-0 flex-col gap-4">
        <!-- Viaje -->
        <section class="panel" aria-labelledby="enc-viaje">
          <h2 id="enc-viaje" class="titulo">Viaje</h2>
          <div class="grid grid-cols-1 gap-3 @xl:grid-cols-2">
            <label class="campo">
              <span>Origen</span>
              <estacion-select v-model="origen" :estaciones="contexto?.estaciones ?? []" :propio="contexto?.estacion?.departamento" :disabled="!!contexto?.estacion" :show-clear="false" placeholder="Estación de origen" input-id="enc-origen" />
            </label>
            <label class="campo">
              <span>Destino</span>
              <estacion-select v-model="destino" :estaciones="destinos" :show-clear="false" placeholder="Estación de destino" input-id="enc-destino" />
            </label>
            <label class="campo">
              <span>Ruta prevista</span>
              <Select v-model="rutaId" :options="rutas" option-label="nombre" option-value="id" :loading="cargandoRutas" :placeholder="origen && destino ? (rutas.length ? 'Elija la ruta' : 'Sin ruta directa (transbordo)') : 'Elija origen y destino'" :disabled="!rutas.length" show-clear fluid input-id="enc-ruta" />
            </label>
            <label class="campo">
              <span>Empresa que transporta</span>
              <Select v-model="empresaId" :options="empresas" option-label="nombre" option-value="id" :disabled="!!contexto?.empresa || empresas.length <= 1" placeholder="Empresa" fluid input-id="enc-empresa" />
            </label>
          </div>
        </section>

        <!-- Clientes -->
        <section class="panel" aria-labelledby="enc-clientes">
          <div class="flex items-center justify-between gap-2">
            <h2 id="enc-clientes" class="titulo">Clientes</h2>
            <Button text size="small" severity="secondary" :disabled="!remitente && !destinatario" v-tooltip.top="'Intercambiar remitente y destinatario'" aria-label="Intercambiar remitente y destinatario" @click="[remitente, destinatario] = [destinatario, remitente]">
              <template #icon><icon name="swap-vert" /></template>
            </Button>
          </div>
          <div class="grid grid-cols-1 gap-3 @xl:grid-cols-2">
            <div class="campo">
              <label for="enc-remitente">Remitente (envía)</label>
              <ClienteBuscador v-model="remitente" input-id="enc-remitente" />
            </div>
            <div class="campo">
              <label for="enc-destinatario">Destinatario (recibe)</label>
              <ClienteBuscador v-model="destinatario" input-id="enc-destinatario" />
              <small v-if="destinatario && !destinatario.telefono" class="text-muted-color">Sin teléfono: conviene registrarlo para avisarle cuando llegue.</small>
            </div>
          </div>
        </section>

        <!-- Artículos -->
        <section class="panel" aria-labelledby="enc-articulos">
          <h2 id="enc-articulos" class="titulo">Artículos</h2>
          <ArticulosEditor v-model="articulos" :especiales="contexto?.especiales ?? []" :cotizacion="cotizacion" :por-cobrar="modalidad === 'por_cobrar'" :intentado="intentado" />
          <label class="campo mt-3">
            <span>Observación</span>
            <InputText v-model="observacion" maxlength="255" placeholder="P. ej. frágil, entregar solo con DPI…" fluid />
          </label>
        </section>
      </div>

      <!-- Cobro -->
      <aside class="panel flex flex-col gap-3 @4xl:sticky @4xl:top-4" aria-labelledby="enc-cobro">
        <h2 id="enc-cobro" class="titulo">Cobro</h2>
        <SelectButton v-model="modalidad" :options="modalidades" option-label="etiqueta" option-value="valor" :allow-empty="false" fluid aria-label="Modalidad de pago" />
        <p class="m-0 text-sm text-muted-color">{{ MODALIDADES.find((m) => m.valor === modalidad)?.ayuda }}</p>

        <dl class="resumen">
          <dt>Bultos</dt>
          <dd>{{ cotizacion?.bultos ?? bultos(articulos) }}</dd>
          <template v-if="cotizacion && cotizacion.descuento.centavos > 0">
            <dt>Subtotal</dt>
            <dd>{{ cotizacion.subtotal.texto }}</dd>
            <dt>Descuento ({{ cotizacion.porcentajeDescuento }} %)</dt>
            <dd>−{{ cotizacion.descuento.texto }}</dd>
          </template>
        </dl>
        <div class="flex items-baseline justify-between rounded-lg bg-emphasis p-3">
          <span class="text-muted-color">{{ modalidad === "por_cobrar" ? "Por cobrar" : modalidad === "interna" ? "Sin cobro" : "Total" }}</span>
          <span class="text-2xl font-semibold tabular-nums" :class="{ 'line-through opacity-60': modalidad === 'interna' }">{{ cotizando ? "…" : (cotizacion?.total.texto ?? "—") }}</span>
        </div>
        <Message v-if="errorCotizacion" severity="warn" :closable="false">{{ errorCotizacion }}</Message>

        <template v-if="modalidad === 'pagada'">
          <div class="campo">
            <label for="enc-factura">Facturar a</label>
            <ClienteBuscador v-model="clienteFactura" input-id="enc-factura" :placeholder="remitente ? `${remitente.nombreCompleto} (remitente)` : 'El remitente'" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <label class="campo">
              <span>Tipo de pago</span>
              <Select v-model="tipoPago" :options="contexto?.tiposPago ?? []" option-label="nombre" option-value="id" fluid size="small" />
            </label>
            <label class="campo">
              <span>Moneda</span>
              <Select v-model="moneda" :options="contexto?.monedas ?? []" option-label="sigla" option-value="id" fluid size="small" />
            </label>
          </div>
        </template>

        <Message v-if="intentado && faltante" severity="info" :closable="false">{{ faltante }}</Message>
        <Button label="Registrar encomienda" :loading="registrando" :disabled="!contexto?.permisos.registrar" fluid @click="registrar(false)">
          <template #icon><icon name="package-2-outline" class="mr-1" /></template>
        </Button>
      </aside>
    </div>

    <FacturacionFallidaDialog :fallo="fallo" :ocupado="registrando" consecuencia="La encomienda no quedó registrada." @cancelar="fallo = null" @reintentar="registrar" />

    <Dialog :visible="!!registrada" modal header="Encomienda registrada" :style="{ width: '28rem' }" @update:visible="!$event && nueva()">
      <div v-if="registrada" class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-2">
          <span class="font-mono text-2xl font-semibold">{{ registrada.codigo }}</span>
          <span class="text-lg tabular-nums">{{ registrada.modalidad === "interna" ? "Sin cobro" : `${registrada.porCobrar ? "Por cobrar " : ""}${registrada.total.texto}` }}</span>
        </div>
        <p class="m-0 text-sm">{{ registrada.origen.nombre }} → {{ registrada.destino.nombre }} · {{ registrada.bultos }} {{ registrada.bultos === 1 ? "bulto" : "bultos" }}</p>
        <Message v-if="registrada.contingencia" severity="warn" :closable="false">Registrada sin factura electrónica (contingencia): se certificará después.</Message>
        <p class="m-0 text-sm text-muted-color">Se imprimieron el recibo y {{ registrada.bultos === 1 ? "la guía" : `las ${registrada.bultos} guías` }} para pegar en los bultos.</p>
      </div>
      <template #footer>
        <div class="flex flex-wrap justify-end gap-2">
          <Button label="Recibo" severity="secondary" outlined size="small" @click="registrada && imprimirRecibo(registrada)"><template #icon><icon name="receipt-long-outline" class="mr-1" /></template></Button>
          <Button label="Guías" severity="secondary" outlined size="small" @click="registrada && imprimirGuias(registrada)"><template #icon><icon name="label-outline" class="mr-1" /></template></Button>
          <Button label="Nueva encomienda" size="small" @click="nueva()" />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from "vue";
import { cotizarEncomienda, fetchContextoEncomienda, fetchRutasEncomienda, registrarEncomienda } from "@/core/encomienda/api";
import { bultos, errorArticulos, limpiarArticulos, MODALIDADES } from "@/core/encomienda/reglas";
import type { ArticuloPedido, ContextoEncomienda, CotizacionEncomienda, DatosEncomienda, ModalidadEncomienda, RutaOpcion } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import type { Cliente, ErrorVenta } from "@/core/venta/types";
import { notify } from "@/core/notify";
import ClienteBuscador from "@/shared/cliente/ClienteBuscador.vue";
import ArticulosEditor from "@/shared/encomienda/ArticulosEditor.vue";
import { imprimirEncomienda, imprimirGuias, imprimirRecibo } from "@/shared/encomienda/impresion";
import FacturacionFallidaDialog from "@/shared/facturacion/FacturacionFallidaDialog.vue";

type Modalidad = Exclude<ModalidadEncomienda, "con_boleto">;

const contexto = shallowRef<ContextoEncomienda | null>(null);
const errorCarga = ref("");

const origen = ref<number | null>(null);
const destino = ref<number | null>(null);
const rutas = ref<RutaOpcion[]>([]);
const rutaId = ref<number | null>(null);
const cargandoRutas = ref(false);
const empresaId = ref<number | null>(null);
const remitente = ref<Cliente | null>(null);
const destinatario = ref<Cliente | null>(null);
const articulos = ref<ArticuloPedido[]>([]);
const observacion = ref("");
const modalidad = ref<Modalidad>("pagada");
const clienteFactura = ref<Cliente | null>(null);
const tipoPago = ref<number | null>(null);
const moneda = ref<number | null>(null);

const cotizacion = ref<CotizacionEncomienda | null>(null);
const cotizando = ref(false);
const errorCotizacion = ref("");
const intentado = ref(false);
const registrando = ref(false);
const fallo = ref<ErrorVenta | null>(null);
const registrada = ref<DatosEncomienda | null>(null);
/** Clave del registro: la misma en los reintentos, así no se registra dos veces. */
let token = crypto.randomUUID();

const modalidades = computed(() => MODALIDADES.filter((m) => m.valor !== "con_boleto" && (m.valor !== "interna" || contexto.value?.permisos.interna)));
const destinos = computed(() => (contexto.value?.estaciones ?? []).filter((e) => e.id !== origen.value));
/** Las empresas que operan la ruta elegida; sin ruta, todas las del usuario. */
const empresas = computed(() => {
  const todas = contexto.value?.empresas ?? [];
  const deRuta = rutas.value.find((r) => r.id === rutaId.value)?.empresas ?? [];
  return contexto.value?.empresa || !deRuta.length ? todas : todas.filter((e) => deRuta.some((x) => x.id === e.id));
});

async function iniciar() {
  errorCarga.value = "";
  try {
    contexto.value = await fetchContextoEncomienda();
    origen.value ??= contexto.value.estacion?.id ?? null;
    empresaId.value ??= contexto.value.empresa?.id ?? (contexto.value.empresas.length === 1 ? (contexto.value.empresas[0]?.id ?? null) : null);
    const efectivo = contexto.value.tiposPago.find((t) => /efectivo/i.test(t.nombre));
    tipoPago.value ??= efectivo?.id ?? contexto.value.tiposPago[0]?.id ?? null;
    moneda.value ??= contexto.value.monedas.find((m) => m.sigla === "GTQ")?.id ?? contexto.value.monedas[0]?.id ?? null;
  } catch (e) {
    errorCarga.value = e instanceof Error ? e.message : String(e);
  }
}
onMounted(iniciar);

// Rutas del origen al destino: si hay una sola se elige; la empresa, la que la opera.
watch([origen, destino], async ([o, d]) => {
  rutas.value = [];
  rutaId.value = null;
  if (!o || !d) return;
  cargandoRutas.value = true;
  try {
    rutas.value = await fetchRutasEncomienda(o, d);
    if (rutas.value.length === 1) rutaId.value = rutas.value[0]?.id ?? null;
  } catch {
    rutas.value = [];
  } finally {
    cargandoRutas.value = false;
  }
});
watch(empresas, (lista) => {
  if (!lista.some((e) => e.id === empresaId.value)) empresaId.value = lista.length === 1 ? (lista[0]?.id ?? null) : (contexto.value?.empresa?.id ?? null);
});

// Cotización al instante (con retardo mientras se escribe el peso).
let espera: ReturnType<typeof setTimeout> | undefined;
let pedidoCotizacion = 0;
watch(
  [origen, destino, articulos, modalidad],
  () => {
    clearTimeout(espera);
    errorCotizacion.value = "";
    if (!origen.value || !destino.value || errorArticulos(articulos.value)) {
      cotizacion.value = null;
      return;
    }
    espera = setTimeout(cotizar, 350);
  },
  { deep: true },
);
onBeforeUnmount(() => clearTimeout(espera));

async function cotizar() {
  const n = ++pedidoCotizacion;
  cotizando.value = true;
  try {
    const c = await cotizarEncomienda({ origen: origen.value, destino: destino.value!, articulos: limpiarArticulos(articulos.value), modalidad: modalidad.value });
    if (n === pedidoCotizacion) cotizacion.value = c;
  } catch (e) {
    if (n !== pedidoCotizacion) return;
    cotizacion.value = null;
    errorCotizacion.value = errorVenta(e)?.error ?? "No se pudo cotizar.";
  } finally {
    if (n === pedidoCotizacion) cotizando.value = false;
  }
}

/** Lo primero que falta para registrar (o null). */
const faltante = computed(() => {
  if (!origen.value) return "Elija la estación de origen.";
  if (!destino.value) return "Elija la estación de destino.";
  if (!empresaId.value) return "Elija la empresa que transporta.";
  if (!remitente.value) return "Elija el remitente.";
  if (!destinatario.value) return "Elija el destinatario.";
  return errorArticulos(articulos.value) ?? (errorCotizacion.value || null);
});

async function registrar(sinFactura: boolean) {
  intentado.value = true;
  if (faltante.value) return;
  registrando.value = true;
  try {
    const e = await registrarEncomienda({
      token,
      empresa: empresaId.value!,
      origen: origen.value,
      destino: destino.value!,
      ruta: rutaId.value,
      remitente: remitente.value!.id,
      destinatario: destinatario.value!.id,
      modalidad: modalidad.value,
      articulos: limpiarArticulos(articulos.value),
      observacion: observacion.value.trim() || null,
      tipoPago: modalidad.value === "pagada" ? tipoPago.value : null,
      moneda: modalidad.value === "pagada" ? moneda.value : null,
      clienteFactura: modalidad.value === "pagada" ? (clienteFactura.value?.id ?? null) : null,
      sinFacturaElectronica: sinFactura,
    });
    fallo.value = null;
    registrada.value = e;
    void imprimirEncomienda(e);
  } catch (e) {
    const err = errorVenta(e);
    if (err?.codigo === "facturacion") fallo.value = err;
    else notify.error(err?.error ?? (e instanceof Error ? e.message : "No se pudo registrar la encomienda."));
  } finally {
    registrando.value = false;
  }
}

/** Formulario limpio para la siguiente (se conservan origen, empresa y forma de pago). */
function nueva() {
  registrada.value = null;
  token = crypto.randomUUID();
  destino.value = null;
  remitente.value = null;
  destinatario.value = null;
  clienteFactura.value = null;
  articulos.value = [];
  observacion.value = "";
  cotizacion.value = null;
  intentado.value = false;
}
</script>

<style scoped>
.panel {
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  background: var(--p-content-background);
}
.titulo {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  font-weight: 600;
}
.campo {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.25rem;
}
.campo > span:first-child,
.campo > label:first-child {
  font-size: 0.875rem;
  font-weight: 500;
}
.resumen {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.875rem;
}
.resumen dt {
  color: var(--p-text-muted-color);
}
.resumen dd {
  margin: 0;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
@media (width < 48rem) {
  .panel {
    padding: 0.75rem;
  }
}
</style>
