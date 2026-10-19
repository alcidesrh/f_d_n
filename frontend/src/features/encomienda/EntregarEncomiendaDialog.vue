<!--
  Entregar una encomienda desembarcada en su destino: quién la retira (por
  defecto el destinatario) y, si es por cobrar, el cobro con su factura (a
  quien paga; por defecto quien la retira). Si el certificador falla no se
  entrega: reintentar o, con permiso, contingencia. Al terminar imprime el
  recibo con la factura.
-->
<template>
  <Dialog :visible="visible" modal header="Entregar encomienda" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="abrir">
    <div v-if="encomienda" class="flex flex-col gap-3">
      <dl class="datos">
        <dt>Encomienda</dt>
        <dd class="font-mono">{{ encomienda.codigo }} · {{ encomienda.bultos }} {{ encomienda.bultos === 1 ? "bulto" : "bultos" }}</dd>
        <dt>Contenido</dt>
        <dd>{{ encomienda.contenido }}</dd>
        <dt>Remitente</dt>
        <dd>{{ encomienda.remitente.nombre }}</dd>
        <dt>Destinatario</dt>
        <dd>{{ encomienda.destinatario.nombre }}<span v-if="encomienda.destinatario.documento" class="text-muted-color"> · doc. {{ encomienda.destinatario.documento }}</span></dd>
      </dl>
      <div class="flex flex-col gap-1">
        <label for="entrega-receptor" class="text-sm font-medium">La retira</label>
        <ClienteBuscador v-model="receptor" input-id="entrega-receptor" :placeholder="`${encomienda.destinatario.nombre} (destinatario)`" />
      </div>

      <template v-if="encomienda.porCobrar">
        <div class="flex items-baseline justify-between rounded-lg bg-emphasis p-3">
          <span class="text-muted-color">Por cobrar</span>
          <span class="text-2xl font-semibold tabular-nums">{{ encomienda.total.texto }}</span>
        </div>
        <div class="flex flex-col gap-1">
          <label for="entrega-factura" class="text-sm font-medium">Facturar a</label>
          <ClienteBuscador v-model="clienteFactura" input-id="entrega-factura" placeholder="Quien la retira" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <label class="flex flex-col gap-1">
            <span class="text-sm font-medium">Tipo de pago</span>
            <Select v-model="tipoPago" :options="tiposPago" option-label="nombre" option-value="id" size="small" fluid />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-sm font-medium">Moneda</span>
            <Select v-model="moneda" :options="monedas" option-label="sigla" option-value="id" size="small" fluid />
          </label>
        </div>
      </template>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('update:visible', false)" />
      <Button :label="encomienda?.porCobrar ? 'Cobrar y entregar' : 'Entregar'" :loading="enviando" @click="entregar(false)" />
    </template>
  </Dialog>
  <FacturacionFallidaDialog :fallo="fallo" :ocupado="enviando" consecuencia="La encomienda no se entregó ni se cobró." @cancelar="fallo = null" @reintentar="entregar" />
</template>

<script setup lang="ts">
import { ref } from "vue";
import { entregarEncomienda } from "@/core/encomienda/api";
import type { FilaEncomienda } from "@/core/encomienda/types";
import { errorVenta } from "@/core/venta/api";
import type { Cliente, ErrorVenta, Opcion } from "@/core/venta/types";
import { notify } from "@/core/notify";
import ClienteBuscador from "@/shared/cliente/ClienteBuscador.vue";
import { imprimirRecibo } from "@/shared/encomienda/impresion";
import FacturacionFallidaDialog from "@/shared/facturacion/FacturacionFallidaDialog.vue";

const props = defineProps<{
  visible: boolean;
  encomienda: FilaEncomienda | null;
  tiposPago: Opcion[];
  monedas: Array<{ id: number; sigla: string; nombre: string }>;
}>();
const emit = defineEmits<{ "update:visible": [visible: boolean]; listo: [] }>();

const receptor = ref<Cliente | null>(null);
const clienteFactura = ref<Cliente | null>(null);
const tipoPago = ref<number | null>(null);
const moneda = ref<number | null>(null);
const enviando = ref(false);
const fallo = ref<ErrorVenta | null>(null);

function abrir() {
  receptor.value = null;
  clienteFactura.value = null;
  tipoPago.value = props.tiposPago.find((t) => /efectivo/i.test(t.nombre))?.id ?? props.tiposPago[0]?.id ?? null;
  moneda.value = props.monedas.find((m) => m.sigla === "GTQ")?.id ?? props.monedas[0]?.id ?? null;
}

async function entregar(sinFactura: boolean) {
  const e = props.encomienda;
  if (!e) return;
  enviando.value = true;
  try {
    const datos = await entregarEncomienda(e.id, {
      receptor: receptor.value?.id ?? null,
      clienteFactura: clienteFactura.value?.id ?? null,
      tipoPago: e.porCobrar ? tipoPago.value : null,
      moneda: e.porCobrar ? moneda.value : null,
      sinFacturaElectronica: sinFactura,
    });
    fallo.value = null;
    notify.success(`Encomienda ${e.codigo} entregada`);
    // El cobro lleva su comprobante (la factura).
    if (e.porCobrar) void imprimirRecibo(datos, false);
    emit("listo");
    emit("update:visible", false);
  } catch (x) {
    const err = errorVenta(x);
    if (err?.codigo === "facturacion") fallo.value = err;
    else notify.error(err?.error ?? "No se pudo entregar.");
  } finally {
    enviando.value = false;
  }
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
</style>
