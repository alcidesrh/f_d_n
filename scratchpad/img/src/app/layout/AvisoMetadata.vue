<!--
  Un administrador publicó cambios de configuración con aviso (ADR-036): lo
  dice con su nota y ofrece recargar. Como el de versión nueva, nunca recarga
  solo (se perdería una venta a medias); lo que no está abierto ya se puso al
  día solo.
-->
<template>
  <Transition name="aviso-metadata">
    <div v-if="metadata.aviso" class="aviso-metadata" role="status">
      <icon name="published-with-changes" size="1.25rem" color="text-current" />
      <span class="min-w-0 flex-1 text-sm">
        <span class="font-semibold">Hay cambios en la configuración.</span>
        {{ metadata.aviso.nota ?? 'Recarga para verlos en todas las pantallas.' }}
      </span>
      <Button label="Después" size="small" text @click="metadata.descartarAviso()" />
      <Button label="Recargar" size="small" @click="recargar" />
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { useMetadataStore } from '@/core/metadata/sincronizacion'

const metadata = useMetadataStore()

function recargar() {
  window.location.reload()
}
</script>

<style scoped>
.aviso-metadata {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.5rem 0.5rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
  background: var(--p-content-background);
  color: var(--p-text-color);
  box-shadow: var(--shadow);
}
.aviso-metadata-enter-active,
.aviso-metadata-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}
.aviso-metadata-enter-from,
.aviso-metadata-leave-to {
  opacity: 0;
  transform: translateY(0.5rem);
}
</style>
