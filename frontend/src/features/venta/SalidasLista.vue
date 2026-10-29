<!--
  Salidas del día que pasan por la estación; clic (o Enter) = abrir su croquis.

  Sobre `shared/data-grid/DataGrid.vue` (ADR-029): tabla cuando el contenedor
  mide 47rem o más (lo que suman los mínimos de las columnas) y, por debajo,
  tarjetas de dos líneas: viaje del cliente; ruta, empresa, precios y asientos
  vendidos/libres (el bus solo en la tabla). La salida elegida queda resaltada.

  El viaje es el del cliente (ADR-030): "Sube en" y "Baja en" con la hora en
  cada parada ("≈" si es estimada, "+1" si llega otro día), con el nombre debajo.
  La ruta del bus, de origen a destino. Una salida sin
  tarifa para ese viaje lo dice en el precio y no se puede elegir.

  Si la lista junta salidas de más de un día (por la hora de salida), cada
  día se distingue: fondo celeste en el anterior (alternando hacia atrás
  desde el último), una línea al empezar otro día y la fecha con el día de
  la semana en una etiqueta.
-->
<template>
  <div class="salidas-lista">
    <DataGrid :columns="columns" :rows="salidas" :row-key="rowKey" :loading="cargando" :selected-keys="seleccion" :row-class="rowClass" :cards-below="54" :density="ancho ? 'comfortable' : 'normal'" max-height="38rem" row-clickable class="salidas" @row-click="elegir($event as SalidaResumen)">
      <template #header="{ column }">
        <span v-if="column.key === 'asientos'" class="asientos" aria-label="Vendidos y libres">
          <icon name="airline_seat_recline_extra" size="1.1rem" class="asientos__vendidos" title="Vendidos" />
          <icon name="tatami_seat" size="1.1rem" :weight="300" class="asientos__libres" title="Libres" />
        </span>
        <template v-else>{{ column.label }}</template>
      </template>

      <template #cell="{ row, column }">
        <div v-if="column.key === 'sube' || column.key === 'baja'" class="celda min-w-0 leading-tight">
          <div class="truncate tabular-nums font-medium" :title="punta(s(row), column.key).nombre ?? ''">
            {{ punta(s(row), column.key).nombre ?? "—" }}
          </div>
          <div class="truncate text-xs text-muted-color font-medium">{{ horaPunta(s(row), column.key) }}</div>
        </div>
        <div v-else-if="column.key === 'hora'" class="celda">
          <div class="tracking-wide tabular-nums whitespace-nowrap font-medium">
            {{ hora(s(row).salida) }}
          </div>
          <div v-if="variosDias" class="dia" :class="{ 'dia--alterno': alterno(s(row)) }">{{ diaSemana(s(row).salida) }}</div>
          <div v-else class="text-xs text-muted-color tabular-nums whitespace-nowrap font-medium">
            {{ fecha(s(row).salida) }}
          </div>
        </div>
        <div v-else-if="column.key === 'ruta'" class="celda ruta min-w-0 font-medium">
          <div class="truncate" :title="ruta(s(row))">{{ ruta(s(row)) }}</div>
        </div>
        <GridText v-else-if="column.key === 'empresa'" class="celda text-[0.9rem]!" :text="nombreEmpresa(s(row))" />
        <template v-else-if="column.key === 'bus'">
          <span v-if="s(row).bus" class="celda truncate" :title="bus(s(row))">{{ s(row).bus!.codigo }}</span>
          <icon v-else name="no_transfer" class="text-surface-500" size="1.2rem" title="Sin bus" />
        </template>
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
            <span v-if="variosDias" class="dia flex-none" :class="{ 'dia--alterno': alterno(s(row)) }">{{ diaSemana(s(row).salida) }}</span>
            <span class="flex-none font-semibold tabular-nums">{{ horaPunta(s(row), "sube") }}</span>
            <span class="min-w-0 flex-1 truncate text-right font-medium" :title="viaje(s(row))">{{ viaje(s(row)) }}</span>
          </div>
          <div class="tarjeta__linea text-sm text-muted-color">
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
    <p class="salidas-total" role="status">{{ total }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import GridText from "@/shared/data-grid/GridText.vue";
import type { GridColumn } from "@/shared/data-grid/types";
import { diasEntre, fecha, hora, horaViaje } from "@/core/venta/modelo";
import type { Importe, ParadaViaje, SalidaResumen } from "@/core/venta/types";

const props = defineProps<{
  salidas: SalidaResumen[];
  cargando: boolean;
  salidaId: number | null;
}>();
const emit = defineEmits<{ elegir: [id: number] }>();

const columns: GridColumn[] = [
  { key: "hora", label: "Hora", width: "120px" },
  { key: "ruta", label: "Ruta", minWidth: "9rem", width: "1.5fr" },
  { key: "sube", label: "Sube en", minWidth: "7rem" },
  { key: "baja", label: "Baja en", minWidth: "7rem" },
  { key: "empresa", label: "Empresa", width: "7rem" },
  { key: "bus", label: "Bus", width: "4rem" },
  { key: "precios", label: "Precio", width: "5rem" },
  { key: "asientos", label: "Asientos", width: "4.5rem", align: "center" },
];

/** Desde `lg` (64rem) las filas de la tabla respiran más. */
const consulta = window.matchMedia("(min-width: 64rem)");
const ancho = ref(consulta.matches);
const alCambiar = (e: MediaQueryListEvent) => (ancho.value = e.matches);
consulta.addEventListener("change", alCambiar);
onBeforeUnmount(() => consulta.removeEventListener("change", alCambiar));

/** "3 salidas" bajo la lista: cuántas quedan listadas con el filtro de hoy. */
const total = computed(() => `${props.salidas.length} ${props.salidas.length === 1 ? "salida" : "salidas"}`);

const s = (row: unknown) => row as SalidaResumen;
const rowKey = (row: unknown) => String(s(row).id);
const seleccion = computed(() => new Set(props.salidaId == null ? [] : [String(props.salidaId)]));

/** Día de cada salida (por su hora de salida) en el orden de la lista: el número de grupo y si lo abre. */
const dias = computed(() => {
  const porId = new Map<number, { indice: number; primero: boolean }>();
  let anterior: string | null = null;
  let indice = -1;
  for (const r of props.salidas) {
    const clave = fecha(r.salida);
    const primero = clave !== anterior;
    if (primero) indice++;
    anterior = clave;
    porId.set(r.id, { indice, primero });
  }
  return { porId, cuantos: indice + 1 };
});
const variosDias = computed(() => dias.value.cuantos > 1);
const dia = (r: SalidaResumen) => dias.value.porId.get(r.id) ?? { indice: 0, primero: false };
/** Con fondo los días anteriores al último, alternando hacia atrás: el día actual va sin fondo. */
const alterno = (r: SalidaResumen) => variosDias.value && (dias.value.cuantos - 1 - dia(r).indice) % 2 === 1;
/** "lun 26/10". */
const diaSemana = (iso: string) => {
  const partes = new Intl.DateTimeFormat("es-GT", { weekday: "short", day: "2-digit", month: "2-digit", timeZone: "America/Guatemala" }).formatToParts(new Date(iso));
  const de = (t: string) => partes.find((p) => p.type === t)?.value ?? "";
  return `${de("weekday").replace(".", "")} ${de("day")}/${de("month")}`;
};

const vendible = (r: SalidaResumen) => ["programada", "abordando"].includes(r.estado) && !!r.bus;
/** Hay tarifa para el viaje del cliente: sin ella no se vende, así que no se elige. */
const conTarifa = (r: SalidaResumen) => !!r.precios?.length;
const rowClass = (row: unknown) => {
  const r = s(row);
  return {
    "is-cancelada": r.estado === "cancelada",
    "is-no-vendible": !vendible(r),
    "is-sin-tarifa": !conTarifa(r),
    "is-dia-alterno": alterno(r),
    "is-nuevo-dia": variosDias.value && dia(r).primero && dia(r).indice > 0,
  };
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
/** Origen y destino de la ruta del bus, sin horas. */
const rutaInicio = (r: SalidaResumen) => r.ruta.origen.nombre ?? "";
const rutaFin = (r: SalidaResumen) => r.ruta.destino.nombre ?? "";
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
/* Conteo bajo la lista, centrado. */
.salidas-total {
  margin: 0.8rem 0 0;
  text-align: center;
  font-size: 0.9125rem;
  color: var(--p-text-muted-color);
  /*font-weight: 500;*/
  font-variant-numeric: tabular-nums;
  position: absolute;
  left: 50%;
  transform: translate(-50%, -50%);
}

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
/* Varios días: bandas alternas y una línea donde empieza otro día. */
.salidas :deep(.dg-brow.is-dia-alterno:not(:hover, .is-selected)),
.salidas :deep(.dg-card.is-dia-alterno:not(.is-selected)) {
  --dg-row-bg: color-mix(in srgb, var(--p-sky-500) 7%, var(--dg-bg));
}
.salidas :deep(.dg-brow.is-nuevo-dia) {
  box-shadow: inset 0 2px 0 var(--p-sky-500);
}
.salidas :deep(.dg-card.is-nuevo-dia) {
  margin-top: 0.5rem;
}
.dia {
  display: inline-block;
  padding: 0 0.4rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  text-transform: capitalize;
  background: var(--p-surface-200);
  color: var(--p-surface-700);
}
.dia--alterno {
  background: var(--p-sky-100);
  color: var(--p-sky-800);
}
.celda .dia {
  font-size: 0.75rem;
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
  /*.celda .text-sm,
  .celda.ruta {
    font-size: 0.9525rem;
  }*/
  .celda .asientos--valores,
  .asientos--valores.celda {
    font-size: 1rem;
  }
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
