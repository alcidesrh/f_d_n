<!--
  Resultado de la compra. Si se completó: aviso, descarga automática del
  PDF (una vez) con los boletos de todos los viajes y el botón para
  descargarlo. Si no: el motivo y cómo seguir.
-->
<template>
  <div class="contenedor flex max-w-3xl flex-col gap-4 py-6 md:py-10">
    <Skeleton v-if="cargando" height="20rem" border-radius="1rem" />

    <template v-else-if="compras.length">
      <section class="panel flex flex-col gap-5">
        <div class="flex items-start gap-3">
          <span class="grid size-12 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><icon name="check" size="1.8rem" /></span>
          <div>
            <h1 class="m-0 text-2xl font-bold">{{ t('compra.listo') }}</h1>
            <p class="m-0 mt-1 text-muted-color">
              <i18n-t keypath="compra.enviado" tag="span" scope="global">
                <template #email><b class="text-color">{{ compras[0]?.cliente?.email }}</b></template>
              </i18n-t>
            </p>
            <p v-if="descargado" class="m-0 mt-1 flex items-center gap-1 text-sm text-emerald-700"><icon name="download" size="1rem" />{{ t('compra.descargando') }}</p>
          </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
          <a :href="api.urlBoletoPdf(token)" download class="no-underline">
            <Button class="boton-compra w-full sm:w-auto" size="large"><icon name="download" size="1.1rem" /><span>{{ t('compra.descargar') }}</span></Button>
          </a>
          <a :href="api.urlBoletoPdf(token, true)" target="_blank" rel="noopener" class="no-underline">
            <Button severity="secondary" outlined size="large" class="w-full sm:w-auto"><icon name="eye" size="1.1rem" /><span>{{ t('compra.ver') }}</span></Button>
          </a>
        </div>

        <article v-for="(c, i) in compras" :key="c.id" class="rounded-xl bg-surface-50 p-4 ring-1 ring-surface-200">
          <p v-if="compras.length > 1" class="m-0 mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide" :class="i === 0 ? 'text-marca-700' : 'text-acento-700'">
            <icon :name="i === 0 ? 'arrow-right' : 'arrow-back-up'" size="0.95rem" />{{ t(i === 0 ? 'salidas.ida' : 'salidas.regreso') }}
          </p>
          <dl class="m-0 grid grid-cols-1 gap-x-6 gap-y-2.5 text-sm sm:grid-cols-2">
            <div><dt class="text-muted-color">{{ t('compra.viaje') }}</dt><dd class="m-0 font-medium">{{ c.origen?.nombre }} → {{ c.destino?.nombre }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.salida') }}</dt><dd class="m-0 font-medium first-letter:uppercase">{{ fechaLarga(c.salida?.salidaOrigen, region) }}, {{ hora(c.salida?.salidaOrigen, region) }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.asientos') }}</dt><dd class="m-0 font-medium">{{ c.boletos.map((b) => b.asiento).join(', ') }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.total') }}</dt><dd class="m-0 font-medium">{{ c.total.texto }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.numero') }}</dt><dd class="m-0 font-mono">{{ c.codigoBarras }}</dd></div>
            <div>
              <dt class="text-muted-color">{{ t('compra.factura') }}</dt>
              <dd class="m-0 font-medium">
                <template v-if="c.factura">
                  {{ t('compra.facturaDte', { numero: c.factura.numero, serie: c.factura.serie }) }}
                  <a v-if="c.factura.urlPdf" :href="c.factura.urlPdf" target="_blank" rel="noopener" class="ml-1">{{ t('compra.verFactura') }}</a>
                </template>
                <template v-else>{{ t('compra.facturaPendiente') }}</template>
              </dd>
            </div>
          </dl>
        </article>

        <p class="m-0 text-xs text-muted-color">{{ t('compra.sinReembolso') }}</p>
        <RouterLink :to="{ name: 'inicio', params: { idioma: locale } }" class="no-underline">
          <Button :label="t('compra.otra')" severity="secondary" text><template #icon><icon name="ticket" size="1.1rem" class="mr-1" /></template></Button>
        </RouterLink>
      </section>
    </template>

    <section v-else class="panel flex flex-col gap-4">
      <div class="flex items-start gap-3">
        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-orange-100 text-orange-600"><icon name="alert" size="1.6rem" /></span>
        <div>
          <h1 class="m-0 text-xl font-bold">{{ t('compra.fallo') }}</h1>
          <p class="m-0 mt-1">{{ mensaje || t('compra.falloTexto') }}</p>
        </div>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row">
        <RouterLink v-if="!carrito.vacio" :to="{ name: 'pago', params: { idioma: locale } }" class="no-underline"><Button :label="t('compra.reintentar')" class="w-full sm:w-auto" /></RouterLink>
        <RouterLink :to="{ name: 'inicio', params: { idioma: locale } }" class="no-underline"><Button :label="t('compra.buscarOtra')" severity="secondary" outlined class="w-full sm:w-auto" /></RouterLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import { useCarrito } from '@/carrito'
import { mensajeDeError } from '@/errores'
import { REGION, type Idioma } from '@/i18n'
import { fechaLarga, hora } from '@/modelo'
import type { Compra } from '@/tipos'

const props = defineProps<{ token: string; error: string | null }>()
const { t, te, locale } = useI18n()
const carrito = useCarrito()
const compras = ref<Compra[]>([])
const mensaje = ref(props.error ?? '')
const cargando = ref(true)
const descargado = ref(false)
const region = computed(() => REGION[locale.value as Idioma])

onMounted(async () => {
  try {
    const r = await api.compra(props.token)
    if (r.estado === 'completado' && r.compras?.length) {
      compras.value = r.compras
      if (carrito.token === props.token) carrito.olvidar()
      descargarUnaVez()
    } else {
      mensaje.value ||= r.mensaje ?? ''
      await carrito.refrescar()
    }
  } catch (e) {
    mensaje.value ||= mensajeDeError(e, t, te).titulo
  } finally {
    cargando.value = false
  }
})

/** Descarga el PDF la primera vez que se ve la compra (en esta pestaña). */
function descargarUnaVez() {
  const clave = `fdn.descargado.${props.token}`
  try {
    if (sessionStorage.getItem(clave)) return
    sessionStorage.setItem(clave, '1')
  } catch {
    // sin almacenamiento: se descarga igual
  }
  const a = document.createElement('a')
  a.href = api.urlBoletoPdf(props.token)
  a.download = `boleto_${compras.value[0]?.codigoBarras ?? ''}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()
  descargado.value = true
}
</script>
