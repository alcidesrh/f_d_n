<template>
  <Teleport to="body">
    <TransitionGroup
      name="toast"
      tag="div"
      class="pointer-events-none fixed left-1/2 top-4 z-50 flex w-full max-w-md -translate-x-1/2 flex-col items-center gap-2 px-4"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="toneClass(toast.type)"
        class="pointer-events-auto flex w-full items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
        role="alert"
      >
        <i :class="`pi ${iconFor(toast.type)} mt-0.5`" />
        <span class="flex-1 text-sm leading-snug">{{ toast.text }}</span>
        <button
          v-if="toast.sticky"
          :aria-label="'Cerrar notificación'"
          class="text-current/70 hover:text-current transition-colors"
          @click="remove(toast.id)"
        >
          <i class="pi pi-times text-xs" />
        </button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { useToasts, type ToastType } from '@/composables/useToasts'

defineOptions({ name: 'Toasts' })

const { toasts, remove } = useToasts()

function iconFor(type: ToastType): string {
  switch (type) {
    case 'info':
      return 'pi-info-circle'
    case 'success':
      return 'pi-check-circle'
    case 'warning':
      return 'pi-exclamation-triangle'
    case 'error':
      return 'pi-times-circle'
  }
}

function toneClass(type: ToastType): string {
  switch (type) {
    case 'info':
      return 'bg-sky-50 border-sky-200 text-sky-800'
    case 'success':
      return 'bg-emerald-50 border-emerald-200 text-emerald-800'
    case 'warning':
      return 'bg-amber-50 border-amber-200 text-amber-800'
    case 'error':
      return 'bg-red-50 border-red-200 text-red-800'
  }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.toast-move {
  transition: transform 0.25s ease;
}
</style>
