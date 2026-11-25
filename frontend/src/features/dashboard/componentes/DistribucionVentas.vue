<!--
  Cómo se reparte lo vendido (la primera gráfica de la portada): por canal,
  por punto de venta, por usuario o, en boletos, por operación; de un día (hoy
  por defecto) o un rango, de todas las empresas del alcance o de las que se
  marquen. Datos de
  `GET /api/tablero/distribucion`, aparte del resto del tablero (su rango es
  libre). Si el rango llega a hoy, se actualiza sola cada 5 minutos.
-->
<template>
  <TableroPanel titulo="Distribución de la venta" icono="donut-large" :subtitulo="subtitulo" :ayuda="AYUDAS[vista]">
    <div class="flex flex-wrap items-center gap-2">
      <SelectButton v-model="vista" :options="VISTAS" option-label="nombre" option-value="id" :allow-empty="false" size="small" aria-label="Ver la venta por" />
      <DatePicker
        v-model="rango"
        selection-mode="range"
        date-format="dd/mm/yy"
        :max-date="hoy"
        :manual-input="false"
        hide-on-range-selection
        show-icon
        icon-display="input"
        show-button-bar
        size="small"
        class="w-full @md:w-56"
        input-id="distribucion-rango"
        aria-label="Día o rango de venta"
        placeholder="Hoy"
      />
    </div>
    <div v-if="empresas.length > 1" class="empresas" role="group" aria-label="Empresas">
      <label class="empresa" for="distribucion-empresa-todas">
        <Checkbox :model-value="!elegidas.length" binary input-id="distribucion-empresa-todas" @update:model-value="elegidas = []" />
        Todas
      </label>
      <label v-for="e in empresas" :key="e.id" class="empresa" :for="`distribucion-empresa-${e.id}`">
        <Checkbox :model-value="elegidas" :value="e.id" :input-id="`distribucion-empresa-${e.id}`" @update:model-value="marcar" />
        {{ e.nombre }}
      </label>
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-if="!datos && cargando" height="16rem" />
    <DonaPorciones
      v-else-if="datos"
      :key="`${vista}|${datos.desde}|${datos.hasta}|${datos.elegidas.join(',')}`"
      :porciones="porciones"
      :formato="formato"
      :total-rotulo="vista === 'tipo' ? 'boletos' : `${numero(datos.total.boletos)} boletos`"
      :agrupa="vista !== 'venta'"
      :fijo="vista === 'venta' || vista === 'tipo'"
      :nombre-resto="NOMBRE_RESTO[vista]"
      :titulo="`Venta ${VISTAS.find((v) => v.id === vista)!.titulo}, ${subtitulo}`"
      :class="{ 'opacity-60': cargando }"
    />
  </TableroPanel>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { claveDeUsuario } from '@/core/auth/alcanceSesion'
import { aDia } from '@/core/reporte/filtro'
import { fetchDistribucion } from '@/core/tablero/api'
import { elegirEmpresas } from '@/core/tablero/empresas'
import { dinero, numero, rango as rotuloRango } from '@/core/tablero/formato'
import type { Porcion } from '@/core/tablero/porciones'
import type { Canal, DistribucionVentas, TipoBoleto } from '@/core/tablero/types'
import DonaPorciones from './DonaPorciones.vue'
import TableroPanel from './TableroPanel.vue'

type Vista = 'venta' | 'estacion' | 'usuario' | 'tipo'

const VISTAS: { id: Vista; nombre: string; titulo: string }[] = [
  { id: 'venta', nombre: 'Venta', titulo: 'por canal' },
  { id: 'estacion', nombre: 'Estación', titulo: 'por punto de venta' },
  { id: 'usuario', nombre: 'Usuario', titulo: 'por usuario' },
  { id: 'tipo', nombre: 'Operaciones', titulo: 'por operación' },
]
const VENDIDO = 'Lo vendido son los boletos cobrados, también los de pasajeros que no se presentaron; no cuentan anulados, reasignados, cortesías ni vouchers.'
const AYUDAS: Record<Vista, string> = {
  venta: `Por canal: taquillas, agencias y página web. ${VENDIDO}`,
  estacion: `Cada estación con su nombre y su sigla; la página y las agencias, como dos puntos más. ${VENDIDO}`,
  usuario: `Cada usuario que vendió (la página vende con el usuario "web"). ${VENDIDO}`,
  tipo: 'Boletos de las ventas del periodo, cada uno en una sola operación: anulados y reasignados por su estado; de los demás, cortesías y vouchers por no cobrarse; cancelados, los cobrados que no se presentaron. Cobrados incluye los pospuestos de la página.',
}
const CANALES: Record<Canal, string> = { estacion: 'Taquilla', agencia: 'Agencias', web: 'Página web' }
/** Las operaciones (cada boleto en una sola, `DistribucionVentas::tipo()` del backend). */
const TIPOS: Record<TipoBoleto, { nombre: string; detalle: string }> = {
  cobrado: { nombre: 'Cobrados', detalle: 'Se cobraron y siguen vigentes' },
  anulado: { nombre: 'Anulados', detalle: 'Se anuló la compra' },
  reasignado: { nombre: 'Reasignados', detalle: 'Pasaron a otro asiento o salida' },
  cortesia: { nombre: 'Cortesías', detalle: 'Emitidos sin cobro' },
  cancelado: { nombre: 'Cancelados', detalle: 'El pasajero no se presentó' },
  voucher: { nombre: 'Vouchers', detalle: 'Del sistema anterior, sin cobro' },
}
const NOMBRE_RESTO: Record<Vista, string> = { venta: 'canales', estacion: 'puntos de venta', usuario: 'usuarios', tipo: 'operaciones' }
const ACTUALIZAR_MS = 5 * 60_000
const PREFERENCIAS = 'tablero.distribucion'

/** Vista y empresas elegidas, recordadas en este navegador; el día no (siempre arranca en hoy). */
function leerPreferencias(): { vista: Vista; empresas: number[] } {
  try {
    const p = JSON.parse(localStorage.getItem(claveDeUsuario(PREFERENCIAS)) ?? '{}') as { vista?: string; empresas?: unknown }
    return {
      vista: VISTAS.some((v) => v.id === p.vista) ? (p.vista as Vista) : 'venta',
      empresas: Array.isArray(p.empresas) ? p.empresas.filter((x): x is number => typeof x === 'number') : [],
    }
  } catch {
    return { vista: 'venta', empresas: [] }
  }
}

const inicial = leerPreferencias()
const vista = ref<Vista>(inicial.vista)
/** Empresas marcadas; vacío = "Todas". */
const elegidas = ref<number[]>(inicial.empresas)
const hoy = ref(new Date(new Date().setHours(0, 0, 0, 0)))
/** `[desde, hasta]`; `hasta` es null mientras se elige el segundo día (entonces es ese día). Vacío: hoy. */
const rango = ref<(Date | null)[] | null>(null)
const datos = shallowRef<DistribucionVentas | null>(null)
const cargando = ref(false)
const error = ref('')

const empresas = computed(() => datos.value?.empresas ?? [])
const marcar = (v: number[]) => (elegidas.value = elegirEmpresas(v, empresas.value.map((e) => e.id)))
const consulta = computed(() => {
  const desde = rango.value?.[0] ?? hoy.value
  return { desde: aDia(desde), hasta: aDia(rango.value?.[1] ?? desde), empresas: elegidas.value }
})
const subtitulo = computed(() => {
  if (!datos.value) return ''
  const { desde, hasta } = datos.value
  const dia = desde === hasta && desde === aDia(hoy.value) ? 'Hoy' : rotuloRango(desde, hasta)
  return vista.value === 'tipo' ? `${dia} · ${numero(datos.value.tipos.reduce((s, t) => s + t.boletos, 0))} boletos` : `${dia} · ${dinero(datos.value.total.monto, true)}`
})

const conBoletos = (n: number) => `${numero(n)} ${n === 1 ? 'boleto' : 'boletos'}`
/** Las porciones de cada vista. */
const PORCIONES: Record<Vista, (d: DistribucionVentas) => Porcion[]> = {
  venta: (d) => d.canales.map((c) => ({ clave: c.canal, etiqueta: CANALES[c.canal], nombre: CANALES[c.canal], valor: c.monto, detalle: conBoletos(c.boletos) })),
  // El nombre completo de la estación, y su sigla al lado (la página y las agencias no tienen).
  estacion: (d) => d.estaciones.map((e) => ({ clave: e.clave, etiqueta: e.nombre, nombre: e.canal === 'estacion' ? e.sigla : e.nombre, valor: e.monto, detalle: conBoletos(e.boletos) })),
  usuario: (d) => d.usuarios.map((u) => ({ clave: u.clave, etiqueta: u.usuario, nombre: u.nombre, valor: u.monto, detalle: conBoletos(u.boletos) })),
  tipo: (d) => d.tipos.map((t) => ({ clave: t.tipo, etiqueta: TIPOS[t.tipo].nombre, nombre: TIPOS[t.tipo].detalle, valor: t.boletos, detalle: t.monto ? dinero(t.monto, true) : undefined })),
}
const porciones = computed(() => (datos.value ? PORCIONES[vista.value](datos.value) : []))
const formato = computed(() => (vista.value === 'tipo' ? (v: number) => numero(v) : (v: number) => dinero(v, true)))

let pedido = 0
async function cargar(silencioso = false) {
  const n = ++pedido
  const { desde, hasta, empresas: e } = consulta.value
  cargando.value = true
  if (!silencioso) error.value = ''
  try {
    const r = await fetchDistribucion(desde, hasta, e, silencioso)
    if (n !== pedido) return
    datos.value = r
    error.value = ''
    // Una empresa recordada que ya no está en su alcance se quita (y si no queda ninguna, "Todas").
    const limpias = elegirEmpresas(e, r.empresas.map((x) => x.id))
    if (limpias.join() !== e.join()) elegidas.value = limpias
  } catch (err) {
    if (n !== pedido) return
    if (!silencioso || !datos.value) error.value = err instanceof Error && err.message ? `No se pudo cargar la distribución: ${err.message}` : 'No se pudo cargar la distribución.'
  } finally {
    if (n === pedido) cargando.value = false
  }
}

watch(consulta, (a, b) => (a.desde !== b.desde || a.hasta !== b.hasta || a.empresas.join() !== b.empresas.join()) && void cargar())
watch([vista, elegidas], () => {
  try {
    localStorage.setItem(claveDeUsuario(PREFERENCIAS), JSON.stringify({ vista: vista.value, empresas: elegidas.value }))
  } catch {
    /* sin almacenamiento: solo no se recuerda */
  }
})

/** Pasada la medianoche, "hoy" es otro día: lo que se miraba (sin rango elegido) también. */
function alDia() {
  const ahora = new Date(new Date().setHours(0, 0, 0, 0))
  if (ahora.getTime() !== hoy.value.getTime()) hoy.value = ahora
}
let temporizador: ReturnType<typeof setInterval> | undefined
function refrescar() {
  if (document.visibilityState !== 'visible') return
  alDia()
  if (consulta.value.hasta >= aDia(hoy.value)) void cargar(true)
}
function alVolver() {
  if (document.visibilityState === 'visible' && datos.value && Date.now() - new Date(datos.value.generado).getTime() > ACTUALIZAR_MS) refrescar()
}
onMounted(() => {
  void cargar()
  temporizador = setInterval(refrescar, ACTUALIZAR_MS)
  document.addEventListener('visibilitychange', alVolver)
})
onBeforeUnmount(() => {
  clearInterval(temporizador)
  document.removeEventListener('visibilitychange', alVolver)
})
</script>

<style scoped>
.empresas {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
}
.empresa {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2rem;
  font-size: 0.875rem;
  cursor: pointer;
}
</style>
