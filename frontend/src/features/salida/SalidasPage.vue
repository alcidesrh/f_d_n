<!--
  Salidas (ADR-024): por defecto sin finalizadas ni anuladas, primero las
  que están en ruta, luego las que abordan y después las programadas de la
  más próxima a la más lejana. Filtros por estado (con conteo), fechas,
  empresa, trayecto y bus. Por fila: editar, anular (queda como cancelada)
  y eliminar, con la opción de propagarlo a las futuras idénticas. Ver abre el
  croquis con la ocupación y el resumen de venta; el menú PDF genera el
  manifiesto interno y el del piloto.
  Contenido angosto: tarjetas; desde 56rem de ancho del contenido: tabla.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <Toolbar>
      <template #start><PageHead /></template>
      <template #end>
        <RouterLink v-if="opciones?.puede.crear" :to="{ name: 'salidas-programar' }" class="no-underline">
          <Button label="Programar salidas" size="small"><template #icon><icon name="calendar-plus" class="mr-1" /></template></Button>
        </RouterLink>
      </template>
    </Toolbar>

    <section class="panel flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-muted-color">Estado:</span>
        <button
          v-for="e in ESTADOS"
          :key="e.valor"
          type="button"
          class="rounded-full border px-2.5 py-1 text-xs font-medium"
          :class="filtro.estados.includes(e.valor) ? 'border-primary bg-primary text-primary-contrast' : 'border-surface-300 bg-transparent text-color'"
          :aria-pressed="filtro.estados.includes(e.valor)"
          @click="alternarEstado(e.valor)"
        >
          {{ e.etiqueta }}<span v-if="datos?.porEstado[e.valor]" class="ml-1 opacity-75">{{ datos.porEstado[e.valor] }}</span>
        </button>
      </div>
      <div class="grid grid-cols-2 gap-3 @3xl:grid-cols-[1fr_1fr_minmax(0,1.4fr)_auto] @3xl:items-end">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Desde</span>
          <DatePicker v-model="desde" date-format="dd/mm/yy" show-icon show-button-bar fluid />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Hasta</span>
          <DatePicker v-model="hasta" date-format="dd/mm/yy" show-icon show-button-bar fluid :min-date="desde ?? undefined" />
        </label>
        <label class="col-span-2 flex flex-col gap-1 @3xl:col-span-1">
          <span class="text-xs font-medium text-muted-color">Trayecto</span>
          <Select v-model="filtro.trayecto" :options="opciones?.trayectos ?? []" option-label="ruta" option-value="id" filter show-clear fluid :virtual-scroller-options="{ itemSize: 38 }" placeholder="Todos" />
        </label>
        <Button severity="secondary" outlined class="col-span-2 @3xl:col-span-1" @click="masFiltros = true">
          <icon name="filter" class="mr-1" />Más filtros
          <Badge v-if="otrosActivos" :value="otrosActivos" class="ml-1.5" />
        </Button>
      </div>
      <div v-if="estaModificado" class="flex justify-end"><Button label="Limpiar filtros" size="small" text @click="limpiar" /></div>
    </section>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Angosto: tarjetas -->
    <div class="flex flex-col gap-3 @4xl:hidden">
      <Skeleton v-if="cargando && !datos" height="8rem" />
      <p v-else-if="datos && !datos.items.length" class="panel m-0 text-center text-sm text-muted-color">No hay salidas con estos filtros.</p>
      <article v-for="s in datos?.items ?? []" :key="s.id" class="panel !p-3 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="font-semibold">{{ fechaHora(s.fecha) }}</div>
            <div class="text-sm">{{ s.trayecto.ruta }}</div>
            <div class="text-xs text-muted-color">
              <template v-if="s.bus">Bus {{ s.bus.codigo }}</template><template v-else>Sin bus</template>
              <template v-if="s.empresa"> · {{ s.empresa.nombre }}</template> · #{{ s.id }}
            </div>
          </div>
          <div class="flex flex-col items-end gap-1">
            <Tag :value="etiquetaEstado(s.estado).etiqueta" :severity="etiquetaEstado(s.estado).severidad" />
            <Tag v-if="s.atrasada" value="Atrasada" severity="warn" class="!text-xs" />
            <span class="text-xs tabular-nums text-muted-color">{{ ocupacion(s) }}</span>
          </div>
        </div>
        <div class="flex justify-end gap-1">
          <Button severity="secondary" text rounded size="small" class="tap-target" aria-label="Ver" v-tooltip.top="'Ver croquis y resumen'" @click="verSalida = s">
            <template #icon><icon name="eye" /></template>
          </Button>
          <Button severity="secondary" text rounded size="small" class="tap-target" aria-label="Manifiestos" v-tooltip.top="'Manifiestos (PDF)'" aria-haspopup="menu" @click="abrirMenu($event, s)">
            <template #icon><icon name="file-type-pdf" /></template>
          </Button>
          <Button v-for="a in acciones(s)" :key="a.op" :severity="a.severidad" text rounded size="small" class="tap-target" :disabled="!a.habilitada" :aria-label="a.etiqueta" v-tooltip.top="a.ayuda" @click="abrir(a.op, s)">
            <template #icon><icon :name="a.icono" /></template>
          </Button>
        </div>
      </article>
    </div>

    <!-- Ancho: tabla -->
    <div class="hidden @4xl:block">
      <DataTable
        :value="datos?.items ?? []"
        data-key="id"
        lazy
        paginator
        :rows="filtro.porPagina"
        :first="(filtro.pagina - 1) * filtro.porPagina"
        :total-records="datos?.total ?? 0"
        :rows-per-page-options="[25, 50, 100]"
        :loading="cargando"
        size="small"
        striped-rows
        @page="alPaginar"
      >
        <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">No hay salidas con estos filtros.</p></template>
        <Column header="Salida">
          <template #body="{ data }">
            <div class="whitespace-nowrap font-medium">{{ fechaHora(data.fecha) }}</div>
            <div class="text-xs text-muted-color">#{{ data.id }}</div>
          </template>
        </Column>
        <Column header="Trayecto">
          <template #body="{ data }">{{ data.trayecto.ruta }}</template>
        </Column>
        <Column header="Bus">
          <template #body="{ data }">
            <template v-if="data.bus"><div>{{ data.bus.codigo }}</div><div class="text-xs text-muted-color">{{ data.bus.matricula }}</div></template>
            <span v-else class="text-muted-color">—</span>
          </template>
        </Column>
        <Column header="Empresa">
          <template #body="{ data }"><span class="text-sm">{{ data.empresa?.nombre ?? '—' }}</span></template>
        </Column>
        <Column header="Vendidos" class="text-right">
          <template #body="{ data }"><span class="whitespace-nowrap tabular-nums">{{ ocupacion(data) }}</span></template>
        </Column>
        <Column header="Estado">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Tag :value="etiquetaEstado(data.estado).etiqueta" :severity="etiquetaEstado(data.estado).severidad" />
              <Tag v-if="data.atrasada" value="Atrasada" severity="warn" />
            </div>
          </template>
        </Column>
        <Column header="" class="w-0">
          <template #body="{ data }">
            <div class="flex justify-end gap-0.5">
              <Button severity="secondary" text rounded size="small" aria-label="Ver" v-tooltip.top="'Ver croquis y resumen'" @click="verSalida = data">
                <template #icon><icon name="eye" /></template>
              </Button>
              <Button severity="secondary" text rounded size="small" aria-label="Manifiestos" v-tooltip.top="'Manifiestos (PDF)'" aria-haspopup="menu" @click="abrirMenu($event, data)">
                <template #icon><icon name="file-type-pdf" /></template>
              </Button>
              <Button v-for="a in acciones(data)" :key="a.op" :severity="a.severidad" text rounded size="small" :disabled="!a.habilitada" :aria-label="a.etiqueta" v-tooltip.top="a.ayuda" @click="abrir(a.op, data)">
                <template #icon><icon :name="a.icono" /></template>
              </Button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Paginator
      v-if="datos && datos.total > filtro.porPagina"
      class="@4xl:hidden"
      :rows="filtro.porPagina"
      :first="(filtro.pagina - 1) * filtro.porPagina"
      :total-records="datos.total"
      template="PrevPageLink CurrentPageReport NextPageLink"
      current-page-report-template="{currentPage} / {totalPages}"
      @page="alPaginar"
    />

    <Drawer v-model:visible="masFiltros" position="right" header="Más filtros" class="!w-[min(26rem,100vw)]">
      <div class="flex flex-col gap-4">
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Empresa</span><Select v-model="filtro.empresa" :options="opciones?.empresas ?? []" option-label="nombre" option-value="id" show-clear filter fluid placeholder="Todas" /></label>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Bus</span><Select v-model="filtro.bus" :options="opciones?.buses ?? []" option-label="codigo" option-value="id" :filter-fields="['codigo', 'matricula']" show-clear filter fluid placeholder="Todos" /></label>
        <div class="flex flex-col gap-1">
          <span class="text-xs font-medium">Orden</span>
          <SelectButton v-model="orden" :options="ORDENES" option-label="l" option-value="v" :allow-empty="false" class="flex-wrap" />
        </div>
        <div class="flex gap-2">
          <Button label="Limpiar" severity="secondary" outlined class="flex-1" @click="limpiarAvanzados" />
          <Button label="Ver resultados" class="flex-1" @click="masFiltros = false" />
        </div>
      </div>
    </Drawer>

    <Menu ref="menuManifiestos" :model="itemsManifiestos" popup />
    <VerSalidaDialog :salida="verSalida" @cerrar="verSalida = null" />
    <EditarSalidaDialog v-if="opciones" :salida="operacion === 'editar' ? seleccion : null" :opciones="opciones" @cerrar="seleccion = null" @hecho="alTerminar" />
    <ConfirmarOperacionDialog :salida="operacion !== 'editar' ? seleccion : null" :operacion="operacion === 'eliminar' ? 'eliminar' : 'anular'" @cerrar="seleccion = null" @hecho="alTerminar" />
    <ResultadoOperacionDialog :resultado="resultado" :operacion="operacion" @cerrar="resultado = null" />
  </div>
</template>

<script setup lang="ts">
import { fetchOpciones, fetchSalidas } from '@/core/salida/api'
import { aDia, aQuery, deDia, ESTADOS, etiquetaEstado, filtroInicial, type FiltroSalidas } from '@/core/salida/filtro'
import { resumen, type Operacion } from '@/core/salida/resultado'
import type { EstadoSalida, OpcionesSalidas, PaginaSalidas, ResultadoOperacion, SalidaFila } from '@/core/salida/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import ConfirmarOperacionDialog from './ConfirmarOperacionDialog.vue'
import EditarSalidaDialog from './EditarSalidaDialog.vue'
import ResultadoOperacionDialog from './ResultadoOperacionDialog.vue'
import VerSalidaDialog from './VerSalidaDialog.vue'
import { abrirManifiesto, MANIFIESTOS } from './manifiesto'

const ORDENES = [
  { v: 'proximas', l: 'Próximas primero' },
  { v: 'fecha-asc', l: 'Fecha ↑' },
  { v: 'fecha-desc', l: 'Fecha ↓' },
]

const filtro = reactive<FiltroSalidas>(filtroInicial())
const datos = shallowRef<PaginaSalidas | null>(null)
const opciones = shallowRef<OpcionesSalidas | null>(null)
const cargando = ref(false)
const error = ref('')
const masFiltros = ref(false)
const seleccion = ref<SalidaFila | null>(null)
const operacion = ref<Operacion>('editar')
const resultado = shallowRef<ResultadoOperacion | null>(null)
const verSalida = ref<SalidaFila | null>(null)
const menuManifiestos = ref<{ toggle: (e: Event) => void } | null>(null)
const itemsManifiestos = ref<Array<{ label: string; icon?: string; command: () => void }>>([])

const estaModificado = computed(() => aQuery({ ...filtro, pagina: 1 }) !== aQuery(filtroInicial()))
const otrosActivos = computed(() => [filtro.empresa, filtro.bus, filtro.orden !== 'proximas' ? 1 : null].filter((v) => v !== null).length)

const comoFecha = (clave: 'desde' | 'hasta') =>
  computed<Date | null>({ get: () => (filtro[clave] ? deDia(filtro[clave]) : null), set: (d) => (filtro[clave] = d ? aDia(d) : null) })
const desde = comoFecha('desde')
const hasta = comoFecha('hasta')
const orden = computed({
  get: () => (filtro.orden === 'proximas' ? 'proximas' : `fecha-${filtro.direccion}`),
  set: (v: string) => {
    filtro.orden = v === 'proximas' ? 'proximas' : 'fecha'
    filtro.direccion = v === 'fecha-desc' ? 'desc' : 'asc'
  },
})

const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
const ocupacion = (s: SalidaFila) => (s.capacidad ? `${s.vendidos} / ${s.capacidad}` : String(s.vendidos))

/** Acciones de la fila: habilitadas según el estado de la salida y los permisos del usuario. */
function acciones(s: SalidaFila) {
  const p = opciones.value?.puede
  const programada = s.estado === 'programada'
  return [
    { op: 'editar' as const, icono: 'pencil', etiqueta: 'Editar', severidad: 'secondary' as const, habilitada: !!p?.editar && programada, ayuda: programada ? 'Editar' : 'Solo se editan salidas programadas' },
    { op: 'anular' as const, icono: 'ban', etiqueta: 'Anular', severidad: 'warn' as const, habilitada: !!p?.anular && programada, ayuda: programada ? 'Anular (se conserva como cancelada)' : 'Solo se anulan salidas programadas' },
    {
      op: 'eliminar' as const,
      icono: 'trash',
      etiqueta: 'Eliminar',
      severidad: 'danger' as const,
      habilitada: !!p?.eliminar && (programada || s.estado === 'cancelada'),
      ayuda: programada || s.estado === 'cancelada' ? 'Eliminar' : 'Una salida en curso o finalizada no se elimina',
    },
  ]
}

/** Menú de los dos manifiestos (PDF) de la fila. */
function abrirMenu(e: Event, s: SalidaFila) {
  itemsManifiestos.value = MANIFIESTOS.map((m) => ({ label: m.etiqueta, command: () => void abrirManifiesto(s.id, m.tipo) }))
  menuManifiestos.value?.toggle(e)
}

function abrir(op: Operacion, s: SalidaFila) {
  operacion.value = op
  seleccion.value = s
}

function alTerminar(r: ResultadoOperacion) {
  seleccion.value = null
  const { texto, severidad } = resumen(r, operacion.value)
  notify[severidad](texto)
  if (r.omitidas.length) resultado.value = r
  void cargar()
}

function alternarEstado(e: EstadoSalida) {
  filtro.estados = filtro.estados.includes(e) ? filtro.estados.filter((x) => x !== e) : [...filtro.estados, e]
}

function limpiarAvanzados() {
  Object.assign(filtro, { empresa: null, bus: null, orden: 'proximas', direccion: 'asc' })
}

function limpiar() {
  Object.assign(filtro, filtroInicial())
}

function alPaginar(e: { page: number; rows: number }) {
  filtro.pagina = e.page + 1
  filtro.porPagina = e.rows
}

// Cualquier cambio de filtro (salvo la página) vuelve a la primera página.
watch(
  () => aQuery({ ...filtro, pagina: 1 }),
  () => (filtro.pagina = 1),
)

let pedido = 0
async function cargar() {
  const n = ++pedido
  cargando.value = true
  error.value = ''
  try {
    const r = await fetchSalidas(aQuery(filtro))
    if (n === pedido) datos.value = r
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver las salidas (salida.ver).' : e instanceof Error ? e.message : String(e)
  } finally {
    if (n === pedido) cargando.value = false
  }
}
watch(() => aQuery(filtro), cargar, { immediate: true })

onMounted(async () => {
  opciones.value = await fetchOpciones().catch(() => null)
})
</script>
