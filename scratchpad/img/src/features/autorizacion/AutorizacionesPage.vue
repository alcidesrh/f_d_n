<!--
  Autorizaciones, pases de un solo uso (ADR-040), ruta `/autorizaciones`
  (cualquier usuario con sesión):

  - «Por resolver»: las pendientes que el usuario puede aprobar o rechazar
    (solo con `autorizacion.aprobar`).
  - «Mis solicitudes»: las que pidió, con su estado; una aprobada se usa
    sola al repetir la operación.
  - «Historial»: todas las de su alcance de aprobación, filtrables por estado.

  `?vista=` elige la pestaña y `?id=` muestra esa autorización arriba. Se
  refresca sola con cada aviso (Mercure, `useAutorizacionesStore().cambios`).
-->
<template>
  <div class="@container mx-auto flex w-full max-w-4xl flex-col gap-4">
    <header class="flex flex-col gap-1">
      <h1 class="m-0 flex items-center gap-2 text-xl font-semibold"><icon name="verified-user-outline" />Autorizaciones</h1>
      <p class="m-0 text-sm text-muted-color">
        Un pase de un solo uso para lo que una regla le impide: sin el permiso, pasado su límite diario o con la salida ya pasada de hora. Se pide desde la operación
        bloqueada; aprobada, repita la operación.
      </p>
    </header>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <SelectButton v-model="vista" :options="vistas" option-label="etiqueta" option-value="valor" :allow-empty="false" aria-label="Vista" />
      <Select
        v-if="vista !== 'por-resolver'"
        v-model="estado"
        :options="ESTADOS_FILTRO"
        option-label="etiqueta"
        option-value="valor"
        show-clear
        placeholder="Todos los estados"
        class="w-full @lg:w-56"
        aria-label="Estado"
      />
    </div>

    <AutorizacionTarjeta v-if="destacada" :a="destacada" destacada :propia="destacada.solicitante.id === yo" @resolver="abrirResolver" @retirar="retirarla" @bitacora="(a) => (bitacora = a.id)" />

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Skeleton v-else-if="!pagina" height="10rem" />
    <p v-else-if="!items.length" class="m-0 py-10 text-center text-sm text-muted-color">{{ VACIO[vista] }}</p>
    <div v-else class="flex flex-col gap-3" :class="{ 'opacity-60': cargando }">
      <AutorizacionTarjeta v-for="a in items" :key="a.id" :a="a" :propia="a.solicitante.id === yo" @resolver="abrirResolver" @retirar="retirarla" @bitacora="(a) => (bitacora = a.id)" />
    </div>
    <Paginator v-if="pagina && pagina.total > pagina.porPagina" :rows="pagina.porPagina" :total-records="pagina.total" :first="(pagina.pagina - 1) * pagina.porPagina" @page="(e) => cargar(e.page + 1)" />

    <ResolverDialog :a="resolviendo" :como="como" @cerrar="resolviendo = null" @resuelta="alResolver" />
    <BitacoraDialog v-if="bitacora !== null" :visible="true" tipo="autorizacion" :id="bitacora" @update:visible="(v: boolean) => !v && (bitacora = null)" />
  </div>
</template>

<script setup lang="ts">
import { fetchAutorizacion, fetchAutorizaciones, retirar } from '@/core/autorizacion/api'
import { ESTADOS } from '@/core/autorizacion/reglas'
import { useAutorizacionesStore } from '@/core/autorizacion/store'
import type { Autorizacion, EstadoAutorizacion, PaginaAutorizaciones, VistaAutorizaciones } from '@/core/autorizacion/types'
import { useCuentaStore } from '@/core/cuenta/store'
import { notify } from '@/core/notify'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import AutorizacionTarjeta from './AutorizacionTarjeta.vue'
import ResolverDialog from './ResolverDialog.vue'

const ESTADOS_FILTRO = (Object.keys(ESTADOS) as EstadoAutorizacion[]).map((valor) => ({ valor, etiqueta: ESTADOS[valor].etiqueta }))
const VACIO: Record<VistaAutorizaciones, string> = {
  'por-resolver': 'No hay autorizaciones esperando su respuesta.',
  mias: 'No ha pedido autorizaciones. Se piden desde la operación que una regla le impide (anular, reasignar, cortesías).',
  todas: 'No hay autorizaciones con este filtro.',
}

const route = useRoute()
const router = useRouter()
const store = useAutorizacionesStore()
const cuenta = useCuentaStore()
const yo = computed(() => cuenta.cuenta?.id ?? null)

const aprueba = computed(() => !!store.resumen?.aprueba)
const vistas = computed(() => [
  ...(aprueba.value ? [{ valor: 'por-resolver' as const, etiqueta: store.resumen?.porResolver ? `Por resolver (${store.resumen.porResolver})` : 'Por resolver' }] : []),
  { valor: 'mias' as const, etiqueta: 'Mis solicitudes' },
  ...(aprueba.value ? [{ valor: 'todas' as const, etiqueta: 'Historial' }] : []),
])

const vista = ref<VistaAutorizaciones>('mias')
const estado = ref<EstadoAutorizacion | null>(null)
const pagina = shallowRef<PaginaAutorizaciones | null>(null)
const items = computed(() => (pagina.value?.items ?? []).filter((a) => a.id !== destacada.value?.id))
const cargando = ref(false)
const error = ref('')
const destacada = shallowRef<Autorizacion | null>(null)
const resolviendo = shallowRef<Autorizacion | null>(null)
const como = ref<'aprobar' | 'rechazar'>('aprobar')
const bitacora = ref<number | null>(null)

let pedido = 0
async function cargar(n = 1, silencioso = false) {
  const mio = ++pedido
  cargando.value = true
  error.value = ''
  try {
    const p = await fetchAutorizaciones(vista.value, vista.value === 'por-resolver' ? null : estado.value, n, silencioso)
    if (mio === pedido) pagina.value = p
  } catch (e) {
    if (mio === pedido) error.value = e instanceof Error ? e.message : String(e)
  } finally {
    if (mio === pedido) cargando.value = false
  }
}

async function cargarDestacada() {
  const id = Number(route.query.id)
  if (!id) return void (destacada.value = null)
  try {
    destacada.value = await fetchAutorizacion(id)
  } catch (e) {
    destacada.value = null
    notify.error(e instanceof Error ? e.message : String(e))
  }
}

function abrirResolver(a: Autorizacion, c: 'aprobar' | 'rechazar') {
  como.value = c
  resolviendo.value = a
}

function alResolver(a: Autorizacion) {
  resolviendo.value = null
  if (destacada.value?.id === a.id) destacada.value = a
  store.alAvisar()
}

async function retirarla(a: Autorizacion) {
  try {
    const r = await retirar(a.id)
    notify.info(`Autorización #${r.id} retirada.`)
    if (destacada.value?.id === r.id) destacada.value = r
    store.alAvisar()
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  }
}

watch([vista, estado], () => {
  void router.replace({ query: { ...route.query, vista: vista.value } })
  pagina.value = null
  void cargar()
})
watch(() => route.query.id, cargarDestacada)
// Cada aviso (pedida, resuelta, retirada) refresca lo que se ve.
watch(
  () => store.cambios,
  () => {
    void cargar(pagina.value?.pagina ?? 1, true)
    if (destacada.value) void cargarDestacada()
  },
)

onMounted(async () => {
  await store.cargarResumen()
  const pedida = route.query.vista as VistaAutorizaciones | undefined
  const disponibles = vistas.value.map((v) => v.valor)
  vista.value = pedida && disponibles.includes(pedida) ? pedida : store.resumen?.porResolver ? 'por-resolver' : 'mias'
  if (vista.value === 'mias') void cargar()
  void cargarDestacada()
})
</script>
