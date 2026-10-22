<!--
  Salidas del día que pasan por la estación; clic (o Enter) = abrir su croquis.

  Sobre `shared/data-grid/DataGrid.vue` (ADR-029): tabla cuando el contenedor
  mide 47rem o más (lo que suman los mínimos de las columnas) y, por debajo,
  tarjetas de dos líneas: viaje del cliente; ruta, empresa, precios y asientos
  vendidos/libres (el bus solo en la tabla). La salida elegida queda resaltada.

  El viaje es el del cliente (ADR-030): "Sube en" y "Baja en" con la hora en
  cada parada ("≈" si es estimada, "+1" si llega otro día), con el nombre debajo.
  La ruta del bus, por siglas y debajo los nombres. Una salida sin
  tarifa para ese viaje lo dice en el precio y no se puede elegir.
-->
<template>
  <DataGrid :columns="columns" :rows="salidas" :row-key="rowKey" :loading="cargando" :selected-keys="seleccion" :row-class="rowClass" :cards-below="54" :density="ancho ? 'comfortable' : 'normal'" max-height="38rem" row-clickable class="salidas" @row-click="elegir($event as SalidaResumen)">
    <template #header="{ column }">
      <span v-if="column.key === 'asientos'" class="asientos" aria-label="Vendidos y libres">
        <icon name="airline_seat_recline_extra" size="1.1rem" class="asientos__vendidos" title="Vendidos" />
        <icon name="tatami_seat" size="1.1rem" :weight="300" class="asientos__libres" title="Libres" />
      </span>
      <span v-if="column.key === 'hora'" class="pl-0.4">{{ column.label }}</span>
      <template v-else>{{ column.label }}</template>
    </template>

    <template #cell="{ row, column }">
      <div v-if="column.key === 'sube' || column.key === 'baja'" class="celda min-w-0 leading-tight">
        <div class="truncate tabular-nums">{{ horaPunta(s(row), column.key) }}</div>
        <div class="truncate text-xs text-muted-color" :title="punta(s(row), column.key).nombre ?? ''">{{ punta(s(row), column.key).nombre ?? "—" }}</div>
      </div>
      <div v-else-if="column.key === 'hora'" class="celda">
        <div class="tracking-wide text-center tabular-nums whitespace-nowrap">{{ hora(s(row).salida) }}</div>
        <div class="text-xs tabular-nums text-muted-color whitespace-nowrap">{{ fecha(s(row).salida) }}</div>
      </div>
      <div v-else-if="column.key === 'ruta'" class="celda ruta min-w-0 leading-tight">
        <div class="truncate" :title="rutaNombres(s(row))">{{ rutaSiglas(s(row)) }}</div>
        <div class="truncate text-xs tabular-nums text-muted-color" :title="rutaNombres(s(row))">{{ rutaNombres(s(row)) }}</div>
      </div>
      <GridText v-else-if="column.key === 'empresa'" class="celda text-[0.9rem]!" :text="nombreEmpresa(s(row))" />
      <template v-else-if="column.key === 'bus'">
        <span v-if="s(row).bus" class="celda truncate" :title="bus(s(row))">{{ s(row).bus!.codigo }}</span>
        <icon v-else name="no_transfer" class="text-surface-500" size="1.2rem" title="Sin bus" />
      </template>
      <span v-else-if="column.key === 'estado'" class="estado" :class="`estado--${s(row).estado}`" :title="s(row).estado">{{ s(row).estado.charAt(0).toUpperCase() }}</span>
      <template v-else-if="column.key === 'precios'">
        <span v-if="conTarifa(s(row))" class="precios precios--columna">
          <span v-for="(p, i) in s(row).precios" :key="p.clase" :class="{ 'text-orange-600 mt-1': i }"
            ><b>{{ p.clase }}</b> {{ precioCorto(p.precio) }}</span
          >
        </span>
        <span v-else class="sin-tarifa">Sin tarifa</span>
      </template>
      <span v-else-if="column.key === 'asientos' && s(row).capacidad" class="celda asientos asientos--valores">
        <span class="asientos__vendidos">{{ s(row).vendidos ?? 0 }}</span>
        <span class="asientos__libres">{{ libres(s(row)) }}</span>
      </span>
    </template>

    <template #card="{ row }">
      <div class="tarjeta">
        <div class="tarjeta__linea">
          <span class="flex-none font-semibold tabular-nums">{{ horaPunta(s(row), "sube") }}</span>
          <span class="min-w-0 flex-1 truncate text-right font-medium" :title="viaje(s(row))">{{ viaje(s(row)) }}</span>
        </div>
        <div class="tarjeta__linea text-xs text-muted-color">
          <span class="min-w-0 truncate" :title="ruta(s(row))">{{ ruta(s(row)) }} · {{ nombreEmpresa(s(row)) || "—" }}</span>
          <span v-if="conTarifa(s(row))" class="precios">
            <span v-for="p in s(row).precios" :key="p.clase"
              ><b>{{ p.clase }}</b> {{ precioCorto(p.precio) }}</span
            >
          </span>
          <span v-else class="sin-tarifa">Sin tarifa</span>
          <span v-if="s(row).capacidad" class="asientos asientos--valores ml-auto" :aria-label="`${s(row).vendidos ?? 0} vendidos, ${libres(s(row))} libres`">
            <span class="asientos__vendidos">{{ s(row).vendidos ?? 0 }}</span>
            <span class="asientos__libres">{{ libres(s(row)) }}</span>
          </span>
        </div>
      </div>
    </template>

    <template #empty>
      <icon name="no-transfer-outline" size="2rem" color="text-surface-400" />
      <span>No hay salidas para esa fecha y estación.</span>
    </template>
  </DataGrid>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import { diasEntre, fecha, hora, horaViaje } from "@/core/venta/modelo";
import type { Importe, Opcion, ParadaViaje, SalidaResumen } from "@/core/venta/types";

const props = defineProps<{
  salidas: SalidaResumen[];
  cargando: boolean;
  salidaId: number | null;
}>();
const emit = defineEmits<{ elegir: [id: number] }>();

const columns: GridColumn[] = [
  { key: "hora", label: "Hora", minWidth: "6.75rem" },
  { key: "ruta", label: "Ruta", minWidth: "9rem", width: "1.5fr" },
  { key: "sube", label: "Sube en", minWidth: "7rem" },
  { key: "baja", label: "Baja en", minWidth: "7rem" },
  { key: "empresa", label: "Empresa", width: "7rem" },
  { key: "bus", label: "Bus", width: "4rem" },
  { key: "estado", label: "Estado", width: "3rem" },
  { key: "precios", label: "Precio", width: "5rem" },
  { key: "asientos", label: "Asientos", width: "4.5rem", align: "center" },
];

/** Desde `lg` (64rem) las filas de la tabla respiran más. */
const consulta = window.matchMedia("(min-width: 64rem)");
const ancho = ref(consulta.matches);
const alCambiar = (e: MediaQueryListEvent) => (ancho.value = e.matches);
consulta.addEventListener("change", alCambiar);
onBeforeUnmount(() => consulta.removeEventListener("change", alCambiar));

const s = (row: unknown) => row as SalidaResumen;
const rowKey = (row: unknown) => String(s(row).id);
const seleccion = computed(() => new Set(props.salidaId == null ? [] : [String(props.salidaId)]));

const vendible = (r: SalidaResumen) => ["programada", "abordando"].includes(r.estado) && !!r.bus;
/** Hay tarifa para el viaje del cliente: sin ella no se vende, así que no se elige. */
const conTarifa = (r: SalidaResumen) => !!r.precios?.length;
const rowClass = (row: unknown) => {
  const r = s(row);
  return { "is-cancelada": r.estado === "cancelada", "is-no-vendible": !vendible(r), "is-sin-tarifa": !conTarifa(r) };
};
function elegir(r: SalidaResumen) {
  if (conTarifa(r)) emit("elegir", r.id);
}

/** Dónde sube o baja el cliente; sin `viaje`, las puntas de la ruta (hora solo en el origen). */
const punta = (r: SalidaResumen, cual: "sube" | "baja"): ParadaViaje => r.viaje?.[cual] ?? (cual === "sube" ? { ...r.ruta.origen, hora: r.salida, aprox: false } : { ...r.ruta.destino, hora: null, aprox: false });
/** "≈ 05:20 a. m. (+1)": la hora en la parada y, con `desde`, los días que pasan desde entonces. */
function horaDe(p: ParadaViaje | undefined, desde?: string | null) {
  if (!p?.hora) return "—";
  const dias = desde ? diasEntre(desde, p.hora) : 0;
  return `${horaViaje(p)}${dias > 0 ? ` (+${dias})` : ""}`;
}
/** La subida, a secas; la bajada, con "+1" si se llega otro día que el que se sube. */
const horaPunta = (r: SalidaResumen, cual: "sube" | "baja") => horaDe(punta(r, cual), cual === "baja" ? (punta(r, "sube").hora ?? r.salida) : null);
const viaje = (r: SalidaResumen) => `${punta(r, "sube").nombre ?? ""} → ${punta(r, "baja").nombre ?? ""}`;
/** Origen y destino de la ruta del bus, sin horas: las dos líneas de la celda Ruta. */
const rutaInicio = (r: SalidaResumen) => r.ruta.origen.nombre ?? "";
const rutaFin = (r: SalidaResumen) => r.ruta.destino.nombre ?? "";
/** "GUA - FLO": la sigla de cada punta de la ruta del bus (el nombre, si no tiene). */
const rutaSiglas = (r: SalidaResumen) => `${etiqueta(r.ruta.origen)} - ${etiqueta(r.ruta.destino)}`;
/** "Guatemala-Flores": los nombres completos, debajo de las siglas y en el tooltip. */
const rutaNombres = (r: SalidaResumen) => `${r.ruta.origen.nombre}-${r.ruta.destino.nombre}`;
const etiqueta = (o: Opcion & { alias: string | null }) => o.alias || o.nombre;
/** "Guatemala → Flores": la ruta entera, para la tarjeta. */
const ruta = (r: SalidaResumen) => `${rutaInicio(r)} → ${rutaFin(r)}`;
/** "TRANSPORTES FUENTES" → "Transportes Fuentes". */
const nombreEmpresa = (r: SalidaResumen) => (r.empresa?.nombre ?? "").toLocaleLowerCase("es").replace(/(^|[\s-])(\p{L})/gu, (_, sep: string, c: string) => sep + c.toLocaleUpperCase("es"));
const bus = (r: SalidaResumen) => [r.bus?.codigo, r.bus?.gama].filter(Boolean).join(" · ");
const libres = (r: SalidaResumen) => (r.capacidad ?? 0) - (r.vendidos ?? 0);
/** "Q 100.00" → "Q 100": en la lista los centavos solo se ven si los hay. */
const precioCorto = (p: Importe | null) => (p ? p.texto.replace(/[.,]00$/, "") : "—");
</script>

<style scoped>
/* Cancelada: tachada (fila y tarjeta). */
.salidas :deep(.dg-brow.is-cancelada),
.salidas :deep(.dg-card.is-cancelada) {
  position: relative;
}
.salidas :deep(.dg-brow.is-cancelada)::after,
.salidas :deep(.dg-card.is-cancelada)::after {
  content: "";
  position: absolute;
  inset-inline: 0;
  top: 50%;
  border-top: 1px solid var(--p-red-400);
  pointer-events: none;
}
/* Tarjetas de dos líneas: lo justo de aire para leerlas y tocarlas. */
.salidas :deep(.dg-cards) {
  gap: 0.375rem;
}
.salidas :deep(.dg-card--custom) {
  padding: 0.375rem 0.625rem;
  border-radius: 0.5rem;
}

/* Sin tarifa: no se puede elegir. */
.salidas :deep(.dg-brow.is-sin-tarifa),
.salidas :deep(.dg-card.is-sin-tarifa) {
  cursor: not-allowed;
  opacity: 0.55;
}
.sin-tarifa {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  font-style: italic;
  white-space: nowrap;
}

/* Fuera de móvil la tabla pasa a 1rem (los precios y la ruta conservan su tamaño). */
@media (min-width: 48rem) {
  .celda {
    font-size: 1rem;
  }
  .celda .text-xs,
  .celda.ruta {
    font-size: 0.9125rem;
  }
  .celda .asientos--valores,
  .asientos--valores.celda {
    font-size: 1rem;
  }
}

/* Estado: inicial en un círculo del color del estado. */
.estado {
  display: inline-flex;
  width: 1.75rem;
  height: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: var(--p-surface-200);
  color: var(--p-surface-700);
  font-size: 0.875rem;
  cursor: default;
}
/*.estado--programada {
  background: var(--p-amber-100);
  color: var(--p-amber-700);
}
.estado--abordando {
  background: var(--p-lime-100);
  color: var(--p-lime-700);
}
.estado--iniciada {
  background: var(--p-emerald-100);
  color: var(--p-emerald-700);
}*/
.estado--programada,
.estado--abordando,
.estado--iniciada,
.estado--finalizada {
  background: var(--p-surface-100);
  color: var(--p-surface-700);
}
.estado--cancelada {
  background: var(--p-red-100);
  color: var(--p-red-700);
}

/* Precio por clase de asiento: la letra en gris, el monto en negrita. */
.precios {
  display: inline-flex;
  flex: none;
  gap: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.precios b {
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  font-weight: 500;
}
.tarjeta .precios {
  color: var(--p-text-color);
}
.precios--columna {
  flex-direction: column;
  gap: 0;
  line-height: 1.2;
}

/* Vendidos · libres, en columnas de ancho fijo para que se alineen. */
.asientos {
  display: inline-grid;
  flex: none;
  grid-template-columns: repeat(2, 1.6rem);
  justify-items: center;
  gap: 0.125rem;
}
.asientos--valores {
  font-variant-numeric: tabular-nums;
  font-weight: 560;
}
.asientos__vendidos {
  color: var(--p-emerald-600);
}
.asientos__libres {
  color: var(--p-text-muted-color);
}

.tarjeta {
  display: flex;
  min-width: 0;
  flex-direction: column;
  line-height: 1.35;
}
.tarjeta__linea {
  display: flex;
  min-width: 0;
  align-items: baseline;
  gap: 0.5rem;
}
</style>
