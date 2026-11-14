<!--
  «Mis compras»: los viajes comprados en línea por el cliente, sin usuario
  ni contraseña. Entra con la llave que quedó en el navegador al comprar o
  con la del enlace del correo del boleto (`?llave=`); si no tiene ninguna,
  pide un enlace a su correo. Primero los viajes próximos (el que sale antes,
  arriba), que se pueden modificar; luego los pasados (el más reciente,
  arriba), con su factura para descargar. No se anula ni se cancela.
-->
<template>
  <div class="contenedor flex max-w-3xl flex-col gap-4 py-6 md:py-10">
    <h1 class="m-0 text-2xl font-bold text-marca-900">{{ t('misCompras.titulo') }}</h1>

    <Skeleton v-if="cargando" height="18rem" border-radius="1rem" />

    <!-- Sin llave: pedir el enlace al correo. -->
    <section v-else-if="!datos" class="panel flex flex-col gap-4">
      <Message v-if="aviso" severity="warn" :closable="false">{{ aviso }}</Message>
      <template v-if="!enviado">
        <p class="m-0">{{ t('misCompras.accesoTexto') }}</p>
        <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="pedirEnlace">
          <label class="flex flex-1 flex-col gap-1.5">
            <span class="text-sm font-medium">{{ t('misCompras.correo') }}</span>
            <InputText v-model="correo" type="email" required autocomplete="email" inputmode="email" fluid />
          </label>
          <Button type="submit" :label="t('misCompras.pedirEnlace')" :loading="enviando" />
        </form>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <p class="m-0 text-sm text-muted-color">{{ t('misCompras.accesoAyuda') }}</p>
      </template>
      <div v-else class="flex items-start gap-3">
        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><icon name="mail" size="1.6rem" /></span>
        <div>
          <p class="m-0 font-semibold">{{ t('misCompras.enlaceEnviado') }}</p>
          <p class="m-0 mt-1 text-sm text-muted-color">{{ t('misCompras.enlaceEnviadoTexto', { correo }) }}</p>
        </div>
      </div>
    </section>

    <template v-else>
      <p class="m-0 flex flex-wrap items-center gap-x-2 text-sm text-muted-color">
        <span>{{ t('misCompras.de', { correo: datos.correo }) }}</span>
        <button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-sm font-medium text-marca-700 underline" @click="salir">{{ t('misCompras.salir') }}</button>
      </p>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <section v-if="!datos.futuros.length && !datos.pasados.length" class="panel text-center">
        <p class="m-0 mb-3">{{ t('misCompras.vacio') }}</p>
        <RouterLink :to="{ name: 'inicio', params: { idioma: locale } }" class="no-underline"><Button :label="t('nav.comprar')" /></RouterLink>
      </section>

      <section v-for="grupo in grupos" :key="grupo.clave" class="flex flex-col gap-3">
        <h2 class="m-0 flex items-center gap-2 text-lg font-semibold">
          <icon :name="grupo.icono" class="text-marca-700" />{{ grupo.titulo }}
          <span class="text-sm font-normal text-muted-color">({{ grupo.viajes.length }})</span>
        </h2>
        <p v-if="grupo.clave === 'futuros'" class="m-0 text-sm text-muted-color">{{ textoPolitica }}</p>

        <article v-for="v in grupo.viajes" :key="v.id" class="panel flex flex-col gap-3" :class="{ 'opacity-80': !v.futuro }">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p v-if="v.sentido" class="m-0 mb-1 text-xs font-semibold uppercase tracking-wide" :class="v.sentido === 'ida' ? 'text-marca-700' : 'text-acento-700'">
                {{ t(v.sentido === 'ida' ? 'salidas.ida' : 'salidas.regreso') }}
              </p>
              <p class="m-0 text-lg font-semibold">{{ v.origen }} → {{ v.destino }}</p>
              <p class="m-0 text-sm"><HoraSubida v-if="v.boletos[0]" :s="v.boletos[0].salida" /></p>
            </div>
            <span v-if="v.anulado" class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ t('misCompras.anulado') }}</span>
            <span v-else-if="v.futuro" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ t('misCompras.proximo') }}</span>
          </div>

          <dl class="m-0 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
            <div><dt class="text-muted-color">{{ t('compra.asientos') }}</dt><dd class="m-0 font-medium">{{ v.boletos.map((b) => `${b.asiento}${b.clase === 'B' ? ' B' : ''}`).join(', ') }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.total') }}</dt><dd class="m-0 font-medium">{{ v.total.texto }}</dd></div>
            <div><dt class="text-muted-color">{{ t('compra.numero') }}</dt><dd class="m-0 font-mono">{{ v.codigoBarras }}</dd></div>
            <div>
              <dt class="text-muted-color">{{ t('compra.factura') }}</dt>
              <dd class="m-0 font-medium">
                <template v-if="v.factura && !v.factura.anulada">{{ t('compra.facturaDte', { numero: v.factura.numero, serie: v.factura.serie }) }}</template>
                <template v-else>{{ t('misCompras.facturaEnProceso') }}</template>
              </dd>
            </div>
          </dl>

          <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <!-- Próximos: editar. -->
            <template v-if="v.futuro">
              <RouterLink v-if="v.editable" :to="{ name: 'modificarViaje', params: { idioma: locale, viaje: v.id } }" class="no-underline">
                <Button class="w-full sm:w-auto"><icon name="edit" size="1.1rem" /><span>{{ t('misCompras.modificar') }}</span></Button>
              </RouterLink>
              <Button severity="secondary" outlined class="w-full sm:w-auto" :loading="bajando === v.id" @click="descargar(v)">
                <icon name="download" size="1.1rem" /><span>{{ t('misCompras.boleto') }}</span>
              </Button>
              <span v-if="v.editable && v.limiteModificacion" class="text-xs text-muted-color">{{ t('misCompras.hasta', { fecha: fechaHora(v.limiteModificacion) }) }}</span>
              <span v-else-if="!v.editable && v.motivo" class="text-xs text-muted-color">{{ v.motivo }}</span>
            </template>
            <!-- Pasados: descargar la factura. -->
            <template v-else-if="v.descargarFactura">
              <a v-if="v.factura?.urlPdf" :href="v.factura.urlPdf" target="_blank" rel="noopener" class="no-underline">
                <Button class="w-full sm:w-auto"><icon name="receipt" size="1.1rem" /><span>{{ t('misCompras.factura') }}</span></Button>
              </a>
              <Button :severity="v.factura?.urlPdf ? 'secondary' : undefined" :outlined="!!v.factura?.urlPdf" class="w-full sm:w-auto" :loading="bajando === v.id" @click="descargar(v)">
                <icon name="download" size="1.1rem" /><span>{{ t(v.factura?.urlPdf ? 'misCompras.boletoFactura' : 'misCompras.factura') }}</span>
              </Button>
            </template>
            <span v-else-if="!v.anulado" class="text-xs text-muted-color">{{ t('misCompras.sinFactura') }}</span>
          </div>
        </article>
      </section>

      <p class="m-0 text-xs text-muted-color">{{ t('misCompras.sinCancelar') }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import * as api from '@/api'
import HoraSubida from '@/componentes/HoraSubida.vue'
import { mensajeDeError } from '@/errores'
import { REGION, type Idioma } from '@/i18n'
import { guardarLlave, leerLlave } from '@/llave'
import { fechaCorta, hora } from '@/modelo'
import type { MisCompras, ViajeComprado } from '@/tipos'

const { t, te, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const cargando = ref(true)
const datos = ref<MisCompras | null>(null)
const error = ref('')
const aviso = ref('')
const correo = ref('')
const enviando = ref(false)
const enviado = ref(false)
const bajando = ref<number | null>(null)

const region = computed(() => REGION[locale.value as Idioma])
const fechaHora = (iso: string) => `${fechaCorta(iso, region.value)}, ${hora(iso, region.value)}`

const grupos = computed(() =>
  [
    { clave: 'futuros', titulo: t('misCompras.futuros'), icono: 'calendar', viajes: datos.value?.futuros ?? [] },
    { clave: 'pasados', titulo: t('misCompras.pasados'), icono: 'clock', viajes: datos.value?.pasados ?? [] },
  ].filter((g) => g.viajes.length),
)

const textoPolitica = computed(() => {
  const p = datos.value?.politica
  if (!p) return ''
  if (p.modificacionesPorAsiento === 0) return t('misCompras.politicaCerrada')
  const base = t('misCompras.politica', { minutos: p.minutosAntes, veces: p.modificacionesPorAsiento })
  if (!p.penalizacion || p.penalizacion.centavos === 0) return base
  return `${base} ${t(p.penalizacionPorAsiento ? 'misCompras.penalizacionPorAsiento' : 'misCompras.penalizacion', { monto: p.penalizacion.texto })}`
})

onMounted(async () => {
  // Enlace del correo: la llave pasa al navegador y sale de la URL.
  const deEnlace = typeof route.query.llave === 'string' ? route.query.llave : null
  if (deEnlace) {
    guardarLlave(deEnlace)
    await router.replace({ name: 'misCompras', params: { idioma: locale.value } })
  }
  await cargar(deEnlace ?? leerLlave())
})

async function cargar(llave: string | null) {
  cargando.value = true
  error.value = ''
  try {
    if (!llave) return
    const r = await api.misCompras(llave)
    guardarLlave(r.llave)
    datos.value = r
  } catch (e) {
    if (e instanceof api.ErrorPublico && e.estado === 401) {
      guardarLlave(null)
      aviso.value = mensajeDeError(e, t, te).titulo
    } else {
      // Se conserva la llave: fue un fallo de red o del servidor.
      aviso.value = mensajeDeError(e, t, te).titulo
    }
  } finally {
    cargando.value = false
  }
}

async function pedirEnlace() {
  enviando.value = true
  error.value = ''
  try {
    await api.pedirAcceso(correo.value.trim(), locale.value)
    enviado.value = true
  } catch (e) {
    error.value = mensajeDeError(e, t, te).titulo
  } finally {
    enviando.value = false
  }
}

function salir() {
  guardarLlave(null)
  datos.value = null
  aviso.value = ''
}

/** PDF del viaje (boletos con los datos de la factura electrónica). */
async function descargar(v: ViajeComprado) {
  const llave = leerLlave()
  if (!llave) return
  bajando.value = v.id
  error.value = ''
  try {
    const url = URL.createObjectURL(await api.pdfViaje(llave, v.id))
    const a = document.createElement('a')
    a.href = url
    a.download = `boleto_${v.codigoBarras}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch (e) {
    error.value = mensajeDeError(e, t, te).titulo
  } finally {
    bajando.value = null
  }
}
</script>
