<!-- Cuerpo de la tarjeta de un boleto: trayecto, estado, asiento, salida, pasajero y venta. -->
<template>
  <div class="flex flex-col gap-2 text-sm">
    <div class="flex items-start justify-between gap-2">
      <div class="font-semibold leading-snug">{{ d.trayecto ? `${d.trayecto.origen} → ${d.trayecto.destino}` : d.titulo }}</div>
      <Tag :value="estado.etiqueta" :severity="estado.severidad" class="!text-xs shrink-0" />
    </div>
    <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
      <template v-if="d.asiento"><dt>Asiento</dt><dd>{{ d.asiento.numero }} · clase {{ d.asiento.clase }}</dd></template>
      <template v-if="d.salida">
        <dt>Salida</dt>
        <dd>
          <router-link :to="{ name: 'salidas', query: { ver: d.salida.id } }" class="enlace">{{ d.salida.fecha ? fecha(d.salida.fecha) : `#${d.salida.id}` }}</router-link>
          <span v-if="d.salida.bus" class="text-muted-color"> · bus {{ d.salida.bus }}</span>
        </dd>
      </template>
      <template v-if="d.pasajero"><dt>Pasajero</dt><dd class="truncate">{{ d.pasajero.nombre }}<span v-if="d.pasajero.documento" class="text-muted-color"> · {{ d.pasajero.documento }}</span></dd></template>
      <template v-if="d.venta">
        <dt>Venta</dt>
        <dd>
          {{ d.precio?.texto ?? '—' }} · {{ CANALES[d.venta.canal] ?? d.venta.canal }}<template v-if="d.venta.lugar"> ({{ d.venta.lugar }})</template>
          <span v-if="d.venta.sinCobro" class="sin-cobro">{{ d.venta.sinCobro === 'cortesia' ? 'Cortesía' : 'Voucher' }}</span>
          <div v-if="d.venta.fecha" class="text-xs text-muted-color">{{ fecha(d.venta.fecha) }}<template v-if="d.venta.vendedor"> por {{ d.venta.vendedor }}</template></div>
        </dd>
      </template>
      <template v-if="d.observacion"><dt>Nota</dt><dd>{{ d.observacion }}</dd></template>
    </dl>
  </div>
</template>

<script setup lang="ts">
interface DatosBoleto {
  titulo: string
  estado: string
  asiento: { numero: number; clase: string } | null
  pasajero: { nombre: string; documento: string | null } | null
  trayecto: { origen: string; destino: string } | null
  salida: { id: number; fecha: string | null; bus: string | null } | null
  precio: { texto: string } | null
  venta: { id: number; canal: string; fecha: string | null; lugar: string | null; vendedor: string | null; sinCobro: 'cortesia' | 'voucher' | null } | null
  observacion: string | null
}

const props = defineProps<{ datos: unknown }>()
const d = computed(() => props.datos as DatosBoleto)

const ESTADOS: Record<string, { etiqueta: string; severidad: string }> = {
  emitido: { etiqueta: 'Emitido', severidad: 'info' },
  chequeado: { etiqueta: 'Chequeado', severidad: 'success' },
  transito: { etiqueta: 'En tránsito', severidad: 'warn' },
  finalizado: { etiqueta: 'Finalizado', severidad: 'secondary' },
  anulado: { etiqueta: 'Anulado', severidad: 'danger' },
  reasignado: { etiqueta: 'Reasignado', severidad: 'contrast' },
}
const CANALES: Record<string, string> = { estacion: 'Estación', agencia: 'Agencia', web: 'Página web' }
const estado = computed(() => ESTADOS[d.value.estado] ?? { etiqueta: d.value.estado, severidad: 'secondary' })
const fecha = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>

<style scoped>
dt {
  color: var(--p-surface-500);
  font-size: 0.78rem;
  padding-top: 0.05rem;
}
dd {
  margin: 0;
  min-width: 0;
}
.enlace {
  color: var(--p-primary-color);
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}
.sin-cobro {
  margin-left: 0.35rem;
  padding: 0 0.35rem;
  border-radius: 0.3rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--p-surface-700);
  background: var(--p-surface-100);
}
</style>
