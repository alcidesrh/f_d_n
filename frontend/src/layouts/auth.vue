<template>
  <div
    class="min-h-screen w-full flex flex-col justify-between bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-50 transition-colors duration-300 relative overflow-hidden"
  >
    <!-- Ambient Background Blobs -->
    <div
      class="absolute -top-32 -left-32 w-96 h-96 bg-primary-500/10 rounded-full blur-3xl pointer-events-none"
    ></div>
    <div
      class="absolute -bottom-32 -right-32 w-96 h-96 bg-primary-600/10 rounded-full blur-3xl pointer-events-none"
    ></div>

    <!-- Header / Brand Bar -->
    <header class="w-full max-w-7xl mx-auto px-6 py-4 flex items-center justify-between z-10">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white font-bold text-xl shadow-md"
        >
          F
        </div>
        <div class="flex flex-col">
          <span class="font-bold text-lg leading-tight tracking-tight">FDN</span>
          <span class="text-xs text-surface-500 font-medium">Flotas de la Nación</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          class="p-2.5 rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-800 transition-colors cursor-pointer"
          title="Cambiar modo"
          @click="ui.setMode(ui.mode === 'light' ? 'dark' : 'light')"
        >
          <AppIcon :name="ui.mode === 'light' ? 'moon' : 'sun'" :size="18" />
        </button>
      </div>
    </header>

    <!-- Main Content (Slot for Login/Register Pages) -->
    <main class="flex-1 flex items-center justify-center px-4 py-8 z-10 w-full">
      <div class="w-full max-w-md">
        <slot />
      </div>
    </main>

    <!-- Footer -->
    <footer
      class="w-full max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-center justify-between text-xs text-surface-500 gap-2 z-10"
    >
      <div>&copy; {{ currentYear }} Flotas de la Nación. Todos los derechos reservados.</div>
      <div class="flex items-center gap-4">
        <a href="#" class="hover:underline hover:text-surface-700 dark:hover:text-surface-300"
          >Términos</a
        >
        <a href="#" class="hover:underline hover:text-surface-700 dark:hover:text-surface-300"
          >Privacidad</a
        >
        <a href="#" class="hover:underline hover:text-surface-700 dark:hover:text-surface-300"
          >Soporte</a
        >
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
const currentYear = new Date().getFullYear();

watchEffect(() => {
  const el = document.documentElement;
  el.className = ui.mode;
  el.classList.add(`primary-${ui.primary}`);
  el.classList.add(`surface-${ui.surface}`);
});
</script>
