<!--
  Boletos (el "Boletos" del legado): filtros arriba (se aplican con
  «Filtrar»), el panel de acciones sobre el boleto elegido y la lista
  paginada en el servidor. Se elige un boleto con un clic (o Enter) y el panel
  ofrece Consultar, Cancelar, Revertir, Anular, Chequear, Reasignar, Correo, Imp.
  factura, Bitácora y Chat; cada acción explica por qué no se puede si no se
  puede. Columnas ordenables, redimensionables y ocultables (se recuerdan en
  el navegador). `?id=` abre ese boleto (enlaces del chat).
  Contenido angosto: tarjetas; filtros en una columna.
-->
<template>
  <div class="@container flex flex-col gap-3">
    <!-- Filtros -->
    <section class="panel !p-0">
      <button type="button" class="filtros-head" :aria-expanded="filtrosAbiertos" aria-controls="filtros-boletos" @click="filtrosAbiertos = !filtrosAbiertos">
        <icon name="filter-alt-outline" />
        <span class="font-medium">Filtrar resultados</span>
        <Badge v-if="activos" :value="activos" />
        <span class="ml-auto truncate text-xs text-muted-color">{{ rangoTexto }}</span>
        <icon :name="filtrosAbiertos ? 'expand-less' : 'expand-more'" />
      </button>
      <form v-show="filtrosAbiertos" id="filtros-boletos" class="filtros" @submit.prevent="aplicar">
        <label class="campo">
          <span>Id</span>
          <InputNumber v-model="borrador.id" :use-grouping="false" :min="1" placeholder="Identificador" fluid />
        </label>
        <label class="campo">
          <span>Salida</span>
          <InputNumber v-model="borrador.salida" :use-grouping="false" :min="1" placeholder="Id de la salida" fluid />
        </label>
        <label class="campo @3xl:col-span-2">
          <span>Rango de fechas <span class="font-normal">(salida o venta)</span></span>
          <DatePicker v-model="rango" selection-mode="range" :manual-input="false" date-format="dd/mm/yy" show-icon show-button-bar :placeholder="`Por defecto ${rangoPorDefecto}`" fluid />
        </label>
        <label class="campo">
          <span>Estado</span>
          <MultiSelect v-model="borrador.estados" :options="ESTADOS" option-label="etiqueta" option-value="valor" display="chip" :max-selected-labels="2" placeholder="Todos" fluid />
        </label>
        <label class="campo">
          <span>Tipo de documento</span>
          <Select v-model="borrador.documento" :options="DOCUMENTOS" option-label="etiqueta" option-value="valor" show-clear placeholder="Todos" fluid />
        </label>
        <label class="campo">
          <span>Número de asiento</span>
          <InputNumber v-model="borrador.asiento" :use-grouping="false" :min="0" placeholder="Número" fluid />
        </label>
        <div class="campo">
          <span id="clase-asiento">Clase de asiento</span>
          <SelectButton v-model="borrador.clase" :options="['A', 'B']" aria-labelledby="clase-asiento" />
        </div>
        <label class="campo">
          <span>Serie de factura</span>
          <InputText v-model="borrador.serie" placeholder="Serie" fluid />
        </label>
        <label class="campo">
          <span>Número de factura</span>
          <InputNumber v-model="borrador.factura" :use-grouping="false" :min="0" placeholder="Número DTE" fluid />
        </label>
        <label class="campo">
          <span>Origen (sube en)</span>
          <InputText v-model="borrador.origen" placeholder="Estación de origen" fluid />
        </label>
        <label class="campo">
          <span>Destino (baja en)</span>
          <InputText v-model="borrador.destino" placeholder="Estación de destino" fluid />
        </label>
        <label class="campo">
          <span>Referencia externa</span>
          <InputText v-model="borrador.referencia" placeholder="Pasarela de pago" fluid />
        </label>
        <label class="campo">
          <span>Autorización de tarjeta</span>
          <InputText v-model="borrador.autorizacion" placeholder="Autorización" fluid />
        </label>
        <div class="campo @3xl:col-span-2">
          <label for="cliente-documento">Cliente del documento</label>
          <ClienteFiltro v-model="borrador.clienteDocumento" input-id="cliente-documento" placeholder="NIT o nombre de quien factura" />
        </div>
        <div class="campo @3xl:col-span-2">
          <label for="cliente-boleto">Cliente del boleto (pasajero)</label>
          <ClienteFiltro v-model="borrador.clienteBoleto" input-id="cliente-boleto" placeholder="Documento o nombre del pasajero" />
        </div>
        <label class="campo">
          <span>Reasignado del boleto</span>
          <InputNumber v-model="borrador.reasignado" :use-grouping="false" :min="1" placeholder="Id del boleto original" fluid />
        </label>
        <div class="filtros-botones">
          <Button type="button" label="Limpiar" severity="secondary" text :disabled="!borradorModificado" @click="limpiar" />
          <Button type="submit" label="Filtrar"><template #icon><icon name="filter-alt" class="mr-1" /></template></Button>
        </div>
      </form>
    </section>

    <!-- Panel de acciones sobre el boleto elegido -->
    <div class="acciones panel" role="toolbar" aria-label="Acciones del boleto elegido">
      <div class="acciones-elegido">
        <template v-if="elegido">
          <span class="font-semibold">#{{ elegido.id }}</span>
          <span class="truncate text-sm text-muted-color">asiento {{ elegido.asiento }} · {{ elegido.clienteBoleto?.nombre ?? 'sin pasajero' }}</span>
          <Button text rounded size="small" severity="secondary" class="tap-target" aria-label="Quitar selección" v-tooltip.top="'Quitar selección'" @click="elegido = null"><template #icon><icon name="close" /></template></Button>
        </template>
        <span v-else class="text-sm text-muted-color">Elija un boleto de la lista</span>
      </div>
      <div class="acciones-botones">
        <template v-for="a in botones" :key="a.accion">
          <span v-if="estadoAcciones[a.accion].visible" v-tooltip.bottom="estadoAcciones[a.accion].motivo ?? a.ayuda">
            <Button :severity="a.severidad ?? 'secondary'" :outlined="a.severidad === undefined" size="small" :disabled="!estadoAcciones[a.accion].habilitada || ocupado === a.accion" :loading="ocupado === a.accion" :aria-label="a.etiqueta" @click="ejecutar(a.accion)">
              <icon :name="a.icono" />
              <span class="hidden @3xl:inline">{{ a.etiqueta }}</span>
            </Button>
          </span>
        </template>
      </div>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <DataGrid
      :columns="columnasGrid"
      :rows="datos?.items ?? []"
      :row-key="(r) => String((r as BoletoFila).id)"
      :loading="cargando"
      :selected-keys="seleccion"
      density="compact"
      :card-fields="4"
      max-height="calc(100dvh - 14rem)"
      row-clickable
      resizable
      hideable
      :row-class="claseFila"
      @row-click="elegir($event as BoletoFila)"
      @sort="ordenar"
      @resize="redimensionar"
      @hide="ocultar"
    >
      <template #cell="{ row, column }">
        <template v-if="column.key === 'estado'"><Tag :value="etiquetaEstado(f(row).estado).etiqueta" :severity="etiquetaEstado(f(row).estado).severidad" class="!text-xs" /></template>
        <GridText v-else :text="texto(f(row), column.key)" />
      </template>
      <template #card="{ row }">
        <div class="tarjeta">
          <div class="flex items-center justify-between gap-2">
            <span class="font-semibold">#{{ f(row).id }} · asiento {{ f(row).asiento }}</span>
            <Tag :value="etiquetaEstado(f(row).estado).etiqueta" :severity="etiquetaEstado(f(row).estado).severidad" class="!text-xs" />
          </div>
          <div class="truncate text-sm">{{ f(row).sube }} → {{ f(row).baja }}</div>
          <div class="truncate text-xs text-muted-color">{{ texto(f(row), 'fecha') }} · {{ documentoTexto(f(row).documento) }}</div>
          <div class="flex justify-between gap-2 text-xs">
            <span class="truncate">{{ f(row).clienteBoleto?.nombre ?? '—' }}</span>
            <span class="shrink-0 tabular-nums">{{ texto(f(row), 'precio') }}</span>
          </div>
        </div>
      </template>
      <template #empty>
        <icon name="confirmation-number-outline" size="2rem" />
        <span>No hay boletos con estos filtros.</span>
      </template>
    </DataGrid>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <MultiSelect v-model="visibles" :options="COLUMNAS" option-label="label" option-value="key" :max-selected-labels="0" selected-items-label="Columnas ({0})" placeholder="Columnas" size="small" class="w-44">
        <template #dropdownicon><icon name="view-column-outline" /></template>
      </MultiSelect>
      <Paginator v-if="datos" :rows="pagina.porPagina" :first="(pagina.pagina - 1) * pagina.porPagina" :total-records="datos.total" :rows-per-page-options="opciones?.porPagina ?? [10, 15, 25, 50, 100]" template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown" current-page-report-template="{first}–{last} de {totalRecords}" @page="paginar" />
    </div>

    <ConsultarBoletoDialog :visible="dialogo === 'consultar'" :id="consultarId" @update:visible="cerrar" @ver="(id) => (consultarId = id)" @bitacora="bitacoraId = consultarId" />
    <CancelarBoletoDialog :visible="dialogo === 'cancelar'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <RevertirBoletoDialog :visible="dialogo === 'revertir'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <ChequearBoletoDialog :visible="dialogo === 'chequear'" :boleto="elegido" @update:visible="cerrar" @listo="cargar" />
    <CorreoBoletoDialog :visible="dialogo === 'correo'" :boleto="elegido" @update:visible="cerrar" />
    <AnularBoletosDialog :visible="dialogo === 'anular'" :ids="elegido ? [elegido.id] : []" @update:visible="cerrar" @listo="cargar" />
    <BitacoraDialog v-if="bitacoraId !== null" :visible="true" tipo="boleto" :id="bitacoraId" @update:visible="(v: boolean) => !v && (bitacoraId = null)" />
    <EnviarPorChatDialog :visible="dialogo === 'chat'" :referencias="elegido ? [{ tipo: 'BoletoAsiento', id: elegido.id }] : []" @update:visible="cerrar" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, shallowRef, toRaw, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchBoletosListado, fetchOpcionesBoletos } from '@/core/boleto/api'
import { aQuery, alternarOrden, disponibilidad, DOCUMENTOS, documentoTexto, ESTADOS, etiquetaEstado, filtroInicial, filtrosActivos, paginaInicial, type AccionBoleto, type FiltroBoletos, type Pagina } from '@/core/boleto/listado'
import type { BoletoFila, OpcionesBoletos, OrdenBoletos, PaginaBoletos } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { notify } from '@/core/notify'
import { deDia } from '@/core/salida/filtro'
import { diaISO, fecha, hora } from '@/core/venta/modelo'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import AnularBoletosDialog from '@/shared/boleto/AnularBoletosDialog.vue'
import EnviarPorChatDialog from '@/shared/chat/EnviarPorChatDialog.vue'
import { anunciarEnPantalla } from '@/shared/chat/integracion'
import DataGrid from '@/shared/data-grid/DataGrid.vue'
import GridText from '@/shared/data-grid/GridText.vue'
import type { GridColumn } from '@/shared/data-grid/types'
import CancelarBoletoDialog from './CancelarBoletoDialog.vue'
import RevertirBoletoDialog from './RevertirBoletoDialog.vue'
import ChequearBoletoDialog from './ChequearBoletoDialog.vue'
import ClienteFiltro from './ClienteFiltro.vue'
import ConsultarBoletoDialog from './ConsultarBoletoDialog.vue'
import CorreoBoletoDialog from './CorreoBoletoDialog.vue'

interface Columna extends GridColumn {
  /** Orden del backend al hacer clic en el título. */
  orden?: OrdenBoletos
  /** Oculta hasta que se elija en «Columnas» (como en el legado). */
  oculta?: boolean
}

/** Las del legado, en su orden. */
const COLUMNAS: Columna[] = [
  { key: 'id', label: 'ID', width: '5.5rem', orden: 'id', align: 'end' },
  { key: 'fecha', label: 'Fecha salida', width: '10rem', orden: 'fecha' },
  { key: 'asiento', label: 'Número', width: '5.5rem', orden: 'numero', align: 'end' },
  { key: 'estado', label: 'Estado', width: '7.5rem' },
  { key: 'documento', label: 'Documento', width: '13rem' },
  { key: 'ruta', label: 'Ruta', width: '2fr', minWidth: '14rem', orden: 'ruta' },
  { key: 'sube', label: 'Sube en', width: '10rem' },
  { key: 'baja', label: 'Baja en', width: '10rem' },
  { key: 'clase', label: 'Clase', width: '4.5rem', align: 'center', oculta: true },
  { key: 'referenciaExterna', label: 'Ref. externa', width: '9rem', oculta: true },
  { key: 'autorizacion', label: 'Autorización tarjeta', width: '9rem', oculta: true },
  { key: 'clienteDocumento', label: 'Cliente documento', width: '14rem', orden: 'clienteDocumento' },
  { key: 'clienteBoleto', label: 'Cliente boleto', width: '14rem', orden: 'clienteBoleto' },
  { key: 'tipoPago', label: 'Tipo pago', width: '7rem' },
  { key: 'precio', label: 'Precio', width: '7rem', align: 'end' },
  { key: 'reasignadoDe', label: 'Reasignado de', width: '7rem', align: 'end', oculta: true },
  { key: 'empresa', label: 'Empresa', width: '8rem', oculta: true },
  { key: 'creada', label: 'Fecha creación', width: '10rem', orden: 'creada' },
  { key: 'usuario', label: 'Usuario creación', width: '10rem' },
  { key: 'estacion', label: 'Estación creación', width: '10rem' },
]

const BOTONES: Array<{ accion: AccionBoleto; etiqueta: string; icono: string; ayuda: string; severidad?: 'warn' | 'danger' }> = [
  { accion: 'consultar', etiqueta: 'Consultar', icono: 'visibility-outline', ayuda: 'Ver todos los datos del boleto' },
  { accion: 'cancelar', etiqueta: 'Cancelar', icono: 'person-off-outline', ayuda: 'El pasajero no viaja: libera el asiento, conserva la venta y la factura' },
  { accion: 'revertir', etiqueta: 'Revertir', icono: 'undo', ayuda: 'El pasajero sí viaja: el cancelado vuelve a emitido si su asiento sigue libre' },
  { accion: 'anular', etiqueta: 'Anular', icono: 'cancel-outline', ayuda: 'Anula el boleto y su factura' },
  { accion: 'chequear', etiqueta: 'Chequear', icono: 'how-to-reg-outline', ayuda: 'El pasajero se presentó a abordar' },
  { accion: 'reasignar', etiqueta: 'Reasignar', icono: 'swap-horiz', ayuda: 'Cambiar de asiento o de salida' },
  { accion: 'correo', etiqueta: 'Correo', icono: 'mail-outline', ayuda: 'Enviar el boleto por correo' },
  { accion: 'factura', etiqueta: 'Imp. factura', icono: 'print-outline', ayuda: 'Reimprimir el ticket con la factura' },
  { accion: 'bitacora', etiqueta: 'Bitácora', icono: 'history', ayuda: 'Historial del boleto' },
  { accion: 'chat', etiqueta: 'Chat', icono: 'forum-outline', ayuda: 'Enviar el boleto por el chat' },
]
const botones = BOTONES

const PREFERENCIAS = 'boletos.columnas'
type Dialogo = 'consultar' | 'cancelar' | 'revertir' | 'chequear' | 'correo' | 'anular' | 'chat'

const route = useRoute()
const router = useRouter()

const opciones = shallowRef<OpcionesBoletos | null>(null)
const datos = shallowRef<PaginaBoletos | null>(null)
const cargando = ref(false)
const error = ref('')
const borrador = reactive<FiltroBoletos>(filtroInicial())
const aplicado = ref<FiltroBoletos>(filtroInicial())
const pagina = ref<Pagina>(paginaInicial())
const elegido = ref<BoletoFila | null>(null)
const dialogo = ref<Dialogo | null>(null)
const consultarId = ref<number | null>(null)
const bitacoraId = ref<number | null>(null)
const ocupado = ref<AccionBoleto | null>(null)
/** Desde `lg` los filtros empiezan abiertos; en angosto, cerrados (y se cierran al filtrar). */
const ancho = () => globalThis.matchMedia?.('(min-width: 64rem)').matches ?? true
const filtrosAbiertos = ref(ancho())

// —— Columnas: visibles y anchos, recordados en el navegador ——
function leerPreferencias(): { visibles?: string[]; anchos?: Record<string, string> } {
  try {
    return JSON.parse(localStorage.getItem(PREFERENCIAS) ?? '{}')
  } catch {
    return {}
  }
}
const preferencias = leerPreferencias()
const visibles = ref<string[]>(preferencias.visibles?.filter((k) => COLUMNAS.some((c) => c.key === k)) ?? COLUMNAS.filter((c) => !c.oculta).map((c) => c.key))
const anchos = ref<Record<string, string>>(preferencias.anchos ?? {})
watch([visibles, anchos], () => {
  try {
    localStorage.setItem(PREFERENCIAS, JSON.stringify({ visibles: visibles.value, anchos: anchos.value }))
  } catch {
    // Sin almacenamiento: solo dura la visita.
  }
}, { deep: true })

const columnasGrid = computed<GridColumn[]>(() =>
  COLUMNAS.filter((c) => visibles.value.includes(c.key)).map((c) => ({
    ...c,
    width: anchos.value[c.key] ?? c.width,
    sortable: !!c.orden,
    sort: c.orden && c.orden === pagina.value.orden ? pagina.value.dir : null,
  })),
)

function ordenar(key: string) {
  const orden = COLUMNAS.find((c) => c.key === key)?.orden
  if (orden) pagina.value = alternarOrden(pagina.value, orden)
}
function redimensionar(key: string, ancho: string | null) {
  const resto = Object.fromEntries(Object.entries(anchos.value).filter(([k]) => k !== key))
  anchos.value = ancho ? { ...resto, [key]: ancho } : resto
}
function ocultar(key: string) {
  if (visibles.value.length > 1) visibles.value = visibles.value.filter((k) => k !== key)
}

// —— Filtros ——
/** Mientras se elige el rango el fin queda vacío; al filtrar, un solo día es desde y hasta. */
const rango = computed<Array<Date | null> | null>({
  get: () => (borrador.desde ? [deDia(borrador.desde), borrador.hasta ? deDia(borrador.hasta) : null] : null),
  set: (v) => {
    borrador.desde = v?.[0] ? diaISO(v[0]) : null
    borrador.hasta = v?.[1] ? diaISO(v[1]) : null
  },
})
const corta = (dia: string) => dia.split('-').reverse().join('/')
const rangoPorDefecto = computed(() => (opciones.value ? `${corta(opciones.value.desde)} – ${corta(opciones.value.hasta)}` : '±30 días'))
const rangoTexto = computed(() => {
  if (aplicado.value.id !== null) return `Boleto ${aplicado.value.id}`
  return datos.value ? `${corta(datos.value.desde)} – ${corta(datos.value.hasta)}` : ''
})
const activos = computed(() => filtrosActivos(aplicado.value))
const borradorModificado = computed(() => JSON.stringify(borrador) !== JSON.stringify(filtroInicial()))

function aplicar() {
  if (borrador.desde && !borrador.hasta) borrador.hasta = borrador.desde
  aplicado.value = structuredClone(toRaw(borrador))
  pagina.value = { ...pagina.value, pagina: 1 }
  if (!ancho()) filtrosAbiertos.value = false
}
function limpiar() {
  Object.assign(borrador, filtroInicial())
  aplicar()
}

function paginar(e: { page: number; rows: number }) {
  pagina.value = { ...pagina.value, pagina: e.rows === pagina.value.porPagina ? e.page + 1 : 1, porPagina: e.rows }
}

// —— Datos ——
const consulta = computed(() => aQuery(aplicado.value, pagina.value))
let pedido = 0
async function cargar() {
  const n = ++pedido
  cargando.value = true
  error.value = ''
  try {
    const r = await fetchBoletosListado(consulta.value)
    if (n !== pedido) return
    datos.value = r
    // El elegido se refresca con sus datos nuevos (o se suelta si ya no está).
    elegido.value = elegido.value ? (r.items.find((b) => b.id === elegido.value!.id) ?? null) : null
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError && e.status === 403 ? 'No tiene permiso para ver los boletos (boleto.ver).' : e instanceof Error ? e.message : String(e)
  } finally {
    if (n === pedido) cargando.value = false
  }
}
watch(consulta, cargar)

// —— Selección y acciones ——
const seleccion = computed(() => new Set(elegido.value ? [String(elegido.value.id)] : []))
const claseFila = (r: unknown) => ({ 'fila-liberada': ['anulado', 'reasignado', 'cancelado'].includes((r as BoletoFila).estado) })
const estadoAcciones = computed(() => disponibilidad(elegido.value, opciones.value?.puede ?? { cancelar: false, anular: false, reasignar: false, chequear: false }, new Date()))

function elegir(b: BoletoFila) {
  elegido.value = elegido.value?.id === b.id ? null : b
}

async function ejecutar(accion: AccionBoleto) {
  const b = elegido.value
  if (!b || !estadoAcciones.value[accion].habilitada) return
  switch (accion) {
    case 'consultar':
      consultarId.value = b.id
      dialogo.value = 'consultar'
      break
    case 'bitacora':
      bitacoraId.value = b.id
      break
    case 'reasignar':
      await router.push({ name: 'venta', query: { reasignar: String(b.id) } })
      break
    case 'factura':
      ocupado.value = 'factura'
      try {
        const { reimprimirBoletos } = await import('@/shared/boleto/reimprimir')
        await reimprimirBoletos([b.id])
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo reimprimir.')
      } finally {
        ocupado.value = null
      }
      break
    default:
      dialogo.value = accion
  }
}

function cerrar(visible: boolean) {
  if (visible) return
  dialogo.value = null
  if (route.query.id) void router.replace({ query: { ...route.query, id: undefined } })
}

// Con el chat encima, el boleto elegido se ofrece para adjuntar.
anunciarEnPantalla(() => (elegido.value ? [{ tipo: 'BoletoAsiento', id: elegido.value.id, etiqueta: `Boleto ${elegido.value.id} · asiento ${elegido.value.asiento} · ${elegido.value.sube} → ${elegido.value.baja}` }] : []))

// —— Celdas ——
const f = (r: unknown) => r as BoletoFila
const cuando = (iso: string | null) => (iso ? `${fecha(iso)} ${hora(iso)}` : '—')
function texto(b: BoletoFila, key: string): string {
  switch (key) {
    case 'fecha':
    case 'creada':
      return cuando(b[key])
    case 'documento':
      return documentoTexto(b.documento)
    case 'clienteDocumento':
      return b.clienteDocumento ? `${b.clienteDocumento.nombre} (${b.clienteDocumento.documento})` : '—'
    case 'clienteBoleto':
      return b.clienteBoleto?.nombre ?? '—'
    case 'precio':
      return b.precio ? `${b.precio.texto}${b.sinCobro ? ' *' : ''}` : '—'
    default: {
      const v = (b as unknown as Record<string, unknown>)[key]
      return v === null || v === undefined || v === '' ? '' : String(v)
    }
  }
}

// `?id=`: filtra por ese boleto y lo abre.
function desdeRuta() {
  const id = Number(route.query.id)
  if (!Number.isInteger(id) || id <= 0) return false
  Object.assign(borrador, { ...filtroInicial(), id })
  aplicado.value = structuredClone(toRaw(borrador))
  consultarId.value = id
  dialogo.value = 'consultar'
  return true
}
watch(() => route.query.id, desdeRuta)

onMounted(async () => {
  if (!desdeRuta()) void cargar()
  opciones.value = await fetchOpcionesBoletos().catch(() => null)
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
.campo > span:first-child,
.campo > label:first-child {
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
:deep(.fila-liberada) {
  color: var(--p-text-muted-color);
}
</style>
