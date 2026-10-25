/**
 * Reglas puras del indicador de la sincronización con el sistema anterior
 * (ADR-042): qué se muestra en la cabecera y con qué tono.
 */

export interface EstadoSincronizacion {
  activo: boolean
  cambiadoEn: string | null
  cambiadoPor: string | null
  ultimoCicloEn: string | null
  ultimoCiclo: Record<string, unknown> | null
  ultimoError: string | null
  /** Cambios de aquí que esperan llegar al sistema anterior. */
  pendientes: number
  /** Solo trae del sistema anterior; nada de aquí se escribe allá (desarrollo). */
  soloTraer?: boolean
}

export type Nivel = 'apagada' | 'bien' | 'atencion' | 'error'

/** El ciclo corre cada minuto: más de esto sin correr es que el proceso se detuvo. */
export const MINUTOS_SIN_CICLO = 3

/** Encendida, se ve siempre; apagada, solo quien puede encenderla. */
export function visible(estado: EstadoSincronizacion | null, puedeCambiar: boolean): boolean {
  return estado !== null && (estado.activo || puedeCambiar)
}

export function cicloDetenido(estado: EstadoSincronizacion, ahora: Date): boolean {
  if (!estado.ultimoCicloEn) return true
  return ahora.getTime() - new Date(estado.ultimoCicloEn).getTime() > MINUTOS_SIN_CICLO * 60_000
}

export function nivel(estado: EstadoSincronizacion, ahora: Date): Nivel {
  if (!estado.activo) return 'apagada'
  if (estado.ultimoError) return 'error'
  if (estado.pendientes > 0 || cicloDetenido(estado, ahora)) return 'atencion'
  return 'bien'
}

export const ETIQUETA: Record<Nivel, string> = {
  apagada: 'Sincronización apagada',
  bien: 'Sincronizando con el sistema anterior',
  atencion: 'Sincronización con avisos',
  error: 'Sincronización con error',
}

/** Lo que conviene saber, en frases cortas, para el detalle del indicador. */
export function avisos(estado: EstadoSincronizacion, ahora: Date): string[] {
  if (!estado.activo) return []
  const lineas: string[] = []
  if (estado.soloTraer)
    lineas.push(
      'Solo trae del sistema anterior: lo que se haga aquí no se escribe allá ni se comprueba con allá.',
    )
  if (estado.ultimoError) lineas.push(`Último ciclo con error: ${estado.ultimoError}`)
  if (cicloDetenido(estado, ahora))
    lineas.push(
      estado.ultimoCicloEn
        ? `El ciclo no corre desde hace más de ${MINUTOS_SIN_CICLO} minutos (¿está el proceso app:sincronizar?).`
        : 'El ciclo aún no ha corrido (¿está el proceso app:sincronizar?).',
    )
  if (estado.pendientes > 0)
    lineas.push(
      `${estado.pendientes} ${estado.pendientes === 1 ? 'cambio espera' : 'cambios esperan'} llegar al sistema anterior.`,
    )
  return lineas
}
