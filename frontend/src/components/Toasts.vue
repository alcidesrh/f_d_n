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
        class="left-0 toast relative pointer-events-auto flex w-full items-start gap-3 rounded-lg border px-4 py-3 pt-6 shadow-lg"
        role="alert"
      >
        <icon @click="remove(toast.id)" name="x" class="absolute right-0 top-0 m-2" size="1.2rem" />
        <icon :name="`${iconFor(toast.type)}`" size="1.2rem" />
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
import { useToasts, type ToastType } from "@/composables/useToasts";

defineOptions({ name: "Toasts" });

const { toasts, remove } = useToasts();

function iconFor(type: ToastType): string {
  switch (type) {
    case "info":
      return "info-circle";
    case "success":
      return "circle-dashed-check";
    case "warning":
      return "alert-triangle";
    case "error":
      return "exclamation-circle";
  }
}

function toneClass(type: ToastType): string {
  switch (type) {
    case "info":
      return "info bg-sky-50/30 backdrop-blur-[7px] border-sky-200 text-sky-800";
    case "success":
      return "success bg-emerald-50/30 backdrop-blur-[7px] border-emerald-200 text-emerald-800";
    case "warning":
      return "warning bg-amber-50/30 backdrop-blur-[7px] border-amber-200 text-amber-800";
    case "error":
      return "error bg-red-50/30 border-red-200 text-red-800 backdrop-blur-[7px]";
  }
}

function position() {}
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
