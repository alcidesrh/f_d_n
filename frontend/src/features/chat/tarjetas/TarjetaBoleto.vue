<!-- Cuerpo de la tarjeta de un boleto: trayecto, estado, asiento, salida, cliente, cuándo y quién lo vendió. -->
<template>
  <div class="flex flex-col gap-2 text-sm">
    <div class="flex items-start justify-between gap-2">
      <div class="font-semibold leading-snug">{{ d.trayecto ? `${d.trayecto.origen} → ${d.trayecto.destino}` : d.titulo }}</div>
      <Tag :value="estado.etiqueta" :severity="estado.severidad" class="!text-xs shrink-0" />
    </div>
    <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
      <template v-if="d.asiento != null"><dt>Asiento</dt><dd class="tabular-nums">{{ d.asiento }}</dd></template>
      <template v-if="d.salida">
        <dt>Salida</dt>
        <dd class="first-letter:uppercase">
          <router-link :to="{ name: 'salidas', query: { ver: d.salida.id } }" class="enlace">{{ d.salida.fecha ? fecha(d.salida.fecha) : `#${d.salida.id}` }}</router-link>
        </dd>
      </template>
      <template v-if="d.cliente"><dt>Cliente</dt><dd class="truncate">{{ d.cliente }}</dd></template>
      <template v-if="d.creado || d.vendedor">
        <dt>Vendido</dt>
        <dd><template v-if="d.creado">{{ fecha(d.creado) }}</template><template v-if="d.vendedor"> por {{ d.vendedor }}</template></dd>
      </template>
    </dl>
  </div>
</template>

<script setup lang="ts">
interface DatosBoleto {
  titulo: string
  estado: string
  asiento: number | null
  cliente: string | null
  trayecto: { origen: string; destino: string } | null
  salida: { id: number; fecha: string | null } | null
  /** Cuándo se vendió (la venta que lo emitió). */
  creado: string | null
  vendedor: string | null
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
</style>
