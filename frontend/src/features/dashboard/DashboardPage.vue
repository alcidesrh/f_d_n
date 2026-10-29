<!--
  Tablero de gestión (portada): alertas que piden acción, indicadores contra
  el periodo anterior, tendencia y mezcla de canales, operación de hoy,
  venta anticipada, rendimiento por ruta, mapa de demanda, puntos de venta,
  cobertura de agencias y flota. Datos de `GET /api/tablero` (permiso
  `reporte.ventas`; un usuario con empresa solo ve la suya). Se actualiza
  solo cada 5 minutos mientras la pestaña está visible. Sin el permiso,
  muestra accesos directos.
-->
<template>
  <div class="tablero @container flex flex-col gap-4">
    <header class="flex flex-wrap items-end gap-3">
      <div class="min-w-0 flex-1">
        <!-- <p v-if="datos" class="text-sm md:text- lg lg:text-xl font-medium">{{ rango(datos.periodo.desde, datos.periodo.hasta) }} · comparado con {{ datos.periodo.rotuloAnterior }} a la misma hora · actualizado {{ hora(datos.generado.slice(0, 16)) }}</p> -->
      </div>
      <template v-if="!sinPermiso">
        <Select v-if="datos && !datos.alcance.fija && datos.empresas.length > 1" v-model="empresa" :options="datos.empresas" option-label="nombre" option-value="id" show-clear placeholder="Todas las empresas" size="small" class="w-full @md:w-52" aria-label="Empresa" />
        <SelectButton v-model="dias" :options="PERIODOS" option-label="etiqueta" option-value="dias" :allow-empty="false" size="small" aria-label="Periodo" />
        <Button severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" v-tooltip.left="'Actualizar'" @click="cargar()">
          <template #icon><icon name="refresh" /></template>
        </Button>
      </template>
    </header>

    <div v-if="sinPermiso" class="flex flex-col gap-3">
      <Message severity="secondary" :closable="false">El tablero de gestión necesita el permiso de reportes de venta de toda una empresa. Estos son sus accesos más usados:</Message>
      <div class="accesos">
        <RouterLink v-for="a in accesos" :key="a.ruta" :to="a.ruta" class="acceso">
          <icon :name="a.icono" lg class="text-primary" />
          <span class="font-medium">{{ a.nombre }}</span>
        </RouterLink>
      </div>
    </div>

    <template v-else>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <template v-if="!datos && cargando">
        <Skeleton height="4rem" />
        <div class="kpis"><Skeleton v-for="i in 8" :key="i" height="6rem" /></div>
        <div class="fila-2-1"><Skeleton height="18rem" /><Skeleton height="18rem" /></div>
      </template>

      <template v-if="datos">
        <!-- <div class="section-header text-sm mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px] lg:mb-[1rem]">Alerta</div>
        <AlertasTablero :alertas="datos.alertas" /> -->

        <!-- <div class="section-header text-sm mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px] lg:mb-[1rem">Venta</div> -->
        <div class="kpis mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px]">
          <KpiTile etiqueta="Ingresos" icono="payments-outline" :valor="dinero(k.ingresos.actual, true)" :variacion="variacion(k.ingresos.actual, k.ingresos.anterior)" :anterior="dinero(k.ingresos.anterior, true)" ayuda="Boletos vendidos y cobrados en el periodo (por fecha de venta), sin anulados ni cortesías." />
          <KpiTile etiqueta="Boletos vendidos" icono="confirmation-number-outline" :valor="numero(k.boletos.actual)" :variacion="variacion(k.boletos.actual, k.boletos.anterior)" :anterior="numero(k.boletos.anterior)" />
          <KpiTile etiqueta="Ticket promedio" icono="sell-outline" :valor="dinero(k.ticketPromedio.actual)" :variacion="variacion(k.ticketPromedio.actual, k.ticketPromedio.anterior)" :anterior="dinero(k.ticketPromedio.anterior)" />
          <KpiTile etiqueta="Ocupación" icono="airline-seat-recline-normal-outline" :valor="porciento(k.ocupacion.actual, 1)" :variacion="variacion(k.ocupacion.actual, k.ocupacion.anterior, { puntos: true })" :anterior="porciento(k.ocupacion.anterior, 1)" ayuda="Asiento-tramos vendidos sobre los ofrecidos, de las salidas que ya salieron en el periodo (un asiento revendido por tramos cuenta lo que ocupa)." />
          <KpiTile etiqueta="Ingreso por salida" icono="directions-bus-outline" :valor="dinero(k.ingresoPorSalida.actual, true)" :variacion="variacion(k.ingresoPorSalida.actual, k.ingresoPorSalida.anterior)" :detalle="`${numero(k.salidas.actual)} salidas${k.salidasCanceladas.actual ? ` · ${k.salidasCanceladas.actual} anuladas` : ''}`" ayuda="Lo vendido para las salidas que ya salieron, dividido entre esas salidas. Mide si cada viaje paga su costo." />
          <KpiTile etiqueta="Anulación" icono="cancel-outline" :valor="porciento(k.tasaAnulacion.actual, 1)" :variacion="variacion(k.tasaAnulacion.actual, k.tasaAnulacion.anterior, { puntos: true, inverso: true })" :detalle="`${numero(k.anulados.actual)} boletos · ${dinero(k.montoAnulado.actual, true)}`" ayuda="Boletos anulados sobre los vendidos en el periodo. Una tasa alta suele ser errores de venta." />
          <KpiTile etiqueta="No se presentaron" icono="person-off-outline" :valor="porciento(k.tasaNoPresentados.actual, 1)" :variacion="variacion(k.tasaNoPresentados.actual, k.tasaNoPresentados.anterior, { puntos: true, inverso: true })" ayuda="Boletos cancelados al abordar (el pasajero no llegó) sobre los de las salidas que ya salieron. Liberan el asiento pero conservan el cobro." />
          <KpiTile etiqueta="Sin cobro" icono="redeem-outline" :valor="dinero(k.valorCortesias.actual, true)" :variacion="variacion(k.valorCortesias.actual, k.valorCortesias.anterior, { inverso: true })" :detalle="`${numero(k.cortesias.actual)} cortesías y vouchers`" ayuda="Valor de los boletos emitidos sin cobro (vouchers a precio de tarifa; las cortesías nuevas van a Q 0). Es ingreso que no entra." />
        </div>

        <!-- <div class="section-header text-sm mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px] lg:mb-[1rem">Cananles de Ventas por estación, agencia y página.</div> -->
        <div class="fila-2-1 mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px]">
          <TableroPanel titulo="Ingresos diario por canales de venta" icono="show-chart">
            <TendenciaVentas :serie="datos.serie" :por-hora="datos.periodo.porHora" :rotulo-anterior="datos.periodo.rotuloAnterior" />
          </TableroPanel>

          <TableroPanel titulo="Ingresos totales por canales de venta" icono="storefront-outline" subtitulo="Participación en los ingresos">
            <CanalesVenta :canales="datos.canales" />
          </TableroPanel>
        </div>

        <div class="fila-2-1 mt-10">
          <TableroPanel titulo="Demanda por día y hora" icono="grid-on-outline" subtitulo="Ocupación media según la hora de salida" ayuda="Mira al menos las últimas 4 semanas. Mover salidas de las franjas claras a las oscuras sube la ocupación sin sumar buses.">
            <MapaDemanda :demanda="datos.demanda" />
          </TableroPanel>

          <TableroPanel titulo="Mejores puntos de venta" icono="leaderboard-outline" subtitulo="Por ingresos del periodo">
            <PuntosVenta :puntos="datos.puntos" />
          </TableroPanel>
        </div>

        <div class="fila-1-1 mt-10">
          <TableroPanel titulo="Operación de hoy" icono="today-outline" :subtitulo="`${datos.operacionHoy.total} salidas programadas para hoy`">
            <OperacionHoy :operacion="datos.operacionHoy" />
          </TableroPanel>

          <TableroPanel titulo="Venta anticipada" icono="event-upcoming-outline" subtitulo="Hoy y los próximos 7 días" ayuda="Ocupación ya vendida de las salidas de cada día contra la ocupación final del mismo día de la semana anterior.">
            <VentaAnticipada :dias="datos.anticipada" />
          </TableroPanel>
        </div>

        <TableroPanel titulo="Rendimiento por ruta" icono="route-outline" subtitulo="Salidas que ya salieron en el periodo" :ayuda="`Saturada: ocupación de ${porciento(OCUPACION_SATURADA)} o más (sumar frecuencia o un bus más grande). Baja: menos de ${porciento(OCUPACION_BAJA)} (sobran salidas o hay que promoverla). Se juzga con 3 salidas o más.`">
          <RendimientoRutas :rutas="datos.rutas" />
        </TableroPanel>

        <div class="section-header text-sm mt-[0.5rem] mb-[0.3rem] md:text-[1.5rem] lg:mt-[1.5rem] pb-[5px] lg:mb-[1rem">Agencia</div>
        <div class="fila-1-1">
          <TableroPanel titulo="Saldo de agencias" icono="account-balance-wallet-outline" subtitulo="Días de venta que cubre el saldo" ayuda="Saldo disponible dividido entre lo que la agencia vendió por día la última semana.">
            <CoberturaAgencias :agencias="datos.agencias" />
          </TableroPanel>

          <TableroPanel titulo="Flota" icono="airport-shuttle-outline" subtitulo="Buses con salidas del último mes o de la próxima semana">
            <EstadoFlota :flota="datos.flota" />
          </TableroPanel>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from "vue";
import { claveDeUsuario } from "@/core/auth/alcanceSesion";
import { HttpError } from "@/core/http";
import { fetchTablero } from "@/core/tablero/api";
import { OCUPACION_BAJA, OCUPACION_SATURADA, dinero, hora, numero, porciento, rango, variacion } from "@/core/tablero/formato";
import type { DiasTablero, Tablero } from "@/core/tablero/types";
import AlertasTablero from "./componentes/AlertasTablero.vue";
import CanalesVenta from "./componentes/CanalesVenta.vue";
import CoberturaAgencias from "./componentes/CoberturaAgencias.vue";
import EstadoFlota from "./componentes/EstadoFlota.vue";
import { useAccesoStore } from "@/core/acceso/store";
import KpiTile from "./componentes/KpiTile.vue";
import MapaDemanda from "./componentes/MapaDemanda.vue";
import OperacionHoy from "./componentes/OperacionHoy.vue";
import PuntosVenta from "./componentes/PuntosVenta.vue";
import RendimientoRutas from "./componentes/RendimientoRutas.vue";
import TableroPanel from "./componentes/TableroPanel.vue";
import TendenciaVentas from "./componentes/TendenciaVentas.vue";
import VentaAnticipada from "./componentes/VentaAnticipada.vue";

const propietario = localStorage.getItem("sesion:propietario");

const PERIODOS: { dias: DiasTablero; etiqueta: string }[] = [
  { dias: 1, etiqueta: "Hoy" },
  { dias: 7, etiqueta: "7 días" },
  { dias: 30, etiqueta: "30 días" },
  { dias: 90, etiqueta: "90 días" },
];
const ACCESOS = [
  { ruta: "/venta", nombre: "Vender boletos", icono: "point-of-sale" },
  { ruta: "/boletos", nombre: "Boletos", icono: "confirmation-number-outline" },
  { ruta: "/salidas", nombre: "Salidas", icono: "directions-bus-outline" },
  { ruta: "/salidas/chequeo", nombre: "Chequeo", icono: "fact-check-outline" },
  { ruta: "/seguimiento", nombre: "Seguimiento", icono: "map-outline" },
  { ruta: "/chat", nombre: "Chat", icono: "chat-outline" },
];
/** Solo las pantallas que el usuario puede abrir (ADR-039). */
const acceso = useAccesoStore();
const router = useRouter();
const accesos = computed(() =>
  ACCESOS.filter((a) => {
    const r = router.resolve(a.ruta);
    return acceso.puedeEntrar({ name: r.name, params: r.params, meta: r.meta });
  }),
);
const ACTUALIZAR_MS = 5 * 60_000;
const PREFERENCIAS = "tablero.preferencias";

/** Periodo y empresa elegidos, recordados en este navegador (si el almacenamiento falla, se arranca con 7 días). */
function leerPreferencias(): { dias: DiasTablero; empresa: number | null } {
  try {
    const p = JSON.parse(localStorage.getItem(claveDeUsuario(PREFERENCIAS)) ?? "{}") as { dias?: number; empresa?: number | null };
    return { dias: PERIODOS.some((x) => x.dias === p.dias) ? (p.dias as DiasTablero) : 7, empresa: typeof p.empresa === "number" ? p.empresa : null };
  } catch {
    return { dias: 7, empresa: null };
  }
}

const inicial = leerPreferencias();
const dias = ref<DiasTablero>(inicial.dias);
const empresa = ref<number | null>(inicial.empresa);
const datos = shallowRef<Tablero | null>(null);
const cargando = ref(false);
const error = ref("");
const sinPermiso = ref(false);
const ahora = ref(new Date());
const k = computed(() => datos.value!.kpis);

let pedido = 0;
async function cargar(silencioso = false) {
  const n = ++pedido;
  cargando.value = true;
  if (!silencioso) error.value = "";
  try {
    const r = await fetchTablero(dias.value, empresa.value, silencioso);
    if (n !== pedido) return;
    datos.value = r;
    ahora.value = new Date();
    error.value = "";
  } catch (e) {
    if (n !== pedido) return;
    if (e instanceof HttpError && e.status === 403) sinPermiso.value = true;
    else if (!silencioso || !datos.value) error.value = e instanceof Error && e.message ? `No se pudo cargar el tablero: ${e.message}` : "No se pudo cargar el tablero.";
  } finally {
    if (n === pedido) cargando.value = false;
  }
}

watch([dias, empresa], () => {
  try {
    localStorage.setItem(claveDeUsuario(PREFERENCIAS), JSON.stringify({ dias: dias.value, empresa: empresa.value }));
  } catch {
    /* sin almacenamiento: solo no se recuerda */
  }
  void cargar();
});

let temporizador: ReturnType<typeof setInterval> | undefined;
let reloj: ReturnType<typeof setInterval> | undefined;
function alVolver() {
  if (document.visibilityState === "visible" && datos.value && Date.now() - new Date(datos.value.generado).getTime() > ACTUALIZAR_MS) void cargar(true);
}
onMounted(() => {
  void cargar();
  temporizador = setInterval(() => document.visibilityState === "visible" && !sinPermiso.value && void cargar(true), ACTUALIZAR_MS);
  reloj = setInterval(() => (ahora.value = new Date()), 30_000);
  document.addEventListener("visibilitychange", alVolver);
});
onBeforeUnmount(() => {
  clearInterval(temporizador);
  clearInterval(reloj);
  document.removeEventListener("visibilitychange", alVolver);
});
</script>

<style scoped>
/*
 * Colores de las gráficas (paleta validada para daltonismo, claro y oscuro):
 * los tres canales en orden fijo; una escala de un tono para la ocupación;
 * los estados reservados para alertas (siempre con ícono y texto).
 */

.tablero {
  --serie-estacion: var(--p-emerald-600);
  --serie-agencia: var(--p-blue-600);
  --serie-web: var(--p-amber-500);
  --linea-referencia: #8a8985;
  --secuencial-min: #e6f0fd;
  --secuencial-max: #0d366b;
  --estado-bueno: #0ca30c;
  --estado-alerta: #fab219;
  --estado-serio: #ec835a;
  --estado-critico: #d03b3b;
  --estado-info: #2a78d6;
  --estado-generico: var(--p-emerald-500);
}
/* Las series no cambian: cada canal tiene un solo tono, el mismo en ambos
   modos, como en el croquis. */
.dark .tablero {
  --linea-referencia: #a3a29b;
  --secuencial-min: #1b2636;
  --secuencial-max: #9ec5f4;
  --estado-info: #3987e5;
}
.kpis {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}
.fila-2-1,
.fila-1-1 {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
}

.fieldset {
  border-color: var(--p-surface-400);
  border-style: dashed !important;
  border-width: 1px;
  padding: 1.5rem;
  background-color: var(--p-surface-50);
  margin-top: 2rem;
  /*border: none;*/
  /*var(--border);*/
  /*box-shadow: var(--shadow);  */
  /*border-0! shadow-md mt-10!*/
}
/*.shadow-md {
  box-shadow: 0px 3px 7px var(--p-surface-300);
}*/
.accesos {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}
.acceso {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 1.25rem 0.75rem;
  border-radius: var(--p-content-border-radius, 0.75rem);
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
  color: inherit;
  text-decoration: none;
}
.acceso:hover {
  border-color: var(--p-primary-color);
}
@container (min-width: 40rem) {
  .kpis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .accesos {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@container (min-width: 56rem) {
  .fila-1-1 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@container (min-width: 64rem) {
  .fila-2-1 {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  }
}
@container (min-width: 80rem) {
  .kpis {
    grid-template-columns: repeat(8, minmax(0, 1fr));
  }
}
.section-header {
  font-family: "Rajdhani-600", sans-serif;
  border-bottom: var(--border);
  border-color: var(--p-surface-300);
  display: flex;
  /*font-weight: 400px;*/
  width: 40%;
  color: var(--p-surface-500);
  /*justify-content: end;*/
  /*margin-left: auto;*/
}
</style>
