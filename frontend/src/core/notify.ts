/**
 * Notificaciones globales (toasts). Se pueden emitir desde cualquier módulo:
 * `notify.success("Guardado")`. `<Toasts />` pinta `toasts`.
 *
 * `info`/`success`/`warning` se cierran solas; `error` queda hasta que el
 * usuario la cierra. El push se difiere a un macrotask: si se notifica durante
 * un render que termina en error, Vue descartaría el re-render de `<Toasts />`.
 */
import { reactive } from 'vue'

export type ToastType = 'info' | 'success' | 'warning' | 'error'

export interface Toast {
  id: number
  type: ToastType
  text: string
  sticky: boolean
}

const DEFAULT_DURATION = 3500

export const toasts = reactive<Toast[]>([])
const timers = new Map<number, ReturnType<typeof setTimeout>>()
let nextId = 1

export function dismiss(id: number) {
  clearTimeout(timers.get(id))
  timers.delete(id)
  const index = toasts.findIndex((toast) => toast.id === id)
  if (index !== -1) toasts.splice(index, 1)
}

export function dismissAll() {
  while (toasts.length > 0) dismiss(toasts[0]!.id)
}

function push(type: ToastType, text: string, duration = DEFAULT_DURATION) {
  const id = nextId++
  const sticky = type === 'error'
  setTimeout(() => {
    toasts.push({ id, type, text, sticky })
    if (!sticky)
      timers.set(
        id,
        setTimeout(() => dismiss(id), duration),
      )
  }, 0)
  return id
}

export const notify = {
  info: (text: string, duration?: number) => push('info', text, duration),
  success: (text: string, duration?: number) => push('success', text, duration),
  warning: (text: string, duration?: number) => push('warning', text, duration),
  error: (text: string) => push('error', text),
}
