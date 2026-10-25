<template>
  <!-- Sincronización con el sistema anterior (ADR-042): encendida se ve siempre; apagada, solo quien puede encenderla. -->
  <template v-if="mostrar && estado">
    <button
      type="button"
      class="sinc tap-target"
      :class="`sinc--${tono}`"
      :aria-label="ETIQUETA[tono]"
      aria-haspopup="dialog"
      v-tooltip.bottom="ETIQUETA[tono]"
      @click="detalle?.toggle($event)"
    >
      <span class="sinc__punto" aria-hidden="true"></span>
      <icon :name="estado.activo ? 'sync' : 'sync-disabled'" size="1.05rem" />
      <span class="sinc__texto">{{ estado.activo ? 'Sinc.' : 'Sinc. apagada' }}</span>
    </button>
    <Popover ref="detalle">
      <div class="sinc-detalle">
        <div class="font-semibold">{{ ETIQUETA[tono] }}</div>
        <p class="text-sm text-muted-color">
          Lo que se vende, anula o registra aquí se escribe también en el sistema anterior, y lo de
          allá llega aquí en un minuto como mucho.
        </p>
        <ul v-if="lineas.length" class="sinc-detalle__avisos">
          <li v-for="l in lineas" :key="l">{{ l }}</li>
        </ul>
        <dl class="sinc-detalle__datos">
          <template v-if="estado.ultimoCicloEn">
            <dt>Último ciclo</dt>
            <dd>{{ fecha(estado.ultimoCicloEn) }}</dd>
          </template>
          <template v-if="estado.cambiadoEn">
            <dt>{{ estado.activo ? 'Encendida' : 'Apagada' }}</dt>
            <dd>
              {{ fecha(estado.cambiadoEn)
              }}{{ estado.cambiadoPor ? ` por ${estado.cambiadoPor}` : '' }}
            </dd>
          </template>
        </dl>
        <Button
          v-if="puedeCambiar"
          :label="estado.activo ? 'Apagar sincronización' : 'Encender sincronización'"
          :severity="estado.activo ? 'danger' : 'success'"
          size="small"
          :loading="cambiando"
          @click="alternar"
        />
      </div>
    </Popover>
  </template>
</template>

<script setup lang="ts">
import type Popover from 'primevue/popover'
import { useConfirm } from 'primevue/useconfirm'
import { useAccesoStore } from '@/core/acceso/store'
import { notify } from '@/core/notify'
import { cambiar, fetchEstado } from '@/core/sincronizacion/api'
import {
  avisos,
  ETIQUETA,
  nivel,
  visible,
  type EstadoSincronizacion,
} from '@/core/sincronizacion/reglas'

const CADA_MS = 60_000

const acceso = useAccesoStore()
const confirm = useConfirm()
const estado = ref<EstadoSincronizacion | null>(null)
const ahora = ref(new Date())
const cambiando = ref(false)
const detalle = ref<InstanceType<typeof Popover> | null>(null)

const puedeCambiar = computed(() => acceso.puede('config.editar'))
const mostrar = computed(() => visible(estado.value, puedeCambiar.value))
const tono = computed(() => (estado.value ? nivel(estado.value, ahora.value) : 'apagada'))
const lineas = computed(() => (estado.value ? avisos(estado.value, ahora.value) : []))

async function cargar() {
  try {
    estado.value = await fetchEstado()
    ahora.value = new Date()
  } catch {
    // Sin el módulo (ya quitado) o sin conexión: el indicador no se muestra.
    estado.value = null
  }
}

function alternar() {
  const encender = !estado.value?.activo
  confirm.require({
    header: encender ? 'Encender sincronización' : 'Apagar sincronización',
    message: encender
      ? 'Las ventas de aquí se escribirán también en el sistema anterior y las de allá llegarán aquí. ¿Encender?'
      : 'Las ventas dejarán de comprobarse en el otro sistema: el mismo asiento podría venderse dos veces. ¿Apagar?',
    acceptLabel: encender ? 'Encender' : 'Apagar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      cambiando.value = true
      try {
        const r = await cambiar(encender)
        estado.value = r
        if (r.aviso) notify.warning(r.aviso)
        else notify.success(encender ? 'Sincronización encendida.' : 'Sincronización apagada.')
      } catch (e) {
        notify.error(e instanceof Error ? e.message : 'No se pudo cambiar la sincronización.')
      } finally {
        cambiando.value = false
      }
    },
  })
}

const fecha = (iso: string) => new Date(iso).toLocaleString()

let timer: ReturnType<typeof setInterval>
onMounted(() => {
  void cargar()
  timer = setInterval(() => void cargar(), CADA_MS)
})
onUnmounted(() => clearInterval(timer))
</script>

<style scoped>
.sinc {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  border: 1px solid var(--sinc-color);
  color: var(--sinc-color);
  background: color-mix(in srgb, var(--sinc-color) 10%, transparent);
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}
.sinc--bien {
  --sinc-color: var(--p-green-600);
}
.sinc--atencion {
  --sinc-color: var(--p-amber-600);
}
.sinc--error {
  --sinc-color: var(--p-red-600);
}
.sinc--apagada {
  --sinc-color: var(--p-surface-500);
}
.sinc__punto {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--sinc-color);
}
.sinc--bien .sinc__punto {
  animation: sinc-latido 2s ease-in-out infinite;
}
@keyframes sinc-latido {
  50% {
    opacity: 0.3;
  }
}
@media (max-width: 640px) {
  .sinc__texto {
    display: none;
  }
}
.sinc-detalle {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  max-width: 22rem;
}
.sinc-detalle__avisos {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.85rem;
  color: var(--p-amber-700);
}
.sinc-detalle__datos {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.2rem 0.75rem;
  margin: 0;
  font-size: 0.85rem;
}
.sinc-detalle__datos dt {
  color: var(--p-text-muted-color);
}
.sinc-detalle__datos dd {
  margin: 0;
}
</style>
