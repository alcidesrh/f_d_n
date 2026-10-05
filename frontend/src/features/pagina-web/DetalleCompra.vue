<!-- Detalle de una compra web: pago, comprador, ventas y facturas. -->
<template>
  <div class="flex flex-col gap-4 text-sm">
    <div class="flex flex-wrap items-center gap-2">
      <Tag :value="etiquetaEstado(compra.estado).etiqueta" :severity="etiquetaEstado(compra.estado).severidad" />
      <span class="text-lg font-bold tabular-nums">{{ compra.monto.texto }}</span>
      <span v-if="Number(compra.recargoPorciento) > 0" class="text-muted-color">(incluye +{{ Number(compra.recargoPorciento) }} % web)</span>
    </div>
    <Message v-if="compra.mensaje" :severity="compra.estado === 'completado' ? 'info' : 'warn'" :closable="false">{{ compra.mensaje }}</Message>

    <dl class="m-0 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
      <div><dt class="text-muted-color">Comprador</dt><dd class="m-0 font-medium">{{ compra.comprador.nombre || '—' }}</dd></div>
      <div><dt class="text-muted-color">NIT</dt><dd class="m-0">{{ compra.comprador.nit }}</dd></div>
      <div><dt class="text-muted-color">Correo</dt><dd class="m-0"><a v-if="compra.comprador.email" :href="`mailto:${compra.comprador.email}`">{{ compra.comprador.email }}</a></dd></div>
      <div><dt class="text-muted-color">Teléfono</dt><dd class="m-0">{{ compra.comprador.telefono || '—' }}</dd></div>
      <div><dt class="text-muted-color">Tarjeta</dt><dd class="m-0">{{ compra.tarjeta.marca }} ···· {{ compra.tarjeta.ultimos4 }}</dd></div>
      <div><dt class="text-muted-color">Autorización</dt><dd class="m-0 font-mono">{{ compra.autorizacion || '—' }}</dd></div>
      <div><dt class="text-muted-color">Referencia de la pasarela</dt><dd class="m-0 break-all font-mono text-xs">{{ compra.referencia || '—' }}</dd></div>
      <div><dt class="text-muted-color">Comercio que cobró</dt><dd class="m-0">{{ compra.empresa?.nombre ?? '—' }}</dd></div>
      <div><dt class="text-muted-color">Iniciada</dt><dd class="m-0">{{ fechaHora(compra.creado) }}</dd></div>
      <div><dt class="text-muted-color">Último cambio</dt><dd class="m-0">{{ fechaHora(compra.actualizado) }}</dd></div>
    </dl>

    <section v-for="(v, i) in compra.ventas" :key="v.id" class="rounded-lg border border-surface-200 p-3">
      <h3 class="m-0 mb-2 text-sm font-semibold">{{ compra.ventas.length > 1 ? (i === 0 ? 'Ida' : 'Regreso') + ' · ' : '' }}Venta {{ v.codigo }}</h3>
      <dl class="m-0 grid grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2">
        <div><dt class="text-muted-color">Viaje</dt><dd class="m-0">{{ v.origen }} → {{ v.destino }}</dd></div>
        <div><dt class="text-muted-color">Salida</dt><dd class="m-0">{{ fechaHora(v.salida) }} · {{ v.empresa }}</dd></div>
        <div><dt class="text-muted-color">Asientos</dt><dd class="m-0">{{ v.asientos }} ({{ v.cantidad }})</dd></div>
        <div><dt class="text-muted-color">Total</dt><dd class="m-0">{{ v.total.texto }}</dd></div>
        <div class="sm:col-span-2">
          <dt class="text-muted-color">Factura</dt>
          <dd class="m-0">
            <template v-if="v.factura">Serie {{ v.factura.serie }} · DTE {{ v.factura.numero }} <a v-if="v.factura.urlPdf" :href="v.factura.urlPdf" target="_blank" rel="noopener">ver</a></template>
            <template v-else>
              <Tag :value="v.facturacion === 'pendiente' ? 'Pendiente' : 'No aplica'" :severity="v.facturacion === 'pendiente' ? 'warn' : 'secondary'" />
              <span v-if="v.errorFacturacion" class="ml-1 text-xs text-muted-color">{{ v.errorFacturacion }}</span>
            </template>
          </dd>
        </div>
      </dl>
    </section>

    <a v-if="compra.ventas.length" :href="`/api/publico/compras/${compra.token}/boleto.pdf?ver=1`" target="_blank" rel="noopener" class="self-start no-underline">
      <Button label="Ver boleto (PDF)" severity="secondary" outlined size="small"><template #icon><icon name="picture-as-pdf-outline" class="mr-1" /></template></Button>
    </a>
  </div>
</template>

<script setup lang="ts">
import { etiquetaEstado } from '@/core/pagina-web/filtro'
import type { CompraWeb } from '@/core/pagina-web/types'

defineProps<{ compra: CompraWeb }>()
const fechaHora = (iso: string) =>
  new Intl.DateTimeFormat('es-GT', { day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>
