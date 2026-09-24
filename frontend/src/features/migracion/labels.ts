/** Textos y formato de la pantalla de migración (compartidos por página y panel). */
import type { ConteoMigracion, EstadoJobMigracion, JobMigracion, TipoJobMigracion } from './types'

const TIPOS: Record<TipoJobMigracion, string> = {
  reset: 'Reset duro',
  truncar: 'Truncar tablas',
  estaticos: 'Estáticos',
  entidad: 'Entidad',
  iam: 'IAM',
  config: 'Configuración',
  todo: 'Migración completa',
}

const ESTADOS: Record<
  EstadoJobMigracion,
  { label: string; severity: 'success' | 'info' | 'warn' | 'danger' }
> = {
  pending: { label: 'En cola', severity: 'warn' },
  running: { label: 'Ejecutando', severity: 'info' },
  done: { label: 'Completado', severity: 'success' },
  cancelado: { label: 'Cancelado', severity: 'warn' },
  error: { label: 'Error', severity: 'danger' },
  abortado: { label: 'Abortado', severity: 'danger' },
}

export const formatearNumero = (n: number) => new Intl.NumberFormat('es-AR').format(n)

export function etiquetaTipo(job: { tipo: TipoJobMigracion; entidad: string | null }): string {
  return job.entidad ? `${TIPOS[job.tipo]} → ${job.entidad}` : TIPOS[job.tipo]
}

export const etiquetaEstado = (estado: string) =>
  ESTADOS[estado as EstadoJobMigracion]?.label ?? estado

export const severidadEstado = (estado: string) =>
  ESTADOS[estado as EstadoJobMigracion]?.severity ?? 'secondary'

/** Avance de la migración de una entidad: registros nuevos sobre los del legado. */
export function porcentajeNuevo(conteo: ConteoMigracion): number {
  if (conteo.legado <= 0) return conteo.nuevo > 0 ? 100 : 0
  return Math.min(100, Math.round((conteo.nuevo / conteo.legado) * 100))
}

export function porcentajeProgreso(job: JobMigracion): number {
  return job.total && job.total > 0
    ? Math.min(100, Math.round((job.procesados / job.total) * 100))
    : 0
}
