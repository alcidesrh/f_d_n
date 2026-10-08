<!--
  Venta de boletos en taquilla y agencias (ADR-021).

  1. Cliente (buscar o registrar).  2. Fecha y estación → salidas.
  3. Salida → paradas "sube en / baja en" y croquis con la ocupación en
  vivo (otros vendedores y la página web).  4. Asientos → "Facturar" (o
  "Cortesía"): cobro, certificación de la factura y ticket.

  Móvil: todo en una columna, el croquis después del viaje. Desde que el
  contenido mide 48rem (container query), croquis fijo a la derecha.
-->
<template>
  <div class="flex flex-col gap-4">
    <LegadoSwitch @cambio="cambioDeFuente" />
    <Toolbar v-if="store.contexto?.agencia || store.contexto?.estacion">
      <!-- <template #start><PageHead /></template> -->
      <template #end>
        <div class="flex flex-wrap items-center gap-2">
          <Tag v-if="store.contexto?.agencia" severity="info" :value="`${store.contexto.agencia.nombre} · saldo ${store.contexto.agencia.saldo.texto}`" />
          <Tag v-else-if="store.contexto?.estacion" severity="secondary" :value="`Taquilla ${store.contexto.estacion.nombre}`" />
        </div>
      </template>
    </Toolbar>

    <Message v-if="store.errorCarga" severity="error" :closable="false">
      {{ store.errorCarga }}
      <Button label="Reintentar" size="small" text @click="store.iniciar()" />
    </Message>

    <div class="@container">
      <div class="grid grid-cols-1 items-start gap-4 @3xl:grid-cols-[minmax(0,1fr)_20rem]">
        <!-- Columna principal -->
        <div class="flex min-w-0 flex-col gap-4">
          <section class="panel flex flex-col gap-2">
            <label class="text-sm font-medium" for="venta-cliente">Cliente (facturar a)</label>
            <ClienteBuscador v-model="store.cliente" input-id="venta-cliente" />
          </section>
          <section class="panel flex flex-col gap-3">
            <div class="grid grid-cols-1 gap-3 @xl:grid-cols-3 mb-6">
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Fecha de salida</span>
                <DatePicker v-model="store.fecha" date-format="dd/mm/yy" show-icon fluid @update:model-value="store.cargarSalidas()" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Estación</span>
                <estacion-select v-model="store.estacionId" :estaciones="estaciones" :propio="departamentoPropio" placeholder="Todas" @update:model-value="store.cargarSalidas()" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Empresa</span>
                <Select v-model="store.empresaId" :options="store.empresas" option-label="nombre" option-value="id" placeholder="Todas" show-clear fluid />
              </label>
            </div>

            <SalidasLista :salidas="store.salidasVisibles" :cargando="store.cargandoSalidas" :salida-id="store.salidaId" @elegir="store.elegirSalida" />
            <div class="flex items-center justify-between gap-2">
              <Button :label="etiquetaDia(-1)" severity="secondary" text size="small" :disabled="store.cargandoSalidas" @click="moverDia(-1)">
                <template #icon><icon name="chevron-left" class="mr-1" /></template>
              </Button>
              <Button :label="etiquetaDia(1)" severity="secondary" text size="small" icon-pos="right" :disabled="store.cargandoSalidas" @click="moverDia(1)">
                <template #icon><icon name="chevron-right" class="ml-1" /></template>
              </Button>
            </div>
          </section>
          <divider />

          <section v-if="store.detalle" class="panel flex flex-col gap-3">
            <div class="grid grid-cols-1 gap-3 @xl:grid-cols-2">
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Sube en</span>
                <Select :model-value="store.sube" :options="opcionesParada(store.subidas)" option-label="label" option-value="value" fluid @update:model-value="store.cambiarSubida" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">Baja en</span>
                <Select :model-value="store.baja" :options="opcionesParada(store.bajadas)" option-label="label" option-value="value" fluid @update:model-value="store.cambiarBajada" />
              </label>
            </div>
            <label v-if="store.puedeCobrarCompleto" class="flex items-center gap-2 text-sm">
              <Checkbox v-model="store.cobrarTrayectoCompleto" binary input-id="venta-completo" @update:model-value="store.recotizar()" />
              <span> Cobrar la tarifa del trayecto completo ({{ store.detalle.trayecto.origen.nombre }} → {{ store.detalle.trayecto.destino.nombre }}) </span>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-sm font-medium">Observación</span>
              <InputText v-model="store.observacion" maxlength="255" placeholder="P. ej. viaja con mascota, se baja en un punto intermedio…" fluid />
            </label>
          </section>

          <section v-if="store.seleccion.length" class="panel flex flex-col gap-2">
            <div class="text-sm font-medium">Pasajeros</div>
            <ul class="flex flex-col gap-2">
              <li v-for="linea in lineas" :key="linea.asiento" class="grid grid-cols-[3rem_minmax(0,1fr)] items-center gap-2 @xl:grid-cols-[3rem_minmax(0,1fr)_6rem]">
                <span class="font-mono text-lg font-semibold">{{ linea.numero }}</span>
                <ClienteBuscador compacto :model-value="store.pasajeros[linea.asiento] ?? null" :placeholder="`Viaja: ${store.cliente?.nombreCompleto ?? 'el cliente'}`" @update:model-value="store.pasajeros[linea.asiento] = $event" />
                <span class="col-start-2 text-right tabular-nums @xl:col-start-auto">{{ linea.precio }}</span>
              </li>
            </ul>
          </section>
        </div>

        <!-- Croquis + cobro -->
        <div class="flex">
          <divider layout="vertical" />
          <aside class="panel flex flex-col gap-3 @3xl:sticky @3xl:top-4">
            <Transition name="croquis" mode="out-in">
              <div v-if="store.cargandoDetalle" key="cargando">
                <Skeleton height="20rem" />
              </div>
              <div v-else-if="store.detalle" :key="`salida-${store.salidaId}`" class="flex flex-col gap-3">
                <!-- <div class="flex items-baseline justify-between gap-2">
                <div class="font-medium">{{ store.detalle.bus?.codigo ?? "Sin bus" }}</div>
                <div class="text-sm text-muted-color">Libres {{ store.libres }}/{{ store.asientosCroquis.length }}</div>
              </div> -->

                <BusMapLegend :items="leyenda" :conteos="conteos" />
                <div class="overflow-x-auto mt-4">
                  <BusMap :elementos="store.detalle.croquis" :estado="store.estadoAsiento" interactivo inspeccionable tamano="md" class="justify-center" @asiento="(a) => a.id != null && store.alternarAsiento(a.id)" @ocupado="verOcupado" />
                  <AsientoOcupadoPopover ref="detalleAsiento" />
                </div>
                <Divider align="center" class="my-0! before:border-surface-400!" type="dashed">
                  <span class="text-xs font-bold text-surface-500">{{ store.ocupados.length }} / {{ store.asientosCroquis.length }}</span>
                </Divider>
                <div class="flex items-baseline justify-between">
                  <span class="text-muted-color">Total</span>
                  <span class="text-xl font-semibold tabular-nums">{{ store.cotizacion?.total.texto ?? "—" }}</span>
                </div>
                <Message v-if="store.errorCotizacion" severity="warn" :closable="false">
                  {{ store.errorCotizacion }}
                </Message>
                <Message v-if="!store.cliente && store.seleccion.length" severity="info" :closable="false"> Elija el cliente para facturar. </Message>
                <div class="flex flex-wrap justify-end gap-2">
                  <Button v-if="store.contexto?.permisos.cortesia" label="Cortesía" severity="secondary" outlined :disabled="!store.puedeVender" @click="abrirCobro(true)" />
                  <Button :label="store.contexto?.canal === 'agencia' ? 'Vender' : 'Facturar'" :disabled="!store.puedeVender || !store.cotizacion" @click="abrirCobro(false)" />
                </div>
              </div>
              <div v-else key="vacio" class="py-10 text-center text-muted-color">
                <icon name="directions-bus-outline" class="mb-2 text-3xl" />
                <p>Elija un salida para ver sus asientos.</p>
              </div>
            </Transition>
          </aside>
        </div>
      </div>
    </div>

    <CobroDialog v-model:visible="cobro" :cortesia="cortesia" @confirmar="confirmar" />
    <FacturacionFallidaDialog :fallo="store.falloFacturacion" :ocupado="store.vendiendo" @cancelar="store.cancelarVenta()" @reintentar="reintentar" />
    <VentaExitosaDialog :comprobante="store.comprobante" @cerrar="store.comprobante = null" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import BusMap from "@/shared/bus-map/BusMap.vue";
import type { AsientoCroquis } from "@/core/croquis/types";
import AsientoOcupadoPopover from "@/shared/bus-map/AsientoOcupadoPopover.vue";
import BusMapLegend, { type ItemLeyenda } from "@/shared/bus-map/BusMapLegend.vue";
import { conteosPorEstado, hora } from "@/core/venta/modelo";
import type { Parada } from "@/core/venta/types";
import ClienteBuscador from "./ClienteBuscador.vue";
import CobroDialog from "./CobroDialog.vue";
import FacturacionFallidaDialog from "./FacturacionFallidaDialog.vue";
import SalidasLista from "./SalidasLista.vue";
import VentaExitosaDialog from "./VentaExitosaDialog.vue";
import { type OpcionesCobro, useVentaStore } from "./store";
import LegadoSwitch from "@/temporal/legado/LegadoSwitch.vue"; // TEMPORAL-LEGADO
import { useVentaLegado } from "@/temporal/legado/useVentaLegado"; // TEMPORAL-LEGADO
import { imprimirTicket } from "./ticket";

const store = useVentaStore();
const { estaciones, departamentoPropio, cambioDeFuente } = useVentaLegado(store); // TEMPORAL-LEGADO
const detalleAsiento = ref<InstanceType<typeof AsientoOcupadoPopover> | null>(null);
const verOcupado = (a: AsientoCroquis, e: MouseEvent) => {
  if (store.salidaId && a.id != null) void detalleAsiento.value?.abrir(e, store.salidaId, a.id);
};
const cobro = ref(false);
const cortesia = ref(false);
let ultimasOpciones: OpcionesCobro | null = null;

const conteos = computed(() => conteosPorEstado(store.detalle?.croquis ?? [], store.estadoAsiento));

/** Paginado por día: mueve la fecha y recarga las salidas. */
function moverDia(delta: number) {
  const d = new Date(store.fecha);
  d.setDate(d.getDate() + delta);
  store.fecha = d;
  void store.cargarSalidas();
}
/** "Ayer"/"Mañana" respecto de hoy; si ya se navegó, el día de la semana y la fecha. */
function etiquetaDia(delta: number) {
  const d = new Date(store.fecha);
  d.setDate(d.getDate() + delta);
  const dif = Math.round((new Date(d.toDateString()).getTime() - new Date(new Date().toDateString()).getTime()) / 86_400_000);
  if (dif === -1) return "Ayer";
  if (dif === 0) return "Hoy";
  if (dif === 1) return "Mañana";
  return new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "numeric", month: "short" }).format(d);
}
const leyenda: ItemLeyenda[] = ["disponible", "seleccionado", "ocupado", "ocupado-web", "ocupado-agencia", "reservado", "cortesia", "voucher"];

const opcionesParada = (paradas: Parada[]) =>
  paradas.map((p) => ({
    value: p.id,
    label: p.hora ? `${hora(p.hora)} · ${p.nombre}` : (p.nombre ?? ""),
  }));

const lineas = computed(() => {
  const precios = new Map(store.cotizacion?.asientos.map((a) => [a.asiento, a.precio.texto]));
  const numeros = new Map(store.asientosCroquis.map((a) => [a.id, a.tipo === "asiento" ? a.numero : 0]));
  return store.seleccion
    .map((asiento) => ({
      asiento,
      numero: numeros.get(asiento) ?? 0,
      precio: precios.get(asiento) ?? "—",
    }))
    .sort((a, b) => a.numero - b.numero);
});

onMounted(() => {
  if (!store.contexto) void store.iniciar();
});

function abrirCobro(esCortesia: boolean) {
  cortesia.value = esCortesia;
  cobro.value = true;
}

async function confirmar(opciones: OpcionesCobro) {
  ultimasOpciones = opciones;
  const c = await store.vender(opciones);
  cobro.value = false;
  if (c) imprimirTicket(c);
}

async function reintentar(sinFactura: boolean) {
  if (!ultimasOpciones) return;
  const c = await store.vender({ ...ultimasOpciones, sinFacturaElectronica: sinFactura });
  if (c) imprimirTicket(c);
}
</script>

<style scoped>
.croquis-enter-active,
.croquis-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}
.croquis-enter-from,
.croquis-leave-to {
  opacity: 0;
  transform: translateY(0.5rem);
}
@media (prefers-reduced-motion: reduce) {
  .croquis-enter-active,
  .croquis-leave-active {
    transition: none;
  }
}
.panel {
  /*background: var(--p-content-background);*/
  /*border: 1px solid var(--p-content-border-color);*/
  /*border-radius: var(--p-content-border-radius, 0.75rem);*/
  padding: 1rem;
}
</style>
