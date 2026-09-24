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
        :class="TONES[toast.type]"
        class="left-0 toast relative backdrop-blur-[7px] pointer-events-auto flex w-full items-start gap-3 rounded-lg border px-4 py-3 pt-6 shadow-lg"
        role="alert"
      >
        <icon
          @click="dismiss(toast.id)"
          name="x"
          class="absolute right-0 top-0 m-2"
          size="1.2rem"
        />
        <icon :name="ICONS[toast.type]" size="1.2rem" />
        <span class="flex-1 text-sm leading-snug">{{ toast.text }}</span>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { dismiss, toasts, type ToastType } from '@/core/notify'

const ICONS: Record<ToastType, string> = {
  info: 'info-circle',
  success: 'circle-dashed-check',
  warning: 'alert-triangle',
  error: 'exclamation-circle',
}

const TONES: Record<ToastType, string> = {
  info: 'info bg-sky-50/30 border-sky-200 text-sky-800',
  success: 'success bg-emerald-50/30 border-emerald-200 text-emerald-800',
  warning: 'warning bg-amber-50/30 border-amber-200 text-amber-800',
  error: 'error bg-red-50/30 border-red-200 text-red-800',
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
.toast.error svg {
  color: var(--p-red-800);
}
.toast.warning svg {
  color: var(--p-amber-800);
}
.toast.success svg {
  color: var(--p-emerald-800);
}
.toast.info svg {
  color: var(--p-sky-800);
}
</style>
