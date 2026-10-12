<!--
  Depósitos de agencia (el "Buscar Depósito" del legado, ADR-034): filtros
  arriba (se aplican con «Filtrar»), barra de acciones sobre el depósito
  elegido (Registrar, Acreditar, Rechazar) y la lista paginada en el servidor.
  Un usuario de agencia solo ve los suyos y no acredita. `?id=` filtra por ese
  depósito y lo elige. Contenido angosto: tarjetas; filtros en una columna.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <section class="panel !p-0">
      <button type="button" class="filtros-head" :aria-expanded="filtrosAbiertos" aria-controls="filtros-depositos" @click="filtrosAbiertos = !filtrosAbiertos">
        <icon name="filter-alt-outline" />
        <span class="font-medium">Filtrar resultados</span>
        <Badge v-if="activos" :value="activos" />
        <span class="ml-auto truncate text-xs text-muted-color">{{ rangoTexto }}</span>
        <icon :name="filtrosAbiertos ? 'expand-less' : 'expand-more'" />
      </button>
      <form v-show="filtrosAbiertos" id="filtros-depositos" class="filtros" @submit.prevent="aplicar">
        <label class="campo">
          <span>Id</span>
          <InputNumber v-model="borrador.id" :use-grouping="false" :min="1" placeholder="Identificador" fluid />
        </label>
        <label class="campo @3xl:col-span-2">
          <span>Rango de fechas <span class="font-normal">(del depósito)</span></span>
          <DatePicker v-model="rango" selection-mode="range" :manual-input="false" date-format="dd/mm/yy" show-icon show-button-bar :placeholder="`Por defecto ${rangoPorDefecto}`" fluid />
        </label>
        <label v-if="opciones?.agenciaPropia === null" class="campo">
          <span>Agencia</span>
          <Select v-model="borrador.agencia" :options="opciones?.agencias ?? []" option-label="nombre" option-value="id" filter show-clear placeholder="Todas" fluid />
        </label>
        <label class="campo">
          <span>Número de boleta</span>
          <InputText v-model="borrador.boleta" placeholder="Contiene" fluid />
        </label>
        <label class="campo">
          <span>Estado</span>
          <MultiSelect v-model="borrador.estados" :options="ESTADOS_DEPOSITO" option-label="etiqueta" option-value="valor" display="chip" placeholder="Todos" fluid />
        </label>
        <div class="filtros-botones">
          <Button type="button" label="Limpiar" severity="secondary" text :disabled="!borradorModificado" @click="limpiar" />
          <Button type="submit" label="Filtrar"><template #icon><icon name="filter-alt" class="mr-1" /></template></Button>
        </div>
      </form>
    </section>

    <div class="acciones panel" role="toolbar" aria-label="Acciones del depósito elegido">
      <div class="acciones-elegido">
        <template v-if="elegido">
          <span class="font-semibold">#{{ elegido.id }}</span>
          <span class="truncate text-sm text-muted-color">{{ elegido.agencia.nombre }} · boleta {{ elegido.numeroBoleta }} · {{ elegido.importe.texto }}</span>
          <Button text rounded size="small" severity="secondary" class="tap-target" aria-label="Quitar selección" v-tooltip.top="'Quitar selección'" @click="elegido = null"><template #icon><icon name="close" /></template></Button>
        </template>
        <span v-else class="text-sm text-muted-color">Elija un depósito de la lista</span>
      </div>
      <div class="acciones-botones">
        <Button size="small" :disabled="!opciones" aria-label="Registrar depósito" @click="dialogo = 'registrar'">
          <icon name="add" color="text-inherit" />
          <span class="hidden @3xl:inline">Registrar</span>
        </Button>
        <template v-for="a in BOTONES" :key="a.accion">
          <span v-if="estadoAcciones[a.accion].visible" v-tooltip.bottom="estadoAcciones[a.accion].motivo ?? a.ayuda">
            <Button :severity="a.severidad" outlined size="small" :disabled="!estadoAcciones[a.accion].habilitada" :aria-label="a.etiqueta" @click="dialogo = a.accion">
              <icon :name="a.icono" color="text-inherit" />
              <span class="hidden @3xl:inline">{{ a.etiqueta }}</span>
            </Button>
          </span>
        </template>
      </div>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataGrid
      :columns="COLUMNAS"
      :rows="datos?.items ?? []"
      :row-key="(r) => String((r as DepositoFila).id)"
      :loading="cargando"
      :selected-keys="seleccion"
      density="compact"
      :card-fields="4"
      max-height="calc(100dvh - 14rem)"
      row-clickable
      :row-class="claseFila"
      @row-click="elegir($event as DepositoFila)"
    >
      <template #cell="{ row, column }">
        <template v-if="column.key === 'estado'"><Tag :value="etiquetaEstadoDeposito(f(row).estado).etiqueta" :severity="etiquetaEstadoDeposito(f(row).estado).severidad" class="!text-xs" /></template>
        <GridText v-else :text="texto(f(row), column.key)" />
      </template>
      <template #card="{ row }">
        <div class="tarjeta">
          <div class="flex items-center justify-between gap-2">
            <span class="truncate font-semibold">{{ f(row).agencia.nombre }}</span>
            <Tag :value="etiquetaEstadoDeposito(f(row).estado).etiqueta" :severity="etiquetaEstadoDeposito(f(row).estado).severidad" class="!text-xs" />
          </div>
          <div class="flex justify-between gap-2 text-sm">
            <span class="truncate">Boleta {{ f(row).numeroBoleta }} · {{ texto(f(row), 'fecha') }}</span>
            <span class="shrink-0 font-medium tabular-nums">{{ f(row).importe.texto }}</span>
          </div>
          <div class="truncate text-xs text-muted-color">{{ texto(f(row), 'bono') }} de bono · {{ f(row).creadoPor ?? '—' }}</div>
          <div v-if="f(row).motivoRechazo" class="truncate text-xs text-red-600 dark:text-red-400">{{ f(row).motivoRechazo }}</div>
        </div>
      </template>
      <template #empty>
        <icon name="savings-outline" size="2rem" />
        <span>No hay depósitos con estos filtros.</span>
      </template>
    </DataGrid>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <Paginator v-if="datos" :rows="porPagina" :first="(pagina - 1) * porPagina" :total-records="datos.total" :rows-per-page-options="opciones?.porPagina ?? [10, 15, 25, 50, 100]" template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown" current-page-report-template="{first}–{last} de {totalRecords}" @page="paginar" />
    </div>

    <Dialog :visible="dialogo === 'registrar'" modal header="Registrar depósito de agencia" class="w-[min(56rem,calc(100vw-1rem))]" @update:visible="cerrar">
      <RegistrarDepositoForm v-if="opciones" :opciones="opciones" :agencia-inicial="aplicado.agencia" @registrado="registrado">
        <template #acciones><Button label="Cerrar" severity="secondary" text @click="dialogo = null" /></template>
      </RegistrarDepositoForm>
    </Dialog>
    <AcreditarDepositoDialog :visible="dialogo === 'acreditar'" :deposito="elegido" @update:visible="cerrar" @listo="resuelto" />
    <RechazarDepositoDialog :visible="dialogo === 'rechazar'" :deposito="elegido" @update:visible="cerrar" @listo="resuelto" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, shallowRef, toRaw, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchDepositos, fetchOpcionesDepositos } from '@/core/agencia/api'
import { disponibilidadDeposito, ESTADOS_DEPOSITO, etiquetaEstadoDeposito, filtroDepositosInicial, filtrosDepositosActivos, queryDepositos, type AccionDeposito, type FiltroDepositos } from '@/core/agencia/depositos'
import type { DepositoFila, OpcionesDepositos, PaginaDepositos } from '@/core/agencia/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import { deDia } from '@/core/salida/filtro'
import { diaISO, fecha, hora } from '@/core/venta/modelo'
import DataGrid from '@/shared/data-grid/DataGrid.vue'
import GridText from '@/shared/data-grid/GridText.vue'
import type { GridColumn } from '@/shared/data-grid/types'
import AcreditarDepositoDialog from './AcreditarDepositoDialog.vue'
import RechazarDepositoDialog from './RechazarDepositoDialog.vue'
import RegistrarDepositoForm from './RegistrarDepositoForm.vue'

/** Las del legado, en su orden (más Id y quién lo resolvió). */
const COLUMNAS: GridColumn[] = [
  { key: 'id', label: 'Id', width: '5rem', align: 'end' },
  { key: 'fecha', label: 'Fecha', width: '7rem' },
  { key: 'agencia', label: 'Agencia', width: '2fr', minWidth: '12rem' },
  { key: 'estado', label: 'Estado', width: '7.5rem' },
  { key: 'importe', label: 'Importe', width: '8rem', align: 'end' },
  { key: 'aplicaBono', label: 'Aplica bono', width: '6rem', align: 'center' },
  { key: 'bono', label: 'Bono', width: '7rem', align: 'end' },
  { key: 'numeroBoleta', label: 'Boleta', width: '8rem' },
  { key: 'creadoEn', label: 'Creado', width: '9rem' },
  { key: 'creadoPor', label: 'Usuario', width: '8rem' },
  { key: 'resuelto', label: 'Resuelto por', width: '9rem' },
  { key: 'motivo', label: 'Observación / motivo rechazo', width: '2fr', minWidth: '12rem' },
]

const BOTONES: Array<{ accion: AccionDeposito; etiqueta: string; icono: string; ayuda: string; severidad: 'success' | 'danger' }> = [
  { accion: 'acreditar', etiqueta: 'Acreditar', icono: 'check-circle-outline', ayuda: 'Sumar el depósito (y su bono) al saldo de la agencia', severidad: 'success' },
  { accion: 'rechazar', etiqueta: 'Rechazar', icono: 'block', ayuda: 'No se acredita; la agencia recibe el motivo', severidad: 'danger' },
]

type Dialogo = 'registrar' | AccionDeposito

const route = useRoute()
const router = useRouter()

const opciones = shallowRef<OpcionesDepositos | null>(null)
const datos = shallowRef<PaginaDepositos | null>(null)
const cargando = ref(false)
const error = ref('')
const borrador = reactive<FiltroDepositos>(filtroDepositosInicial())
const aplicado = ref<FiltroDepositos>(filtroDepositosInicial())
const pagina = ref(1)
const porPagina = ref(15)
const elegido = ref<DepositoFila | null>(null)
const dialogo = ref<Dialogo | null>(null)
const ancho = () => globalThis.matchMedia?.('(min-width: 64rem)').matches ?? true
const filtrosAbiertos = ref(ancho())

// —— Filtros ——
const rango = computed<Array<Date | null> | null>({
  get: () => (borrador.desde ? [deDia(borrador.desde), borrador.hasta ? deDia(borrador.hasta) : null] : null),
  set: (v) => {
    borrador.desde = v?.[0] ? diaISO(v[0]) : null
    borrador.hasta = v?.[1] ? diaISO(v[1]) : null
  },
})
const corta = (dia: string) => dia.split('-').reverse().join('/')
const rangoPorDefecto = computed(() => (opciones.value ? `${corta(opciones.value.desde)} – ${corta(opciones.value.hasta)}` : '±1 mes'))
const rangoTexto = computed(() => {
  if (aplicado.value.id !== null) return `Depósito ${aplicado.value.id}`
  return datos.value ? `${corta(datos.value.desde)} – ${corta(datos.value.hasta)}` : ''
})
const activos = computed(() => filtrosDepositosActivos(aplicado.value))
const borradorModificado = computed(() => JSON.stringify(borrador) !== JSON.stringify(filtroDepositosInicial()))

function aplicar() {
  if (borrador.desde && !borrador.hasta) borrador.hasta = borrador.desde
  aplicado.value = structuredClone(toRaw(borrador))
  pagina.value = 1
  if (!ancho()) filtrosAbiertos.value = false
}
function limpiar() {
  Object.assign(borrador, filtroDepositosInicial())
  aplicar()
}
function paginar(e: { page: number; rows: number }) {
  pagina.value = e.rows === porPagina.value ? e.page + 1 : 1
  porPagina.value = e.rows
}

// —— Datos ——
const consulta = computed(() => queryDepositos(aplicado.value, pagina.value, porPagina.value))
let pedido = 0
async function cargar() {
  const n = ++pedido
  cargando.value = true
  error.value = ''
  try {
    const r = await fetchDepositos(consulta.value)
    if (n !== pedido) return
    datos.value = r
    elegido.value = elegido.value ? (r.items.find((d) => d.id === elegido.value!.id) ?? null) : r.items.length === 1 && aplicado.value.id !== null ? r.items[0]! : null
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver los depósitos de agencia (agencia.depositar).' : e instanceof Error ? e.message : String(e)
  } finally {
    if (n === pedido) cargando.value = false
  }
}
watch(consulta, cargar)

// —— Selección y acciones ——
const seleccion = computed(() => new Set(elegido.value ? [String(elegido.value.id)] : []))
const claseFila = (r: unknown) => ({ 'fila-rechazada': (r as DepositoFila).estado === 'rechazado' })
const estadoAcciones = computed(() => disponibilidadDeposito(elegido.value, opciones.value?.puede.acreditar ?? false))

function elegir(d: DepositoFila) {
  elegido.value = elegido.value?.id === d.id ? null : d
}
function cerrar(visible: boolean) {
  if (!visible) dialogo.value = null
}
function resuelto(d: DepositoFila) {
  elegido.value = d
  void cargar()
}
function registrado(d: DepositoFila) {
  notify.success(`Depósito ${d.numeroBoleta} registrado`)
  dialogo.value = null
  elegido.value = d
  void cargar()
}

// —— Celdas ——
const f = (r: unknown) => r as DepositoFila
const cuando = (iso: string | null) => (iso ? `${fecha(iso)} ${hora(iso)}` : '—')
function texto(d: DepositoFila, key: string): string {
  switch (key) {
    case 'fecha':
      return corta(d.fecha)
    case 'agencia':
      return d.agencia.nombre
    case 'importe':
      return d.importe.texto
    case 'aplicaBono':
      return d.aplicaBono ? 'Sí' : 'No'
    case 'bono':
      return d.aplicaBono ? d.bono.texto : '—'
    case 'creadoEn':
      return cuando(d.creadoEn)
    case 'resuelto':
      return d.resueltoPor ? `${d.resueltoPor} · ${fecha(d.resueltoEn)}` : '—'
    case 'motivo':
      return d.motivoRechazo ?? d.observacion ?? ''
    default: {
      const v = (d as unknown as Record<string, unknown>)[key]
      return v === null || v === undefined ? '' : String(v)
    }
  }
}

// `?id=` filtra por ese depósito y lo elige (enlaces de «Registrar depósito»);
// `?agencia=&estado=` llega desde «Consultar saldos» (por acreditar).
function desdeRuta() {
  const entero = (v: unknown) => (Number.isInteger(Number(v)) && Number(v) > 0 ? Number(v) : null)
  const id = entero(route.query.id)
  const agencia = entero(route.query.agencia)
  const estados = String(route.query.estado ?? '').split(',').filter((e): e is FiltroDepositos['estados'][number] => ESTADOS_DEPOSITO.some((x) => x.valor === e))
  if (id === null && agencia === null && !estados.length) return false
  // Los pendientes pueden ser viejos: el último año (el máximo del backend).
  const hoy = new Date()
  const rangoAnual = id === null ? { desde: diaISO(new Date(hoy.getFullYear() - 1, hoy.getMonth(), hoy.getDate() + 1)), hasta: diaISO(hoy) } : {}
  Object.assign(borrador, { ...filtroDepositosInicial(), id, agencia, estados, ...rangoAnual })
  aplicado.value = structuredClone(toRaw(borrador))
  void router.replace({ query: {} })
  return true
}

onMounted(async () => {
  if (!desdeRuta()) void cargar()
  opciones.value = await fetchOpcionesDepositos().catch(() => null)
})
</script>

<style scoped>
.filtros-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  min-width: 0;
  padding: 0.75rem 1rem;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}
.filtros {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
  padding: 0 1rem 1rem;
}
@container (min-width: 40rem) {
  .filtros {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@container (min-width: 64rem) {
  .filtros {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
.campo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.campo > span:first-child {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
}
.filtros-botones {
  display: flex;
  justify-content: flex-end;
  align-items: end;
  gap: 0.5rem;
  grid-column: 1 / -1;
}
.acciones {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  padding: 0.5rem 0.75rem;
}
.acciones-elegido {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  flex: 1 1 12rem;
}
.acciones-botones {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}
.acciones-botones :deep(.p-button) {
  gap: 0.375rem;
}
.tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  width: 100%;
}
:deep(.fila-rechazada) {
  color: var(--p-text-muted-color);
}
</style>
