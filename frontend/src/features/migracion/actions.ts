/** Acciones sobre jobs con su notificación (usadas por página y panel). */
import { notify } from '@/core/notify'
import { useMigracionStore } from './store'
import type { JobMigracion, PayloadEjecutar } from './types'

async function withNotify(
  action: () => Promise<unknown>,
  success: string,
  kind: 'success' | 'info' = 'success',
) {
  try {
    await action()
    notify[kind](success)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  }
}

export const iniciarJob = (payload: PayloadEjecutar) =>
  withNotify(
    () => useMigracionStore().arrancarJob(payload),
    'Migración iniciada. Seguí el avance en la consola.',
  )

export const reejecutarJob = (job: JobMigracion) =>
  withNotify(
    () => useMigracionStore().reejecutar(job),
    'Migración re-lanzada con los mismos parámetros.',
  )

export const cancelarJob = () =>
  withNotify(
    () => useMigracionStore().cancelarActual(),
    'Cancelación solicitada. El proceso la respetará en la próxima iteración.',
    'info',
  )
