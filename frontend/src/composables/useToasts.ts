/**
 * Toasts globales agnósticos (no atados a PrimeVue). Estado compartido entre
 * módulos: cualquier componente puede emitir un toast con `useToasts()`.
 *
 * Modos: `info`, `success`, `warning` (auto-dismiss en `duration` ms) y
 * `error` (no desaparece solo; el usuario debe cerrarlo).
 */

import { reactive } from 'vue'

export type ToastType = 'info' | 'success' | 'warning' | 'error'

export interface ToastMessage {
  id: number
  type: ToastType
  text: string
  sticky: boolean
}

const DEFAULT_DURATION = 3500

const state = reactive<{ toasts: ToastMessage[] }>({ toasts: [] })

let nextId = 1
const timers = new Map<number, ReturnType<typeof setTimeout>>()

function push(
  type: ToastType,
  text: string,
  opts: { duration?: number; sticky?: boolean } = {},
): number {
  const id = nextId++
  const sticky = opts.sticky ?? type === 'error'
  state.toasts.push({ id, type, text, sticky })
  if (!sticky) {
    timers.set(
      id,
      setTimeout(() => remove(id), opts.duration ?? DEFAULT_DURATION),
    )
  }
  return id
}

function remove(id: number) {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
  const index = state.toasts.findIndex((toast) => toast.id === id)
  if (index !== -1) state.toasts.splice(index, 1)
}

function clear() {
  timers.forEach((timer) => clearTimeout(timer))
  timers.clear()
  state.toasts.splice(0, state.toasts.length)
}

export function useToasts() {
  return {
    toasts: state.toasts,
    push,
    remove,
    clear,
    info: (text: string, opts?: { duration?: number }) => push('info', text, opts),
    success: (text: string, opts?: { duration?: number }) => push('success', text, opts),
    warning: (text: string, opts?: { duration?: number }) => push('warning', text, opts),
    error: (text: string) => push('error', text),
  }
}
