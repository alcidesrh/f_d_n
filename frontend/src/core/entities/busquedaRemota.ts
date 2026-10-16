/**
 * Entidades con muchos registros: sus filtros y selectores no cargan la lista
 * completa, buscan en el servidor al teclear (`collectionAgnostic` con
 * `search`, `BuscadorOpciones`) y piden por ids lo ya elegido.
 */
import type { AgnosticOption } from '@/core/graphql/types'

export const ENTIDADES_BUSQUEDA_REMOTA: ReadonlySet<string> = new Set([
  'Usuario',
  'Trayecto',
  'Ruta',
  'Enclave',
])

/** Si la entidad se busca en el servidor (y no se carga entera). */
export const esBusquedaRemota = (entidad: string | null | undefined): boolean =>
  !!entidad && ENTIDADES_BUSQUEDA_REMOTA.has(entidad)

/** Id numérico de una opción: el último segmento de su IRI (`/api/rutas/26` → 26). */
export const idDeOpcion = (opcion: AgnosticOption): number =>
  Number(
    String(opcion.value ?? opcion.id ?? '')
      .split('/')
      .pop(),
  )
