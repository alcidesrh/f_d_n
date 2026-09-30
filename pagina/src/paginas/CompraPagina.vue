<!--
  Resultado de la compra (también es a donde vuelve el banco tras 3-D
  Secure). Si se completó, descarga el boleto en PDF y ofrece el botón.
-->
<template>
  <div class="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-8">
    <Skeleton v-if="cargando" height="16rem" border-radius="1rem" />

    <div v-else-if="compra" class="panel flex flex-col gap-4">
      <div class="flex items-start gap-3">
        <icon name="check" size="2.5rem" class="shrink-0 text-green-600" />
        <div>
          <h1 class="m-0 text-2xl font-semibold">¡Compra realizada!</h1>
          <p class="m-0 mt-1 text-muted-color">
            Enviamos su boleto a <b>{{ compra.cliente?.email }}</b>. Preséntelo (impreso o en su teléfono) al abordar.
          </p>
        </div>
      </div>

      <dl class="m-0 grid grid-cols-1 gap-x-6 gap-y-2 rounded-xl bg-surface-50 p-4 text-sm md:grid-cols-2">
        <div><dt class="text-muted-color">Viaje</dt><dd class="m-0 font-medium">{{ compra.origen?.nombre }} → {{ compra.destino?.nombre }}</dd></div>
        <div><dt class="text-muted-color">Salida</dt><dd class="m-0 font-medium">{{ fechaLarga(compra.recorrido?.salidaOrigen) }}, {{ hora(compra.recorrido?.salidaOrigen) }}</dd></div>
        <div><dt class="text-muted-color">Asiento(s)</dt><dd class="m-0 font-medium">{{ compra.boletos.map((b) => b.asiento).join(', ') }}</dd></div>
        <div><dt class="text-muted-color">Total pagado</dt><dd class="m-0 font-medium">{{ compra.total.texto }}</dd></div>
        <div><dt class="text-muted-color">Número de compra</dt><dd class="m-0 font-mono">{{ compra.codigoBarras }}</dd></div>
        <div>
          <dt class="text-muted-color">Factura</dt>
          <dd class="m-0 font-medium">
            <template v-if="compra.factura">
              DTE {{ compra.factura.numero }} · serie {{ compra.factura.serie }}
              <a v-if="compra.factura.urlPdf" :href="compra.factura.urlPdf" target="_blank" rel="noopener" class="ml-1">ver factura</a>
            </template>
            <template v-else>En proceso: le llegará por correo.</template>
          </dd>
        </div>
      </dl>

      <div class="flex flex-wrap gap-2">
        <a :href="api.urlBoletoPdf(token)" download class="no-underline">
          <Button label="Descargar boleto (PDF)">
            <template #icon><icon name="download" class="mr-1" /></template>
          </Button>
        </a>
        <a :href="api.urlBoletoPdf(token, true)" target="_blank" rel="noopener" class="no-underline">
          <Button label="Ver boleto" severity="secondary" outlined />
        </a>
        <RouterLink :to="{ name: 'inicio' }" class="no-underline">
          <Button label="Comprar otro viaje" severity="secondary" text />
        </RouterLink>
      </div>
      <p class="m-0 text-xs text-muted-color">
        No es posible el reembolso ni la modificación de este boleto en línea. Para cambios de fecha, acérquese a una estación hasta 3 horas antes de la salida.
      </p>
    </div>

    <div v-else class="panel flex flex-col gap-3">
      <div class="flex items-start gap-3">
        <icon name="alert" size="2rem" class="shrink-0 text-orange-500" />
        <div>
          <h1 class="m-0 text-xl font-semibold">No se completó la compra</h1>
          <p class="m-0 mt-1">{{ mensaje }}</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink v-if="!carrito.vacio" :to="{ name: 'pago' }" class="no-underline"><Button label="Intentar de nuevo" /></RouterLink>
        <RouterLink :to="{ name: 'inicio' }" class="no-underline"><Button label="Buscar otra salida" severity="secondary" outlined /></RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import * as api from '@/api'
import { useCarrito } from '@/carrito'
import { fechaLarga, hora } from '@/modelo'
import type { Compra } from '@/tipos'

const props = defineProps<{ token: string; error: string | null }>()
const carrito = useCarrito()
const compra = ref<Compra | null>(null)
const mensaje = ref(props.error ?? '')
const cargando = ref(true)

onMounted(async () => {
  try {
    const r = await api.compra(props.token)
    if (r.estado === 'completado' && r.compra) {
      compra.value = r.compra
      if (carrito.token === props.token) carrito.olvidar()
      descargarUnaVez()
    } else {
      mensaje.value ||= r.mensaje ?? 'El pago no se completó. No se realizó ningún cobro.'
      await carrito.refrescar()
    }
  } catch (e) {
    mensaje.value ||= e instanceof Error ? e.message : String(e)
  } finally {
    cargando.value = false
  }
})

/** Fuerza la descarga del PDF la primera vez que se ve la compra. */
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
  a.download = `boleto_${compra.value?.codigoBarras ?? ''}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()
}
</script>
