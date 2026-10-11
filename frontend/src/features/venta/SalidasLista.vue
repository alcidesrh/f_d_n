<!--
  Salidas del día que pasan por la estación; clic (o Enter) = abrir su croquis.

  Sobre `shared/data-grid/DataGrid.vue` (ADR-029): tabla cuando el contenedor
  mide 45rem o más (lo que suman los mínimos de las columnas) y, por debajo,
  tarjetas de dos líneas: hora y viaje; empresa, precios y asientos
  vendidos/libres (el bus solo en la tabla). La salida elegida queda resaltada.

  El viaje es el del cliente (ADR-030): la hora en la estación donde sube
  ("≈" si es estimada), de dónde a dónde, la llegada ("+1" si es otro día) y
  las paradas en medio; la ruta del bus queda como dato secundario.
-->
<template>
  <DataGrid :columns="columns" :rows="salidas" :row-key="rowKey" :loading="cargando" :selected-keys="seleccion" :row-class="rowClass" :cards-below="45" max-height="38rem" row-clickable class="salidas" @row-click="emit('elegir', ($event as SalidaResumen).id)">
    <template #header="{ column }">
      <span v-if="column.key === 'asientos'" class="asientos" aria-label="Vendidos y libres">
        <icon name="airline_seat_recline_extra" size="1.1rem" class="asientos__vendidos" title="Vendidos" />
        <icon name="tatami_seat" size="1.1rem" :weight="300" class="asientos__libres" title="Libres" />
      </span>
      <template v-else>{{ column.label }}</template>
    </template>

    <template #cell="{ row, column }">
      <div v-if="column.key === 'salida'" class="min-w-0 leading-tight">
        <div class="font-medium tabular-nums">{{ horaDe(s(row)) }}</div>
        <div v-if="desdeOrigen(s(row))" class="truncate text-xs text-muted-color" :title="desdeOrigen(s(row))!">{{ desdeOrigen(s(row)) }}</div>
      </div>
      <div v-else-if="column.key === 'trayecto'" class="min-w-0 leading-tight">
        <div class="truncate font-medium" :title="trayecto(s(row))">{{ trayecto(s(row)) }}</div>
        <div v-if="llegada(s(row))" class="truncate text-xs text-muted-color" :title="llegada(s(row))!">{{ llegada(s(row)) }}</div>
      </div>
      <GridText v-else-if="column.key === 'empresa'" :text="s(row).empresa?.nombre ?? ''" />
      <template v-else-if="column.key === 'bus'">
        <span v-if="s(row).bus" class="truncate" :title="bus(s(row))">
          {{ s(row).bus!.codigo }}<span v-if="s(row).bus!.gama" class="text-xs text-muted-color"> · {{ s(row).bus!.gama }}</span>
        </span>
        <icon v-else name="no_transfer" class="text-surface-500" size="1.2rem" title="Sin bus" />
      </template>
      <span v-else-if="column.key === 'estado'" class="estado">{{ s(row).estado }}</span>
      <span v-else-if="column.key === 'precios' && s(row).precios?.length" class="precios precios--columna">
        <span v-for="p in s(row).precios" :key="p.clase"><b>{{ p.clase }}</b> {{ precioCorto(p.precio) }}</span>
      </span>
      <span v-else-if="column.key === 'asientos' && s(row).capacidad" class="asientos asientos--valores">
        <span class="asientos__vendidos">{{ s(row).vendidos ?? 0 }}</span>
        <span class="asientos__libres">{{ libres(s(row)) }}</span>
      </span>
    </template>

    <template #card="{ row }">
      <div class="tarjeta">
        <div class="tarjeta__linea">
          <span class="flex-none font-semibold tabular-nums">{{ horaDe(s(row)) }}</span>
          <span class="min-w-0 flex-1 truncate text-right font-medium" :title="trayecto(s(row))">{{ trayecto(s(row)) }}</span>
        </div>
        <div class="tarjeta__linea text-xs text-muted-color">
          <span class="min-w-0 truncate">
            <template v-if="llegada(s(row))">{{ llegada(s(row)) }} · </template><template v-if="desdeOrigen(s(row))">{{ desdeOrigen(s(row)) }} · </template>{{ s(row).empresa?.nombre ?? "—" }}
          </span>
          <span v-if="s(row).precios?.length" class="precios">
            <span v-for="p in s(row).precios" :key="p.clase"><b>{{ p.clase }}</b> {{ precioCorto(p.precio) }}</span>
          </span>
          <span v-if="s(row).capacidad" class="asientos asientos--valores ml-auto" :aria-label="`${s(row).vendidos ?? 0} vendidos, ${libres(s(row))} libres`">
            <span class="asientos__vendidos">{{ s(row).vendidos ?? 0 }}</span>
            <span class="asientos__libres">{{ libres(s(row)) }}</span>
          </span>
        </div>
      </div>
    </template>

    <template #empty>
      <icon name="directions-bus-outline" size="2rem" />
      <span>No hay salidas para esa fecha y estación.</span>
    </template>
  </DataGrid>
</template>

<script setup lang="ts">
import { computed } from "vue";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import { diasEntre, hora, horaViaje } from "@/core/venta/modelo";
import type { Importe, SalidaResumen } from "@/core/venta/types";

const props = defineProps<{
  salidas: SalidaResumen[];
  cargando: boolean;
  salidaId: number | null;
}>();
const emit = defineEmits<{ elegir: [id: number] }>();

const columns: GridColumn[] = [
  { key: "salida", label: "Salida", width: "6rem" },
  { key: "trayecto", label: "Viaje", minWidth: "10rem", width: "2fr" },
  { key: "empresa", label: "Empresa", minWidth: "6rem" },
  { key: "bus", label: "Bus", width: "5.5rem" },
  { key: "estado", label: "Estado", width: "6rem" },
  { key: "precios", label: "Precio", width: "6.5rem" },
  { key: "asientos", label: "Asientos", width: "4.5rem", align: "center" },
];

const s = (row: unknown) => row as SalidaResumen;
const rowKey = (row: unknown) => String(s(row).id);
const seleccion = computed(() => new Set(props.salidaId == null ? [] : [String(props.salidaId)]));

const vendible = (r: SalidaResumen) => ["programada", "abordando"].includes(r.estado) && !!r.bus;
const rowClass = (row: unknown) => {
  const r = s(row);
  return { "is-cancelada": r.estado === "cancelada", "is-no-vendible": !vendible(r) };
};

/** Hora donde sube el cliente ("≈" si es estimada; "—" si no se conoce). */
const horaDe = (r: SalidaResumen) => (r.viaje ? horaViaje(r.viaje.sube) : hora(r.salidaEstacion ?? r.salida));
/** "sale 08:00 de Guatemala" cuando no se sube en el origen del bus. */
const desdeOrigen = (r: SalidaResumen) => (r.viaje && r.viaje.sube.id !== r.ruta.origen.id ? `bus sale ${hora(r.salida)} de ${r.ruta.origen.nombre}` : null);
const trayecto = (r: SalidaResumen) => (r.viaje ? `${r.viaje.sube.nombre ?? ""} → ${r.viaje.baja.nombre ?? ""}` : `${r.ruta.origen.nombre ?? ""} → ${r.ruta.destino.nombre ?? ""}`);
/** "llega ≈ 05:20 (+1) · 7 paradas". */
const llegada = (r: SalidaResumen) => {
  if (!r.viaje) return null;
  const { sube, baja, intermedias } = r.viaje;
  const dias = baja.hora ? diasEntre(sube.hora ?? r.salida, baja.hora) : 0;
  const partes = [baja.hora ? `llega ${horaViaje(baja)}${dias > 0 ? ` (+${dias})` : ""}` : null, intermedias ? `${intermedias} parada${intermedias === 1 ? "" : "s"}` : "directo"];
  return partes.filter(Boolean).join(" · ");
};
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

.estado {
  width: fit-content;
  padding: 0.25rem 0.5rem;
  border-radius: 0.3rem;
  font-size: 0.6rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* Precio por clase de asiento: la letra en gris, el monto en negrita. */
.precios {
  display: inline-flex;
  flex: none;
  gap: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
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
  font-size: 0.8125rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.asientos__vendidos {
  color: var(--p-blue-600);
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
