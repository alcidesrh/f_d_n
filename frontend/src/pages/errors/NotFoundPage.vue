<template>
  <div class="min-h-screen flex flex-col items-center justify-center p-6 text-center">
    <div class="relative mb-6">
      <span class="text-9xl font-black text-primary-500/20 select-none">404</span>
      <div class="absolute inset-0 flex items-center justify-center">
        <div
          class="w-20 h-20 rounded-3xl bg-primary-600/10 dark:bg-primary-500/20 flex items-center justify-center text-primary-600 dark:text-primary-400"
        >
          <AppIcon name="alert" :size="48" />
        </div>
      </div>
    </div>

    <h1 class="text-3xl font-extrabold text-surface-900 dark:text-surface-0 tracking-tight mb-2">
      Página no encontrada
    </h1>
    <p class="text-surface-500 dark:text-surface-400 max-w-md mb-8 text-sm">
      Lo sentimos, la ruta que intentas acceder no existe o fue movida a otra ubicación dentro del
      sistema.
    </p>

    <div class="flex flex-wrap justify-center gap-4">
      <Button
        label="Volver al inicio"
        icon="pi pi-home"
        class="p-button-primary"
        @click="router.push('/')"
      />
      <Button
        label="Regresar"
        icon="pi pi-arrow-left"
        severity="secondary"
        outlined
        @click="router.back()"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
const router = useRouter()
</script>
