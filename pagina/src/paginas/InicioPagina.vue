<!--
  Inicio = compra (ADR-023): buscador; al cambiar cualquier campo (con
  origen, destino y fecha) aparecen las salidas de ida (y de regreso). Cada
  salida se abre como acordeón con su croquis. "Pagar asientos" aparta
  todo junto y lleva al pago; si otro tomó algún asiento, se avisa aquí.
  La búsqueda queda en la URL (`?o=&d=&f=&r=`) para compartirla o volver.
-->
<template>
  <div :class="{ 'pb-32': viaje.completa }">
    <!-- Con origen, destino y fecha elegidos, el resto de la página se atenúa como detrás de un modal. -->
    <Transition name="velo">
      <div v-if="viaje.completa" class="fixed inset-0 z-[25] bg-slate-900/60" aria-hidden="true" />
    </Transition>

    <section class="bg-gradient-to-br from-marca-950 via-marca-900 to-marca-800 text-white">
      <div class="contenedor pb-20 pt-6 md:pb-28 md:pt-12">
        <h1 class="m-0 max-w-3xl text-2xl font-bold leading-tight tracking-tight md:text-4xl lg:text-5xl">{{ t('inicio.titulo') }}</h1>
        <p class="m-0 mt-2 max-w-2xl text-sm text-blue-100 md:mt-3 md:text-lg">{{ t('inicio.subtitulo') }}</p>
      </div>
    </section>

    <section class="contenedor relative -mt-16 max-md:px-0 md:-mt-20" :class="{ 'z-[30]': viaje.completa }">
      <div class="panel shadow-lg max-md:rounded-none max-md:border-x-0">
        <BuscadorViaje />
      </div>
      <Message v-if="viaje.catalogos && !viaje.catalogos.ventaEnLinea" severity="warn" :closable="false" class="mt-3">
        {{ t('barra.ventaSuspendida') }}
      </Message>
    </section>

    <section v-if="viaje.completa" id="salidas" class="contenedor relative z-[30] mt-4 max-md:px-0 md:mt-5">
      <div class="flex flex-col gap-8 bg-surface-50 p-3 shadow-xl max-md:px-2 md:rounded-2xl md:p-5">
      <Message v-if="aviso" severity="warn" @close="aviso = ''">{{ aviso }}</Message>
      <Message v-if="error" severity="error" @close="error = null">
        <div class="font-semibold">{{ error.titulo }}</div>
        <div v-if="error.detalle" class="mt-1 text-sm">{{ error.detalle }}</div>
      </Message>
      <ListaSalidas v-for="s in viaje.sentidos" :key="s" :sentido="s" :nombres="nombres(s)" />
      </div>
    </section>
    <p v-else class="contenedor mt-6 text-center text-sm text-muted-color">{{ t('buscador.ayuda') }}</p>

    <!-- Contenido para quien llega a la página (y para los buscadores); se oculta al empezar la compra. -->
    <section v-if="!viaje.completa" class="contenedor mt-12 grid gap-3 md:grid-cols-3 md:gap-4">
      <div v-for="b in beneficios" :key="b.titulo" class="panel flex gap-3">
        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-marca-50 text-marca-800"><icon :name="b.icono" size="1.4rem" /></span>
        <div>
          <h2 class="m-0 text-base font-semibold">{{ t(b.titulo) }}</h2>
          <p class="m-0 mt-1 text-sm text-muted-color">{{ t(b.texto) }}</p>
        </div>
      </div>
    </section>

    <section v-if="!viaje.completa" class="contenedor mt-12">
      <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h2 class="m-0 text-xl font-bold md:text-2xl">{{ t('inicio.serviciosTitulo') }}</h2>
          <p class="m-0 mt-1 max-w-2xl text-sm text-muted-color">{{ t('inicio.experiencia') }}</p>
        </div>
        <RouterLink :to="{ name: 'servicios', params: { idioma: locale } }" class="inline-flex items-center gap-1 text-sm font-medium no-underline">
          {{ t('inicio.verServicios') }} <icon name="arrow-right" size="1rem" />
        </RouterLink>
      </div>
      <ul class="m-0 mt-4 grid list-none gap-3 p-0 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="s in servicios" :key="s.id">
          <RouterLink :to="{ name: 'servicios', params: { idioma: locale }, hash: `#${s.id}` }" class="panel flex h-full flex-col gap-1 text-color no-underline transition-shadow hover:shadow-md">
            <span class="font-semibold text-marca-900">{{ s.nombre }}</span>
            <span class="text-sm text-muted-color">{{ s.resumen }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <BarraCompra v-if="viaje.completa" :ocupado="carrito.ocupado" @pagar="pagar" @cancelar="cancelar" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ErrorPublico } from '@/api'
import { useCarrito } from '@/carrito'
import BarraCompra from '@/componentes/compra/BarraCompra.vue'
import BuscadorViaje from '@/componentes/compra/BuscadorViaje.vue'
import ListaSalidas from '@/componentes/compra/ListaSalidas.vue'
import { mensajeDeError, type MensajeError } from '@/errores'
import type { Idioma } from '@/i18n'
import { contenido } from '@/i18n/contenido'
import type { Conflicto } from '@/tipos'
import { useViaje, type Sentido } from '@/viaje'

const { t, te, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const viaje = useViaje()
const carrito = useCarrito()
const aviso = ref('')
const error = ref<MensajeError | null>(null)

const servicios = computed(() => contenido(locale.value as Idioma).servicios.lista)
const beneficios = [
  { icono: 'armchair', titulo: 'inicio.beneficios.asientoTitulo', texto: 'inicio.beneficios.asientoTexto' },
  { icono: 'shield-check', titulo: 'inicio.beneficios.pagoTitulo', texto: 'inicio.beneficios.pagoTexto' },
  { icono: 'ticket', titulo: 'inicio.beneficios.boletoTitulo', texto: 'inicio.beneficios.boletoTexto' },
]

function nombres(s: Sentido) {
  const { origen, destino } = viaje.busqueda
  if (!origen || !destino) return null
  const [o, d] = s === 'ida' ? [origen, destino] : [destino, origen]
  return viaje.nombres[o] && viaje.nombres[d] ? { origen: viaje.nombres[o], destino: viaje.nombres[d] } : null
}

// URL → búsqueda (al entrar con un enlace compartido o al volver).
function desdeUrl() {
  const q = route.query
  const n = (v: unknown) => (Number(v) > 0 ? Number(v) : null)
  const dia = (v: unknown) => (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : null)
  if (!q.o && !q.d) return
  Object.assign(viaje.busqueda, {
    origen: n(q.o),
    destino: n(q.d),
    fecha: dia(q.f) ?? viaje.busqueda.fecha,
    idaVuelta: q.r !== undefined,
    regreso: dia(q.r),
  })
}

desdeUrl()

onMounted(async () => {
  await viaje.cargarCatalogos()
  // Volvió del pago con los asientos aún apartados: se sueltan mientras edita.
  if (!carrito.vacio) await carrito.vaciar()
})

let espera: ReturnType<typeof setTimeout> | null = null
watch(
  () => ({ ...viaje.busqueda }),
  (b) => {
    if (typeof window === 'undefined') return
    // Búsqueda → URL (sin crear una entrada de historial por cada cambio).
    const query = b.origen && b.destino ? { o: String(b.origen), d: String(b.destino), f: b.fecha ?? undefined, ...(b.idaVuelta ? { r: b.regreso ?? '' } : {}) } : {}
    void router.replace({ query, hash: route.hash })
    if (espera) clearTimeout(espera)
    espera = setTimeout(() => void viaje.buscar(), 150)
  },
  { deep: true, immediate: true },
)

/** Empieza de nuevo: limpia la búsqueda y la elección (y suelta lo apartado, si lo hay). */
async function cancelar() {
  aviso.value = ''
  error.value = null
  viaje.cancelar()
  await carrito.vaciar()
}

async function pagar() {
  aviso.value = ''
  error.value = null
  try {
    await carrito.reservar(viaje.pedido())
    await router.push({ name: 'pago', params: { idioma: locale.value } })
  } catch (e) {
    if (e instanceof ErrorPublico && e.codigo === 'asientos_no_disponibles' && Array.isArray(e.detalle.viajes)) {
      const conflictos = e.detalle.viajes as Conflicto[]
      viaje.quitarConflictos(conflictos)
      const lista = conflictos.map((c) => `${t(`salidas.${viaje.sentidos[c.viaje] ?? 'ida'}`)}: ${c.numeros.join(', ')}`).join('; ')
      aviso.value = t('barra.conflicto', { lista })
      document.getElementById('salidas')?.scrollIntoView({ behavior: 'smooth' })
      return
    }
    error.value = mensajeDeError(e, t, te, { max: viaje.maxAsientos })
    if (e instanceof ErrorPublico) void viaje.buscar()
  }
}
</script>

<style scoped>
.velo-enter-active,
.velo-leave-active {
  transition: opacity 0.25s ease;
}
.velo-enter-from,
.velo-leave-to {
  opacity: 0;
}
</style>
