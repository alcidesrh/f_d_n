<!--
  Todo de una encomienda: viaje, dónde está, clientes, artículos con su
  precio, cobro y factura; reimprimir recibo y guías, y su bitácora.
-->
<template>
  <Dialog :visible="visible" modal :header="datos ? `Encomienda ${datos.codigo}` : 'Encomienda'" class="w-[min(44rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <Skeleton v-if="cargando" height="16rem" />
    <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
    <div v-else-if="datos" class="@container flex flex-col gap-4">
      <div class="flex flex-wrap items-center gap-2">
        <Tag :value="datos.estadoEtiqueta" :severity="severidad(datos.estado)" />
        <Tag :value="datos.porCobrar ? 'Por cobrar' : datos.modalidadEtiqueta" severity="secondary" />
        <span v-if="datos.venta" class="text-sm text-muted-color">Con la venta {{ datos.venta }}<template v-if="datos.boleto"> · asiento {{ datos.boleto.asiento }}</template></span>
        <span class="ml-auto text-xl font-semibold tabular-nums">{{ datos.modalidad === "interna" ? "Sin cobro" : datos.total.texto }}</span>
      </div>

      <dl class="datos @xl:grid-cols-[auto_minmax(0,1fr)_auto_minmax(0,1fr)]">
        <dt>Origen</dt>
        <dd>{{ datos.origen.nombre }}</dd>
        <dt>Destino</dt>
        <dd>{{ datos.destino.nombre }}</dd>
        <dt>Ruta prevista</dt>
        <dd>{{ datos.ruta?.nombre ?? "—" }}</dd>
        <dt>Empresa</dt>
        <dd>{{ datos.empresa?.nombre }}</dd>
        <dt>Dónde está</dt>
        <dd>{{ dondeEsta }}</dd>
        <dt>Registrada</dt>
        <dd>{{ fechaHora(datos.creada) }} · {{ datos.usuario ?? "—" }}</dd>
        <dt>Remitente</dt>
        <dd>{{ datos.remitente.nombre }} <span class="text-muted-color">· {{ datos.remitente.nit }}{{ datos.remitente.telefono ? ` · ${datos.remitente.telefono}` : "" }}</span></dd>
        <dt>Destinatario</dt>
        <dd>{{ datos.destinatario.nombre }} <span class="text-muted-color">· {{ datos.destinatario.nit }}{{ datos.destinatario.telefono ? ` · ${datos.destinatario.telefono}` : "" }}</span></dd>
        <template v-if="datos.receptor">
          <dt>La retiró</dt>
          <dd>{{ datos.receptor.nombre }} · {{ fechaHora(datos.entregadaEn) }}</dd>
        </template>
        <template v-if="datos.observacion">
          <dt>Observación</dt>
          <dd>{{ datos.observacion }}</dd>
        </template>
        <template v-if="datos.motivoAnulacion">
          <dt>Motivo anulación</dt>
          <dd>{{ datos.motivoAnulacion }}</dd>
        </template>
      </dl>

      <table class="articulos">
        <thead>
          <tr><th>Artículo</th><th class="r">Bultos</th><th class="r">Por bulto</th><th class="r">Importe</th></tr>
        </thead>
        <tbody>
          <tr v-for="a in datos.articulos" :key="a.id">
            <td>{{ a.resumen }}</td>
            <td class="r">{{ a.cantidad }}</td>
            <td class="r">{{ a.recargo.centavos ? `${a.tarifa.texto} + ${a.recargo.texto}` : a.tarifa.texto }}</td>
            <td class="r">{{ a.importe.texto }}</td>
          </tr>
        </tbody>
        <tfoot v-if="datos.descuento.centavos > 0">
          <tr><td colspan="3" class="r">Descuento por {{ datos.bultos }} bultos</td><td class="r">−{{ datos.descuento.texto }}</td></tr>
        </tfoot>
      </table>

      <div v-if="datos.factura || datos.contingencia || datos.tipoPago" class="text-sm">
        <template v-if="datos.factura">
          Factura {{ datos.factura.serie }}-{{ datos.factura.numero }} a {{ datos.factura.receptorNombre }} ({{ datos.factura.receptorNit }})<Tag v-if="datos.factura.anulada" value="Anulada" severity="danger" class="ml-2" />
        </template>
        <template v-else-if="datos.contingencia">En contingencia: factura pendiente de certificar (acceso {{ datos.numeroAcceso }}).</template>
        <span v-if="datos.tipoPago" class="text-muted-color"> · {{ datos.tipoPago }}</span>
      </div>
    </div>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <Button label="Bitácora" severity="secondary" text size="small" :disabled="!datos" @click="emit('bitacora')"><template #icon><icon name="history" class="mr-1" /></template></Button>
        <Button label="Guías" severity="secondary" outlined size="small" :disabled="!datos || datos.estado === 'anulada'" @click="datos && imprimirGuias(datos)"><template #icon><icon name="label-outline" class="mr-1" /></template></Button>
        <Button label="Recibo" severity="secondary" outlined size="small" :disabled="!datos" @click="datos && imprimirRecibo(datos)"><template #icon><icon name="receipt-long-outline" class="mr-1" /></template></Button>
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { fetchEncomienda } from "@/core/encomienda/api";
import type { DatosEncomienda, EstadoEncomienda } from "@/core/encomienda/types";
import { HttpError } from "@/core/http";
import { fecha, hora } from "@/core/venta/modelo";
import { imprimirGuias, imprimirRecibo } from "@/shared/encomienda/impresion";

const props = defineProps<{ visible: boolean; id: number | null }>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; bitacora: [] }>();

const datos = ref<DatosEncomienda | null>(null);
const cargando = ref(false);
const error = ref("");

async function cargar() {
  if (!props.id) return;
  cargando.value = true;
  error.value = "";
  try {
    datos.value = await fetchEncomienda(props.id);
  } catch (e) {
    datos.value = null;
    error.value = e instanceof HttpError && e.status === 404 ? "La encomienda no existe." : e instanceof Error ? e.message : String(e);
  } finally {
    cargando.value = false;
  }
}

const fechaHora = (iso: string | null) => (iso ? `${fecha(iso)} ${hora(iso)}` : "—");
const dondeEsta = computed(() => {
  const d = datos.value;
  if (!d) return "";
  if (d.estado === "entregada") return "Entregada";
  if (d.estado === "anulada") return "Anulada";
  if (d.ubicacion) return `En bodega en ${d.ubicacion.nombre}`;
  if (d.salida) return `En el bus${d.salida.bus ? ` ${d.salida.bus}` : ""} · salida ${d.salida.id} (${fechaHora(d.salida.fecha)})`;
  return "—";
});
function severidad(e: EstadoEncomienda) {
  return ({ recibida: "info", embarcada: "info", transito: "warn", desembarcada: "contrast", entregada: "success", anulada: "danger" } as const)[e];
}
</script>

<style scoped>
.datos {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.875rem;
}
.datos dt {
  color: var(--p-text-muted-color);
}
.datos dd {
  margin: 0;
  min-width: 0;
  overflow-wrap: anywhere;
}
.articulos {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.articulos th,
.articulos td {
  padding: 0.375rem 0.25rem;
  border-bottom: 1px solid var(--p-content-border-color);
  text-align: left;
  vertical-align: top;
}
.articulos th {
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.articulos .r {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
