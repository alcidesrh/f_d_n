<!--
  Aviso fijo mientras un administrador del sistema usa la cuenta de otro
  usuario (suplantación): de quién es la cuenta, cuánto le queda y «Volver a
  mi cuenta». Lo monta el shell. Al recargar tras una suplantación vencida,
  avisa que se volvió a la cuenta propia.
-->
<template>
  <div v-if="session.suplantando" class="suplantacion-aviso" role="status" aria-live="polite">
    <icon name="supervisor-account-outline" class="shrink-0" />
    <span class="min-w-0 flex-1 text-sm">
      Está usando la cuenta de <strong>{{ session.user }}</strong>
      <span v-if="vencimiento" class="opacity-80"> · {{ vencimiento }}</span>
      <span v-if="soloLectura" class="opacity-80"> · chat y correo {{ soloLectura }}</span>
    </span>
    <Button
      size="small"
      label="Volver a mi cuenta"
      :loading="volviendo"
      class="shrink-0"
      @click="volver"
    >
      <template #icon><icon name="logout" class="mr-1 text-inherit" /></template>
    </Button>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAccesoStore } from '@/core/acceso/store'
import { useSessionStore } from '@/core/auth/session'
import { AVISO_SUPLANTACION_VENCIDA } from '@/core/auth/unauthorized'
import { notify } from '@/core/notify'
import { minutosRestantes, textoVencimiento } from '@/core/suplantacion/reglas'

const session = useSessionStore()
const acceso = useAccesoStore()
const ahora = ref(Date.now())
const volviendo = ref(false)

const suplantacion = computed(() => acceso.acceso?.suplantacion ?? null)
const vencimiento = computed(() =>
  textoVencimiento(minutosRestantes(suplantacion.value?.expira, ahora.value)),
)
const soloLectura = computed(() =>
  suplantacion.value?.chat === 'lectura'
    ? 'en solo lectura'
    : suplantacion.value?.chat === 'bloquear'
      ? 'no disponibles'
      : '',
)

async function volver() {
  volviendo.value = true
  try {
    await session.terminarSuplantacion()
  } finally {
    volviendo.value = false
  }
}

let reloj: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  reloj = setInterval(() => (ahora.value = Date.now()), 30_000)
  if (sessionStorage.getItem(AVISO_SUPLANTACION_VENCIDA)) {
    sessionStorage.removeItem(AVISO_SUPLANTACION_VENCIDA)
    notify.info('La suplantación terminó: volvió a su cuenta.')
  }
})
onBeforeUnmount(() => clearInterval(reloj))
</script>

<style scoped>
.suplantacion-aviso {
  position: fixed;
  z-index: 1000;
  left: 1rem;
  right: 1rem;
  bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 0.5rem 0.75rem;
  border-radius: 0.75rem;
  border: 1px solid var(--c-warning);
  background: var(--p-amber-100);
  color: var(--p-amber-900);
  box-shadow: 0 6px 20px rgb(0 0 0 / 0.18);
}
@media (min-width: 768px) {
  .suplantacion-aviso {
    right: auto;
    max-width: min(40rem, calc(100vw - 2rem));
  }
}
</style>
