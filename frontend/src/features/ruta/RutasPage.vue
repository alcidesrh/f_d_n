<!--
  Rutas (ADR-030): el camino con paradas que recorre un bus. Búsqueda por
  nombre o código, filtros por origen y destino (sus puntas) y, por defecto,
  solo las activas. Por fila: editar,
  crear la de regreso (paradas al revés, queda inactiva), duplicar y eliminar
  (solo sin salidas ni esquemas; si no, se desactiva).
  Contenido angosto: tarjetas; desde 56rem de ancho del contenido: tabla.
  Solo se monta una de las dos vistas, y la búsqueda se aplica con una pausa
  de 150 ms para no re-renderizar toda la lista en cada tecla.
-->
<template>
  <div ref="raizEl" class="@container flex flex-col gap-4">
    <RouterLink v-if="puedeEditar" :to="{ name: 'ruta', params: { id: 'nueva' } }" class="no-underline ml-auto">
      <Button label="Nueva ruta" size="small"
        ><template #icon><icon name="add" class="mr-1" /></template
      ></Button>
    </RouterLink>

    <section class="panel flex flex-col gap-3 @3xl:flex-row @3xl:items-end">
      <label class="flex flex-1 flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Buscar</span>
        <IconField>
          <InputIcon><icon name="search" /></InputIcon>
          <InputText v-model="busquedaEntrada" placeholder="Nombre o código" fluid />
        </IconField>
      </label>
      <label class="flex flex-col gap-1 @3xl:w-56">
        <span class="text-xs font-medium text-muted-color">Origen</span>
        <Select v-model="origenId" :options="lugares" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'departamento']" show-clear placeholder="Cualquiera" fluid />
      </label>
      <label class="flex flex-col gap-1 @3xl:w-56">
        <span class="text-xs font-medium text-muted-color">Destino</span>
        <Select v-model="destinoId" :options="lugares" option-label="nombre" option-value="id" filter :filter-fields="['nombre', 'departamento']" show-clear placeholder="Cualquiera" fluid />
      </label>
      <label class="flex items-center gap-2 text-sm">
        <ToggleSwitch v-model="conInactivas" input-id="rutas-inactivas" />
        Mostrar las inactivas
      </label>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Angosto: tarjetas -->
    <div v-if="angosto" class="flex flex-col gap-3">
      <p v-if="truncado" class="m-0 text-xs text-muted-color">Hay más rutas que coinciden: afine la búsqueda para verlas.</p>
      <Skeleton v-if="!rutas" height="8rem" />
      <p v-else-if="!visibles.length" class="panel m-0 text-center text-sm text-muted-color">No hay rutas con esta búsqueda.</p>
      <article v-for="r in visibles" :key="r.id" class="panel !p-3 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
          <div>
            <RouterLink :to="{ name: 'ruta', params: { id: r.id } }" class="font-semibold">{{ r.nombre }}</RouterLink>
            <div class="text-sm">{{ r.origen }} → {{ r.destino }}</div>
            <div class="text-xs text-muted-color">
              {{ r.paradas }} paradas · {{ duracion(r.duracionMinutos) }}<template v-if="r.codigo"> · {{ r.codigo }}</template>
            </div>
          </div>
          <div class="flex flex-col items-end gap-1">
            <Tag v-if="!r.activo" value="Inactiva" severity="secondary" />
            <span class="text-xs tabular-nums text-muted-color">{{ r.salidasFuturas }} salidas próximas</span>
          </div>
        </div>
        <div class="flex justify-end gap-1">
          <Button v-for="a in acciones" :key="a.op" :severity="a.severidad" text rounded size="small" class="tap-target" :disabled="!puedeEditar && a.op !== 'editar'" :aria-label="a.etiqueta" :title="a.etiqueta" @click="hacer(a.op, r)">
            <template #icon><icon :name="a.icono" /></template>
          </Button>
        </div>
      </article>
    </div>

    <!-- Ancho: tabla -->
    <DataTable v-else :value="visibles" data-key="id" paginator :rows="25" :rows-per-page-options="[25, 50, 100]" size="small" striped-rows sort-field="nombre" :sort-order="1">
      <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">No hay rutas con esta búsqueda.</p></template>
      <Column field="nombre" header="Ruta" sortable>
        <template #body="{ data }">
          <RouterLink :to="{ name: 'ruta', params: { id: data.id } }" class="font-medium">{{ data.nombre }}</RouterLink>
          <div class="text-xs text-muted-color">{{ data.origen }} → {{ data.destino }}</div>
        </template>
      </Column>
      <Column field="paradas" header="Paradas" sortable class="tabular-nums" />
      <Column field="duracionMinutos" header="Duración" sortable>
        <template #body="{ data }">{{ duracion(data.duracionMinutos) }}</template>
      </Column>
      <Column field="kilometros" header="Km" sortable class="tabular-nums" />
      <Column field="salidasFuturas" header="Salidas próximas" sortable class="tabular-nums" />
      <Column field="codigo" header="Código" sortable />
      <Column header="">
        <template #body="{ data }">
          <Tag v-if="!data.activo" value="Inactiva" severity="secondary" />
        </template>
      </Column>
      <Column header="" class="w-0">
        <template #body="{ data }">
          <div class="flex justify-end gap-1 whitespace-nowrap">
            <Button v-for="a in acciones" :key="a.op" :severity="a.severidad" text rounded size="small" :disabled="!puedeEditar && a.op !== 'editar'" :aria-label="a.etiqueta" :title="a.etiqueta" @click="hacer(a.op, data)">
              <template #icon><icon :name="a.icono" /></template>
            </Button>
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup lang="ts">
import { duplicarRuta, eliminarRuta, fetchOpcionesRutas, fetchRutas } from "@/core/ruta/api";
import { duracion } from "@/core/ruta/editor";
import type { Lugar, RutaFila } from "@/core/ruta/types";
import { HttpError } from "@/core/http";
import { notify } from "@/core/notify";
import { useConfirm } from "primevue/useconfirm";

type Op = "editar" | "regreso" | "duplicar" | "eliminar";

const acciones: Array<{
  op: Op;
  icono: string;
  etiqueta: string;
  severidad: "secondary" | "danger";
}> = [
  { op: "editar", icono: "edit-outline", etiqueta: "Ver y editar", severidad: "secondary" },
  {
    op: "regreso",
    icono: "swap-horiz",
    etiqueta: "Crear la ruta de regreso",
    severidad: "secondary",
  },
  { op: "duplicar", icono: "content-copy-outline", etiqueta: "Duplicar", severidad: "secondary" },
  { op: "eliminar", icono: "delete-outline", etiqueta: "Eliminar", severidad: "danger" },
];

/** Pausa tras la última tecla antes de filtrar (como `IconPicker`, algo más largo para listas). */
const PAUSA_BUSQUEDA_MS = 150;
/** Ancho del contenido desde el que se muestra la tabla (`@4xl`). */
const ANCHO_TABLA_REM = 56;

const rutas = shallowRef<RutaFila[] | null>(null);
const puedeEditar = ref(false);
const error = ref("");
const busquedaEntrada = ref("");
const busqueda = ref("");
const conInactivas = ref(false);
/** Lugares para elegir origen y destino (las puntas de la ruta son lugares, no solo estaciones). */
const lugares = shallowRef<Lugar[]>([]);
const origenId = ref<number | null>(null);
const destinoId = ref<number | null>(null);
const angosto = ref(false);
const raizEl = ref<HTMLElement | null>(null);
const router = useRouter();
const confirm = useConfirm();

let pausa: ReturnType<typeof setTimeout> | undefined;
let observador: ResizeObserver | null = null;

watch(busquedaEntrada, (valor) => {
  clearTimeout(pausa);
  pausa = setTimeout(() => (busqueda.value = valor), PAUSA_BUSQUEDA_MS);
});
watch([busqueda, conInactivas, origenId, destinoId], () => void cargar());

/** Lo que coincide con la búsqueda lo decide el servidor: aquí solo llega ese resultado. */
const visibles = computed(() => rutas.value ?? []);
const truncado = ref(false);
let carga = 0;

async function cargar() {
  const mia = ++carga;
  error.value = "";
  try {
    const r = await fetchRutas(busqueda.value.trim(), conInactivas.value, origenId.value, destinoId.value);
    if (mia !== carga) return;
    rutas.value = r.items;
    truncado.value = r.truncado;
    puedeEditar.value = r.puedeEditar;
  } catch (e) {
    if (mia !== carga) return;
    error.value = e instanceof HttpError && e.status === 403 ? "No tiene permiso para ver las rutas (salida.ver)." : e instanceof Error ? e.message : String(e);
  }
}

async function hacer(op: Op, r: RutaFila) {
  if (op === "editar") return router.push({ name: "ruta", params: { id: r.id } });
  if (op === "eliminar") {
    return confirm.require({
      header: "Eliminar ruta",
      message: `¿Eliminar «${r.nombre}»? Solo se puede si no tiene salidas ni esquemas; si los tiene, desactívela.`,
      acceptProps: { label: "Eliminar", severity: "danger" },
      rejectProps: { label: "Cancelar", severity: "secondary" },
      accept: async () => {
        try {
          await eliminarRuta(r.id);
          notify.success("Ruta eliminada");
          await cargar();
        } catch (e) {
          notify.error(e instanceof Error ? e.message : String(e));
        }
      },
    });
  }
  try {
    const nueva = await duplicarRuta(r.id, op === "regreso");
    notify.success(op === "regreso" ? "Ruta de regreso creada (inactiva): revise los minutos" : "Copia creada (inactiva)");
    await router.push({ name: "ruta", params: { id: nueva.id } });
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e));
  }
}

onMounted(() => {
  cargar();
  fetchOpcionesRutas()
    .then((o) => (lugares.value = o.lugares))
    .catch((e) => notify.error(e instanceof Error ? e.message : String(e)));
  const el = raizEl.value;
  if (!el) return;
  observador = new ResizeObserver(([e]) => {
    const px = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    angosto.value = (e?.contentRect.width ?? 0) < ANCHO_TABLA_REM * px;
  });
  observador.observe(el);
});

onBeforeUnmount(() => {
  clearTimeout(pausa);
  observador?.disconnect();
});
</script>
