/**
 * Manifiesto de la metadata del cliente (`GET /api/metadata`, ADR-036): en
 * qué revisión cambió por última vez el schema GraphQL y la configuración de
 * cada entidad. Lo pide `sincronizacion.ts`; las stores de entidad lo leen
 * para saber si la configuración que guardaron sigue vigente.
 *
 * El backend responde con la revisión como etiqueta (`ETag`): el navegador
 * revalida y, si nada cambió, recibe un 304 y usa el que tenía.
 */
import { shallowRef } from 'vue'
import { http } from '@/core/http'

export interface AvisoMetadata {
  revision: number
  nota: string | null
}

export interface Manifiesto {
  revision: number
  esquema: { revision: number; huella: string }
  /** Revisión en que cambió por última vez la configuración de cada entidad. */
  configs: Record<string, number>
  /** Última revisión que pidió recargar todo en cada navegador (0: ninguna). */
  completa: number
  /** Última revisión publicada con aviso: los navegadores ofrecen «Recargar» con la nota. */
  aviso: AvisoMetadata | null
}

/** Cabecera con la revisión vigente en cada respuesta de la API. */
export const CABECERA_REVISION = 'X-Metadata-Revision'

/** El último manifiesto leído en esta página; null mientras no llega (o si falló). */
export const manifiestoActual = shallowRef<Manifiesto | null>(null)

export function fetchManifiesto(): Promise<Manifiesto> {
  return http.get<Manifiesto>('/metadata', { silent: true })
}

/**
 * Desde qué revisión tiene que ser la configuración guardada de `entidad`
 * para seguir vigente: la última en que cambió o una posterior que pidió
 * recargar todo. Null sin manifiesto (no se sabe).
 */
export function revisionVigenteDeConfig(entidad: string, manifiesto = manifiestoActual.value): number | null {
  if (!manifiesto) return null
  return Math.max(manifiesto.configs[entidad] ?? 0, manifiesto.completa)
}
