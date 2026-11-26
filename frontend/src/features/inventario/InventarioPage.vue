<!--
  Inventario de la logística (ADR-071), ruta `/inventario`: qué hay en cada
  almacén (estación, taller, oficina o bus), a qué costo y por qué cambió.

  - Existencias del almacén elegido, con su estado (agotado, bajo el mínimo,
    por reponer) y cuánto reponer descontando lo que viene en camino.
  - «Registrar»: entrada, salida, envío o conteo, según lo que puede ahí.
  - «Niveles» de cada artículo (con `inventario.articulos`).
  - En camino: los envíos que aún no se reciben, con «Recibir» en el destino.
  - Por reponer: lo que toca en todos los almacenes que ve, lo más grave
    primero (para decidir qué se compra en el lugar y qué se envía).
  - Libro: los movimientos del almacén elegido.

  `?almacen=` y `?pestana=` quedan en la URL. Al bajar del mínimo el backend
  crea la afectación del faltante; al reponerse, la resuelve.
-->
<template>
  <div class="@container mx-auto flex w-full max-w-6xl flex-col gap-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex min-w-0 flex-col gap-1">
        <h1 class="m-0 flex items-center gap-2 text-xl font-semibold"><icon name="inventory-2-outline" />Inventario</h1>
        <p class="m-0 text-sm text-muted-color">Qué hay en cada almacén, a qué costo y por qué cambió. Cuando algo baja del mínimo se avisa a quien lo atiende, como una afectación.</p>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <RouterLink v-if="acceso.puedeRecurso('Articulo', 'editar')" :to="{ name: 'entity-list', params: { entity: 'articulo' } }">Artículos</RouterLink>
        <RouterLink v-if="acceso.puedeRecurso('Almacen', 'editar')" :to="{ name: 'entity-list', params: { entity: 'almacen' } }">Almacenes</RouterLink>
      </div>
    </header>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-else-if="!almacenes" height="12rem" />
    <p v-else-if="!almacenes.length" class="m-0 py-10 text-center text-sm text-muted-color">No tiene almacenes a la vista. Pida que le den acceso al inventario de su estación o taller.</p>
    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <Select v-model="almacenId" :options="almacenes" option-value="id" filter :filter-fields="['nombre', 'empresa.nombre', 'tipoEtiqueta']" aria-label="Almacén" class="w-full sm:w-96">
          <template #value="{ value }">
            <span v-if="elegido && value">{{ nombreAlmacen(elegido, variasEmpresas) }}</span>
            <span v-else class="text-muted-color">Elija el almacén</span>
          </template>
          <template #option="{ option }">
            <div class="flex w-full items-center justify-between gap-2">
              <div class="flex min-w-0 flex-col">
                <span :class="{ 'text-muted-color line-through': !option.activo }">{{ nombreAlmacen(option, variasEmpresas) }}</span>
                <span class="text-xs text-muted-color">{{ option.tipoEtiqueta }}<template v-if="option.estacion && option.tipo !== 'estacion'"> · {{ option.estacion.nombre }}</template><template v-if="option.bus"> · bus {{ option.bus.codigo }}</template></span>
              </div>
              <Badge v-if="option.faltantes" v-tooltip.left="'Artículos debajo del mínimo'" :value="option.faltantes" severity="warn" />
            </div>
          </template>
        </Select>
        <div class="flex items-center gap-2">
          <Button severity="secondary" text size="small" :loading="cargando" aria-label="Actualizar" @click="refrescar()">
            <template #icon><icon name="refresh" /></template>
          </Button>
          <Button v-if="datos && permitidos.length" label="Registrar" @click="abrirRegistro()">
            <template #icon><icon name="add" class="mr-1" /></template>
          </Button>
        </div>
      </div>

      <Tabs v-model:value="pestana">
        <TabList>
          <Tab value="existencias">Existencias</Tab>
          <Tab value="en-camino">En camino <Badge v-if="enCamino.length" :value="enCamino.length" severity="info" class="ml-1" /></Tab>
          <Tab value="por-reponer">Por reponer <Badge v-if="porReponer.length" :value="porReponer.length" severity="warn" class="ml-1" /></Tab>
          <Tab value="libro">Libro</Tab>
        </TabList>
        <TabPanels class="!px-0">
          <TabPanel value="existencias">
            <Message v-if="datos && !datos.almacen.activo" severity="secondary" size="small" :closable="false" class="mb-3">Este almacén está inactivo: no recibe ni envía, solo se cuenta para dejarlo en cero.</Message>
            <DataTable :value="datos?.filas ?? []" :loading="cargandoExistencias" data-key="id" size="small" striped-rows sort-field="articulo.nombre" :sort-order="1">
              <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">Este almacén todavía no tiene existencias. Registre una entrada para empezar.</p></template>
              <Column field="articulo.nombre" header="Artículo" sortable>
                <template #body="{ data }">
                  <div :class="{ 'text-muted-color': !data.articulo.activo }">{{ data.articulo.nombre }}</div>
                  <div class="text-xs text-muted-color">{{ data.articulo.codigo }}<template v-if="data.articulo.categoria"> · {{ data.articulo.categoria }}</template></div>
                </template>
              </Column>
              <Column header="Hay" class="text-right tabular-nums">
                <template #body="{ data }">
                  <span class="whitespace-nowrap">{{ cantidad(data.cantidad) }} {{ data.articulo.unidad }}</span>
                  <div v-if="Number(data.enCamino) > 0" class="text-xs text-muted-color">+{{ cantidad(data.enCamino) }} en camino</div>
                </template>
              </Column>
              <Column field="estado" header="Estado" sortable :sort-field="(e: Existencia) => String(PESO[e.estado])">
                <template #body="{ data }"><Tag :value="ESTADOS[data.estado as EstadoExistencia].etiqueta" :severity="ESTADOS[data.estado as EstadoExistencia].severidad" /></template>
              </Column>
              <Column header="Mín. · reorden · máx." class="tabular-nums">
                <template #body="{ data }"><span class="whitespace-nowrap text-sm">{{ Number(data.minimo) ? cantidad(data.minimo) : "—" }} · {{ cantidad(data.puntoReorden) }} · {{ cantidad(data.maximo) }}</span></template>
              </Column>
              <Column header="Reponer" class="text-right tabular-nums">
                <template #body="{ data }">{{ Number(data.reponer) ? cantidad(data.reponer) : "" }}</template>
              </Column>
              <Column header="Costo prom." class="text-right tabular-nums">
                <template #body="{ data }">{{ quetzales(data.costoPromedio, true) }}</template>
              </Column>
              <Column header="Valor" class="text-right tabular-nums">
                <template #body="{ data }">{{ quetzales(data.valor) }}</template>
              </Column>
              <Column class="w-0">
                <template #body="{ data }">
                  <div class="flex justify-end gap-1">
                    <Button v-if="datos?.almacen.puede.niveles" v-tooltip.top="'Niveles'" severity="secondary" text rounded size="small" aria-label="Niveles" @click="nivelando = data">
                      <template #icon><icon name="tune" /></template>
                    </Button>
                    <Button v-if="permitidos.length" v-tooltip.top="'Registrar'" severity="secondary" text rounded size="small" aria-label="Registrar" @click="abrirRegistro({ articulo: data.articulo.id })">
                      <template #icon><icon name="add" /></template>
                    </Button>
                  </div>
                </template>
              </Column>
              <template v-if="datos?.filas.length" #footer>
                <div class="text-right text-sm">Valor del almacén: <strong>{{ quetzales(datos.valor) }}</strong></div>
              </template>
            </DataTable>
          </TabPanel>

          <TabPanel value="en-camino">
            <p v-if="!enCamino.length" class="m-0 py-6 text-center text-sm text-muted-color">No hay envíos en camino desde ni hacia sus almacenes.</p>
            <div v-else class="flex flex-col gap-2">
              <article v-for="m in enCamino" :key="m.id" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-surface-200 p-3 dark:border-surface-700">
                <div class="flex min-w-0 flex-col gap-0.5">
                  <span class="font-medium">{{ cantidad(String(-Number(m.cantidad))) }} {{ m.articulo.unidad }} de «{{ m.articulo.nombre }}»</span>
                  <span class="flex items-center gap-1 text-sm">{{ m.almacen.nombre }} <icon name="arrow-forward" sm /> {{ m.destino?.nombre ?? "—" }}</span>
                  <span class="text-xs text-muted-color">Salió {{ fecha(m.creadoEn) }}<template v-if="m.usuario"> · {{ m.usuario.nombre }}</template><template v-if="m.documento"> · doc. {{ m.documento }}</template></span>
                  <span v-if="m.motivo" class="text-xs text-muted-color">{{ m.motivo }}</span>
                </div>
                <Button v-if="m.puedeRecibir" label="Recibir" size="small" @click="abrirRecibir(m)">
                  <template #icon><icon name="move-to-inbox-outline" class="mr-1" /></template>
                </Button>
              </article>
            </div>
          </TabPanel>

          <TabPanel value="por-reponer">
            <DataTable :value="porReponer" data-key="id" size="small" striped-rows>
              <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">Nada debajo del mínimo ni en el punto de reorden.</p></template>
              <Column header="Almacén">
                <template #body="{ data }">
                  <a href="#" @click.prevent="verAlmacen(data.almacen.id)">{{ nombreAlmacen(data.almacen, variasEmpresas) }}</a>
                </template>
              </Column>
              <Column header="Artículo">
                <template #body="{ data }">{{ data.articulo.nombre }}</template>
              </Column>
              <Column header="Hay" class="text-right tabular-nums">
                <template #body="{ data }"><span class="whitespace-nowrap">{{ cantidad(data.cantidad) }} {{ data.articulo.unidad }}</span></template>
              </Column>
              <Column header="Estado">
                <template #body="{ data }"><Tag :value="ESTADOS[data.estado as EstadoExistencia].etiqueta" :severity="ESTADOS[data.estado as EstadoExistencia].severidad" /></template>
              </Column>
              <Column header="Mínimo" class="text-right tabular-nums">
                <template #body="{ data }">{{ Number(data.minimo) ? cantidad(data.minimo) : "—" }}</template>
              </Column>
              <Column header="En camino" class="text-right tabular-nums">
                <template #body="{ data }">{{ Number(data.enCamino) ? cantidad(data.enCamino) : "" }}</template>
              </Column>
              <Column header="Reponer" class="text-right tabular-nums">
                <template #body="{ data }"><strong>{{ Number(data.reponer) ? cantidad(data.reponer) : "" }}</strong></template>
              </Column>
            </DataTable>
          </TabPanel>

          <TabPanel value="libro">
            <LibroAlmacen v-if="almacenId" :key="almacenId" :almacen="almacenId" :articulos="articulosDelLibro" :version="version" />
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>

    <RegistrarDialog :almacen="registrando" :articulos="articulos" :existencias="datos?.filas ?? []" :inicial="inicial" @cerrar="registrando = null" @registrado="alCambiar" />
    <NivelesDialog :almacen="datos?.almacen ?? null" :existencia="nivelando" @cerrar="nivelando = null" @guardado="alCambiar" />

    <Dialog :visible="!!recibiendo" modal header="Recibir el envío" class="w-[min(28rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && (recibiendo = null)">
      <div v-if="recibiendo" class="flex flex-col gap-3">
        <p class="m-0 text-sm">
          Entran {{ cantidad(String(-Number(recibiendo.cantidad))) }} {{ recibiendo.articulo.unidad }} de «{{ recibiendo.articulo.nombre }}» a <strong>{{ recibiendo.destino?.nombre }}</strong>, al costo con que salieron.
        </p>
        <p class="m-0 text-xs text-muted-color">Si llegó de menos o algo roto, recíbalo y registre después una salida o un conteo con el motivo.</p>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Documento o guía (opcional)</span>
          <InputText v-model="documentoRecibo" maxlength="80" fluid />
        </label>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="recibiendo = null" />
        <Button label="Recibir" :loading="recibiendoEnvio" @click="confirmarRecibir" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { useAccesoStore } from "@/core/acceso/store";
import { fetchAlmacenes, fetchArticulos, fetchEnCamino, fetchExistencias, fetchPorReponer, recibir } from "@/core/inventario/api";
import { cantidad, ESTADOS, fecha, nombreAlmacen, quetzales, tiposPermitidos } from "@/core/inventario/reglas";
import type { AlmacenVisible, ArticuloBreve, EnCamino, EstadoExistencia, Existencia, ExistenciasAlmacen, PorReponer, PuedeEnAlmacen, Almacen, TipoRegistrable } from "@/core/inventario/tipos";
import { notify } from "@/core/notify";
import LibroAlmacen from "./LibroAlmacen.vue";
import NivelesDialog from "./NivelesDialog.vue";
import RegistrarDialog from "./RegistrarDialog.vue";

type Pestana = "existencias" | "en-camino" | "por-reponer" | "libro";
const PESTANAS: Pestana[] = ["existencias", "en-camino", "por-reponer", "libro"];
const PESO: Record<EstadoExistencia, number> = { agotado: 0, bajo_minimo: 1, por_reponer: 2, bien: 3 };

const route = useRoute();
const router = useRouter();
const acceso = useAccesoStore();

const almacenes = shallowRef<AlmacenVisible[] | null>(null);
const almacenId = ref<number | null>(Number(route.query.almacen) || null);
const pestana = ref<Pestana>(PESTANAS.includes(route.query.pestana as Pestana) ? (route.query.pestana as Pestana) : "existencias");
const datos = shallowRef<ExistenciasAlmacen | null>(null);
const enCamino = shallowRef<EnCamino[]>([]);
const porReponer = shallowRef<PorReponer[]>([]);
const articulos = shallowRef<ArticuloBreve[]>([]);
const error = ref("");
const cargando = ref(false);
const cargandoExistencias = ref(false);
const version = ref(0);

const registrando = shallowRef<(Almacen & { puede: PuedeEnAlmacen }) | null>(null);
const inicial = shallowRef<{ tipo?: TipoRegistrable; articulo?: number } | null>(null);
const nivelando = shallowRef<Existencia | null>(null);
const recibiendo = shallowRef<EnCamino | null>(null);
const documentoRecibo = ref("");
const recibiendoEnvio = ref(false);

const elegido = computed(() => almacenes.value?.find((a) => a.id === almacenId.value) ?? null);
const variasEmpresas = computed(() => new Set((almacenes.value ?? []).map((a) => a.empresa?.id)).size > 1);
const permitidos = computed(() => (datos.value ? tiposPermitidos(datos.value.almacen.puede, datos.value.almacen.activo) : []));
/** En el libro se filtra también por artículos ya inactivos que tienen existencia aquí. */
const articulosDelLibro = computed(() => {
  const vistos = new Map(articulos.value.map((a) => [a.id, a]));
  for (const e of datos.value?.filas ?? []) if (!vistos.has(e.articulo.id)) vistos.set(e.articulo.id, e.articulo);
  return [...vistos.values()].sort((a, b) => a.nombre.localeCompare(b.nombre));
});

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e));

async function cargarAlmacenes(silencioso = false) {
  try {
    const lista = await fetchAlmacenes(silencioso);
    almacenes.value = lista;
    if (!lista.some((a) => a.id === almacenId.value)) almacenId.value = lista.find((a) => a.activo)?.id ?? lista[0]?.id ?? null;
  } catch (e) {
    if (!silencioso) error.value = mensaje(e);
  }
}

let pedido = 0;
async function cargarExistencias(silencioso = false) {
  const id = almacenId.value;
  if (!id) return void (datos.value = null);
  const mio = ++pedido;
  cargandoExistencias.value = !silencioso;
  try {
    const d = await fetchExistencias(id, silencioso);
    if (mio === pedido) datos.value = d;
  } catch (e) {
    if (mio === pedido) notify.error(mensaje(e));
  } finally {
    if (mio === pedido) cargandoExistencias.value = false;
  }
}

async function cargarTodo(silencioso = false) {
  const [camino, reponer] = await Promise.allSettled([fetchEnCamino(silencioso), fetchPorReponer(silencioso)]);
  if (camino.status === "fulfilled") enCamino.value = camino.value;
  if (reponer.status === "fulfilled") porReponer.value = reponer.value;
}

async function refrescar(silencioso = false) {
  cargando.value = true;
  try {
    await Promise.all([cargarAlmacenes(silencioso), cargarExistencias(silencioso), cargarTodo(silencioso)]);
    version.value++;
  } finally {
    cargando.value = false;
  }
}

async function abrirRegistro(ini: { tipo?: TipoRegistrable; articulo?: number } | null = null) {
  if (!datos.value) return;
  if (!articulos.value.length) {
    try {
      articulos.value = await fetchArticulos();
    } catch (e) {
      return notify.error(mensaje(e));
    }
  }
  inicial.value = ini;
  registrando.value = datos.value.almacen;
}

function alCambiar() {
  registrando.value = null;
  nivelando.value = null;
  void refrescar(true);
}

function abrirRecibir(m: EnCamino) {
  documentoRecibo.value = "";
  recibiendo.value = m;
}

async function confirmarRecibir() {
  if (!recibiendo.value) return;
  recibiendoEnvio.value = true;
  try {
    const m = await recibir(recibiendo.value.id, documentoRecibo.value);
    notify.success(`Recibido en ${m.almacen.nombre}: quedan ${cantidad(m.saldo)} ${m.articulo.unidad} de «${m.articulo.nombre}».`);
    recibiendo.value = null;
    void refrescar(true);
  } catch (e) {
    notify.error(mensaje(e));
  } finally {
    recibiendoEnvio.value = false;
  }
}

function verAlmacen(id: number) {
  almacenId.value = id;
  pestana.value = "existencias";
}

watch(almacenId, (id) => {
  void router.replace({ query: { ...route.query, almacen: id ? String(id) : undefined } });
  datos.value = null;
  void cargarExistencias();
});
watch(pestana, (p) => void router.replace({ query: { ...route.query, pestana: p } }));

onMounted(async () => {
  cargando.value = true;
  await Promise.all([cargarAlmacenes(), cargarTodo(), fetchArticulos().then((a) => (articulos.value = a), () => undefined)]);
  cargando.value = false;
  if (almacenId.value && !datos.value && !cargandoExistencias.value) void cargarExistencias();
});
</script>
