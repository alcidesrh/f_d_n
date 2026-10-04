<!--
  Compras de la página web: por defecto las completadas, las más recientes
  primero. Filtros rápidos (fecha de compra, estado, búsqueda) y "Más
  filtros" (salida, empresa, origen/destino, ida y vuelta, factura, tarjeta,
  monto). La calculadora suma TODO lo filtrado (no solo la página visible);
  marcando filas se suma además la selección.
  Contenido angosto (móvil o con los paneles del shell abiertos): tarjetas;
  desde 56rem de ancho del contenido (container query): tabla.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <!-- Filtros rápidos -->
    <section class="panel flex flex-col gap-3">
      <div class="flex flex-wrap gap-1.5">
        <Button
          v-for="r in RANGOS"
          :key="r.valor"
          :label="r.etiqueta"
          size="small"
          :severity="rangoActivo === r.valor ? undefined : 'secondary'"
          :outlined="rangoActivo !== r.valor"
          rounded
          @click="ponerRango(r.valor)"
        />
      </div>
      <div class="grid grid-cols-2 gap-3 @3xl:grid-cols-[1fr_1fr_minmax(0,1.4fr)_auto] @3xl:items-end">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">Compradas desde</span>
          <DatePicker v-model="creadoDesde" date-format="dd/mm/yy" show-icon show-button-bar fluid :max-date="hoy" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-medium text-muted-color">hasta</span>
          <DatePicker v-model="creadoHasta" date-format="dd/mm/yy" show-icon show-button-bar fluid :max-date="hoy" />
        </label>
        <label class="col-span-2 flex flex-col gap-1 @3xl:col-span-1">
          <span class="text-xs font-medium text-muted-color">Buscar</span>
          <IconField>
            <InputIcon><icon name="search" /></InputIcon>
            <InputText v-model="texto" placeholder="Comprador, correo, NIT, últimos 4, autorización, n.º de venta" fluid />
          </IconField>
        </label>
        <Button severity="secondary" outlined class="col-span-2 @3xl:col-span-1" @click="masFiltros = true">
          <icon name="filter" class="mr-1" />Más filtros
          <Badge v-if="activos" :value="activos" class="ml-1.5" />
        </Button>
      </div>
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
          {{ e.etiqueta }}
        </button>
        <Button v-if="estaModificado" label="Limpiar filtros" size="small" text @click="limpiar" />
      </div>
    </section>

    <!-- Calculadora -->
    <section v-if="datos" class="grid grid-cols-2 gap-3 @2xl:grid-cols-3 @5xl:grid-cols-6" aria-label="Totales de lo filtrado">
      <div v-for="k in kpis" :key="k.etiqueta" class="panel !p-3">
        <div class="text-xs text-muted-color">{{ k.etiqueta }}</div>
        <div class="text-lg font-bold tabular-nums">{{ k.valor }}</div>
        <div v-if="k.nota" class="text-xs text-muted-color">{{ k.nota }}</div>
      </div>
    </section>
    <section v-if="datos && (datos.resumen.porEmpresa.length > 1 || datos.resumen.porEstado.length > 1)" class="grid gap-3 @2xl:grid-cols-2">
      <div v-if="datos.resumen.porEmpresa.length" class="panel !p-3">
        <h3 class="m-0 mb-2 text-sm font-semibold">Cobrado por empresa (comercio)</h3>
        <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
          <li v-for="r in datos.resumen.porEmpresa" :key="r.empresa" class="flex justify-between gap-2"><span>{{ r.empresa }} <span class="text-muted-color">· {{ r.compras }}</span></span><span class="tabular-nums">{{ r.monto.texto }}</span></li>
        </ul>
      </div>
      <div v-if="datos.resumen.porEstado.length > 1" class="panel !p-3">
        <h3 class="m-0 mb-2 text-sm font-semibold">Por estado</h3>
        <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
          <li v-for="r in datos.resumen.porEstado" :key="r.estado" class="flex items-center justify-between gap-2">
            <span><Tag :value="etiquetaEstado(r.estado).etiqueta" :severity="etiquetaEstado(r.estado).severidad" class="!text-xs" /> <span class="text-muted-color">· {{ r.compras }}</span></span>
            <span class="tabular-nums">{{ r.monto.texto }}</span>
          </li>
        </ul>
      </div>
    </section>

    <Message v-if="seleccion.length" severity="info" :closable="false">
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
        <span><b>Selección:</b> {{ suma.compras }} compra(s) · {{ suma.asientos }} asiento(s) · <b class="tabular-nums">{{ quetzales(suma.centavos) }}</b></span>
        <Button label="Quitar selección" size="small" text @click="seleccion = []" />
      </div>
    </Message>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Angosto: tarjetas -->
    <div class="flex flex-col gap-3 @4xl:hidden">
      <Skeleton v-if="cargando && !datos" height="8rem" />
      <p v-else-if="datos && !datos.items.length" class="panel m-0 text-center text-sm text-muted-color">No hay compras con estos filtros.</p>
      <article v-for="c in datos?.items ?? []" :key="c.id" class="panel !p-3 flex flex-col gap-2">
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-start gap-2">
            <Checkbox v-model="seleccion" :value="c" :input-id="`sel-m-${c.id}`" />
            <label :for="`sel-m-${c.id}`">
              <span class="block font-semibold">{{ c.comprador.nombre || '—' }}</span>
              <span class="block text-xs text-muted-color">{{ fechaHora(c.creado) }} · NIT {{ c.comprador.nit }}</span>
            </label>
          </div>
          <div class="text-right">
            <div class="font-bold tabular-nums">{{ c.monto.texto }}</div>
            <Tag :value="etiquetaEstado(c.estado).etiqueta" :severity="etiquetaEstado(c.estado).severidad" class="!text-xs" />
          </div>
        </div>
        <ViajesCompra :compra="c" />
        <Button label="Detalle" size="small" text class="self-start !px-0" @click="detalle = c" />
      </article>
    </div>

    <!-- Ancho: tabla (envuelta: DataTable impone su propio display) -->
    <div class="hidden @4xl:block">
    <DataTable
      v-model:selection="seleccion"
      :value="datos?.items ?? []"
      data-key="id"
      lazy
      paginator
      :rows="filtro.porPagina"
      :first="(filtro.pagina - 1) * filtro.porPagina"
      :total-records="datos?.total ?? 0"
      :rows-per-page-options="[25, 50, 100]"
      :loading="cargando"
      :sort-field="filtro.orden"
      :sort-order="filtro.direccion === 'asc' ? 1 : -1"
      removable-sort
      size="small"
      striped-rows
      @page="alPaginar"
      @sort="alOrdenar"
      @row-click="detalle = $event.data"
    >
      <template #empty><p class="m-0 py-6 text-center text-sm text-muted-color">No hay compras con estos filtros.</p></template>
      <Column selection-mode="multiple" header-style="width: 3rem" />
      <Column field="creado" header="Compra" sortable>
        <template #body="{ data }"><span class="whitespace-nowrap">{{ fechaHora(data.creado) }}</span></template>
      </Column>
      <Column header="Comprador">
        <template #body="{ data }">
          <div class="font-medium">{{ data.comprador.nombre || '—' }}</div>
          <div class="text-xs text-muted-color">{{ data.comprador.email }} · NIT {{ data.comprador.nit }}</div>
        </template>
      </Column>
      <Column field="salida" header="Viaje(s)" sortable>
        <template #body="{ data }"><ViajesCompra :compra="data" /></template>
      </Column>
      <Column header="Cobra">
        <template #body="{ data }"><span class="text-sm">{{ data.empresa?.nombre ?? '—' }}</span></template>
      </Column>
      <Column field="monto" header="Monto" sortable class="text-right">
        <template #body="{ data }">
          <div class="whitespace-nowrap font-semibold tabular-nums">{{ data.monto.texto }}</div>
          <div v-if="Number(data.recargoPorciento) > 0" class="text-xs text-muted-color">+{{ Number(data.recargoPorciento) }} %</div>
        </template>
      </Column>
      <Column header="Estado">
        <template #body="{ data }"><Tag :value="etiquetaEstado(data.estado).etiqueta" :severity="etiquetaEstado(data.estado).severidad" /></template>
      </Column>
      <Column header="Tarjeta">
        <template #body="{ data }">
          <div class="whitespace-nowrap text-sm">{{ data.tarjeta.marca }} ···· {{ data.tarjeta.ultimos4 }}</div>
          <div v-if="data.autorizacion" class="text-xs text-muted-color">Aut. {{ data.autorizacion }}</div>
        </template>
      </Column>
    </DataTable>
    </div>

    <!-- Paginación en móvil -->
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
        <div class="grid grid-cols-2 gap-3">
          <label class="flex flex-col gap-1"><span class="text-xs font-medium">Salida desde</span><DatePicker v-model="salidaDesde" date-format="dd/mm/yy" show-button-bar fluid /></label>
          <label class="flex flex-col gap-1"><span class="text-xs font-medium">Salida hasta</span><DatePicker v-model="salidaHasta" date-format="dd/mm/yy" show-button-bar fluid /></label>
        </div>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Empresa del viaje</span><Select v-model="filtro.empresa" :options="opciones.empresas" option-label="nombre" option-value="id" show-clear filter fluid placeholder="Todas" /></label>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Origen</span><estacion-select v-model="filtro.origen" :estaciones="opciones.estaciones" :propio="opciones.departamento" placeholder="Cualquiera" /></label>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Destino</span><estacion-select v-model="filtro.destino" :estaciones="opciones.estaciones" :propio="opciones.departamento" placeholder="Cualquiera" /></label>
        <div class="flex flex-col gap-1">
          <span class="text-xs font-medium">Ida y vuelta</span>
          <SelectButton v-model="filtro.idaVuelta" :options="[{ v: 'todos', l: 'Todas' }, { v: 'si', l: 'Sí' }, { v: 'no', l: 'Solo ida' }]" option-label="l" option-value="v" :allow-empty="false" />
        </div>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Factura</span><Select v-model="filtro.facturacion" :options="[{ v: 'certificada', l: 'Certificada' }, { v: 'pendiente', l: 'Pendiente' }, { v: 'no_aplica', l: 'No aplica' }]" option-label="l" option-value="v" show-clear fluid placeholder="Cualquiera" /></label>
        <label class="flex flex-col gap-1"><span class="text-xs font-medium">Tarjeta</span><Select v-model="filtro.marca" :options="[{ v: 'visa', l: 'Visa' }, { v: 'mastercard', l: 'Mastercard' }]" option-label="l" option-value="v" show-clear fluid placeholder="Cualquiera" /></label>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex flex-col gap-1"><span class="text-xs font-medium">Monto mínimo (Q)</span><InputNumber v-model="filtro.montoMinimo" :min="0" :max-fraction-digits="2" fluid /></label>
          <label class="flex flex-col gap-1"><span class="text-xs font-medium">Monto máximo (Q)</span><InputNumber v-model="filtro.montoMaximo" :min="0" :max-fraction-digits="2" fluid /></label>
        </div>
        <div class="flex gap-2">
          <Button label="Limpiar" severity="secondary" outlined class="flex-1" @click="limpiarAvanzados" />
          <Button label="Ver resultados" class="flex-1" @click="masFiltros = false" />
        </div>
      </div>
    </Drawer>

    <Dialog :visible="!!detalle" modal :header="detalle ? `Compra #${detalle.id}` : ''" class="w-[min(40rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && (detalle = null)">
      <DetalleCompra v-if="detalle" :compra="detalle" />
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { fetchCompras, fetchOpciones } from '@/core/pagina-web/api'
import { aQuery, ESTADOS, etiquetaEstado, filtroInicial, filtrosActivos, quetzales, RANGOS, rango, sumaSeleccion, type FiltroCompras, type Rango } from '@/core/pagina-web/filtro'
import type { CompraWeb, EstadoPago, Opcion, PaginaCompras } from '@/core/pagina-web/types'
import { HttpError } from '@/core/http'
import DetalleCompra from './DetalleCompra.vue'
import ViajesCompra from './ViajesCompra.vue'

const hoy = new Date()
const filtro = reactive<FiltroCompras>(filtroInicial())
const datos = shallowRef<PaginaCompras | null>(null)
const cargando = ref(false)
const error = ref('')
const masFiltros = ref(false)
const detalle = ref<CompraWeb | null>(null)
const seleccion = ref<CompraWeb[]>([])
const rangoActivo = ref<Rango | null>('todo')
const texto = ref('')
const opciones = reactive<{ empresas: Opcion[]; estaciones: Array<Opcion & { departamento: string | null }>; departamento: string | null }>({ empresas: [], estaciones: [], departamento: null })

const activos = computed(() => filtrosActivos(filtro))
const suma = computed(() => sumaSeleccion(seleccion.value))
const estaModificado = computed(() => aQuery({ ...filtro, pagina: 1 }) !== aQuery(filtroInicial()))

const kpis = computed(() => {
  const r = datos.value?.resumen
  if (!r) return []
  return [
    { etiqueta: 'Compras', valor: String(r.compras), nota: r.compras !== r.completadas ? `${r.completadas} completadas` : r.idaVuelta ? `${r.idaVuelta} ida y vuelta` : '' },
    { etiqueta: 'Total cobrado', valor: r.cobrado.texto, nota: 'compras completadas' },
    { etiqueta: 'Asientos', valor: String(r.asientos), nota: '' },
    { etiqueta: 'Promedio por compra', valor: r.promedioCompra.texto, nota: '' },
    { etiqueta: 'Promedio por asiento', valor: r.promedioAsiento.texto, nota: '' },
    { etiqueta: 'Recargo web incluido', valor: r.recargo.texto, nota: 'sobre la tarifa' },
  ]
})

/** Fechas del filtro como `Date` para el DatePicker. */
const comoFecha = (clave: 'creadoDesde' | 'creadoHasta' | 'salidaDesde' | 'salidaHasta') =>
  computed<Date | null>({
    get: () => {
      const v = filtro[clave]
      if (!v) return null
      const [a, m, d] = v.split('-').map(Number)
      return new Date(a!, m! - 1, d)
    },
    set: (d) => {
      filtro[clave] = d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : null
      if (clave.startsWith('creado')) rangoActivo.value = null
    },
  })
const creadoDesde = comoFecha('creadoDesde')
const creadoHasta = comoFecha('creadoHasta')
const salidaDesde = comoFecha('salidaDesde')
const salidaHasta = comoFecha('salidaHasta')

const fechaHora = (iso: string) =>
  new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

function ponerRango(r: Rango) {
  const { desde, hasta } = rango(r)
  filtro.creadoDesde = desde
  filtro.creadoHasta = hasta
  rangoActivo.value = r
}

function alternarEstado(e: EstadoPago) {
  filtro.estados = filtro.estados.includes(e) ? filtro.estados.filter((x) => x !== e) : [...filtro.estados, e]
}

function limpiarAvanzados() {
  const base = filtroInicial()
  Object.assign(filtro, {
    salidaDesde: base.salidaDesde,
    salidaHasta: base.salidaHasta,
    empresa: base.empresa,
    origen: base.origen,
    destino: base.destino,
    idaVuelta: base.idaVuelta,
    facturacion: base.facturacion,
    marca: base.marca,
    montoMinimo: base.montoMinimo,
    montoMaximo: base.montoMaximo,
  })
}

function limpiar() {
  Object.assign(filtro, filtroInicial())
  texto.value = ''
  rangoActivo.value = 'todo'
}

function alPaginar(e: { page: number; rows: number }) {
  filtro.pagina = e.page + 1
  filtro.porPagina = e.rows
}

function alOrdenar(e: { sortField?: unknown; sortOrder?: number | null }) {
  const campo = typeof e.sortField === 'string' && ['creado', 'monto', 'salida'].includes(e.sortField) ? (e.sortField as FiltroCompras['orden']) : 'creado'
  filtro.orden = campo
  filtro.direccion = e.sortOrder === 1 ? 'asc' : 'desc'
}

let espera: ReturnType<typeof setTimeout> | null = null
watch(texto, (v) => {
  if (espera) clearTimeout(espera)
  espera = setTimeout(() => (filtro.texto = v), 350)
})

// Cualquier cambio de filtro (salvo la página) vuelve a la primera página.
watch(
  () => aQuery({ ...filtro, pagina: 1 }),
  () => {
    filtro.pagina = 1
  },
)

let pedido = 0
watch(
  () => aQuery(filtro),
  async (query) => {
    const n = ++pedido
    cargando.value = true
    error.value = ''
    try {
      const r = await fetchCompras(query)
      if (n !== pedido) return
      datos.value = r
      // La selección sigue solo con las compras que siguen a la vista.
      const ids = new Set(r.items.map((c) => c.id))
      seleccion.value = seleccion.value.filter((c) => ids.has(c.id))
    } catch (e) {
      if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver las compras de la página (pagina.administrar).' : e instanceof Error ? e.message : String(e)
    } finally {
      if (n === pedido) cargando.value = false
    }
  },
  { immediate: true },
)

onMounted(async () => {
  const o = await fetchOpciones().catch(() => null)
  if (o) Object.assign(opciones, o)
})
</script>
