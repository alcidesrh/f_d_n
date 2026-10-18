<!--
  Registra el service worker de la PWA y avisa cuando hay una versión nueva.
  No se aplica sola: recargar en medio de una venta la perdería. Busca
  versiones nuevas cada hora (una taquilla puede pasar días abierta).
-->
<template>
  <Transition name="actualizacion">
    <div v-if="needRefresh" class="actualizacion" role="status">
      <icon name="system-update-alt" size="1.25rem" color="text-current" />
      <span class="flex-1 text-sm">Hay una versión nueva de la app.</span>
      <Button label="Después" size="small" text @click="needRefresh = false" />
      <Button label="Actualizar" size="small" @click="void updateServiceWorker(true)" />
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue'

const UNA_HORA = 60 * 60 * 1000

const { needRefresh, updateServiceWorker } = useRegisterSW({
  onRegisteredSW(_url, registro) {
    if (registro) setInterval(() => void registro.update(), UNA_HORA)
  },
})
</script>

<style scoped>
.actualizacion {
  position: fixed;
  left: 50%;
  bottom: calc(1rem + env(safe-area-inset-bottom));
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: min(28rem, calc(100% - 2rem));
  padding: 0.5rem 0.5rem 0.5rem 1rem;
  transform: translateX(-50%);
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  background: var(--p-content-background);
  color: var(--p-text-color);
  box-shadow: var(--shadow);
}
.actualizacion-enter-active,
.actualizacion-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.actualizacion-enter-from,
.actualizacion-leave-to {
  opacity: 0;
  transform: translate(-50%, 0.5rem);
}
</style>
