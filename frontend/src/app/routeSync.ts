/**
 * Sincronización de rutas del frontend con la entidad `VueRoute` del backend.
 *
 * Extrae el árbol de rutas declarado en el router (vue-router), con lo que
 * exige cada pantalla (`meta.capacidad`, `meta.recurso`), y lo publica en
 * `/api/vue-routes/sync`. Así el backend sabe qué ítems de menú y qué enlaces
 * de la administración puede abrir cada usuario (ADR-049).
 *
 * La dispara el shell al entrar quien puede configurar la aplicación
 * (`config.editar`, lo que exige el endpoint); también a voluntad con
 * `syncVueRoutes()`.
 */

import type { RouteRecordRaw } from 'vue-router'
import type { RecursoDeRuta } from '@/core/acceso/tipos'
import { http } from '@/core/http'
import { router } from './router'

export interface VueRouteDTO {
  name: string
  path?: string | null
  /** Alguna de estas capacidades (`meta.capacidad`); sin la clave, cualquiera con sesión. */
  capacidad?: string[]
  recurso?: RecursoDeRuta
  children?: VueRouteDTO[]
}

/** Convierte una ruta de vue-router (y sus hijos) al DTO que espera el backend. */
export function toVueRouteDTO(record: RouteRecordRaw): VueRouteDTO | null {
  const name = typeof record.name === 'string' ? record.name : null
  if (!name) return null

  const dto: VueRouteDTO = {
    name,
    path: typeof record.path === 'string' ? record.path : null,
  }
  const { capacidad, recurso } = record.meta ?? {}
  if (typeof capacidad === 'string') dto.capacidad = [capacidad]
  else if (Array.isArray(capacidad)) dto.capacidad = capacidad.filter((c): c is string => typeof c === 'string')
  if (recurso && typeof recurso === 'object') dto.recurso = { ...(recurso as RecursoDeRuta) }

  if (Array.isArray(record.children) && record.children.length > 0) {
    const children = record.children
      .map(toVueRouteDTO)
      .filter((child): child is VueRouteDTO => child !== null)
    if (children.length > 0) dto.children = children
  }

  return dto
}

/** Devuelve el árbol de rutas del router de la app como DTO listo para el backend. */
export function extractVueRoutes(
  routes: readonly RouteRecordRaw[] = router.options.routes,
): VueRouteDTO[] {
  const result: VueRouteDTO[] = []

  for (const record of routes) {
    const dto = toVueRouteDTO(record)

    if (dto) {
      result.push(dto)
    } else if (Array.isArray(record.children)) {
      // La ruta no tiene `name` (solo agrupa): se promocionan sus hijos
      // nombrados al nivel superior.
      result.push(...extractVueRoutes(record.children))
    }
  }

  return result
}

export interface VueRoutesSyncResult {
  ok: boolean
  count: number
  /** Capacidades que el router exige y no están en el catálogo del backend: esas pantallas no las abre nadie. */
  desconocidas: string[]
  error?: string
}

/**
 * Publica el árbol de rutas actual en el backend. Devuelve `ok: true` cuando
 * el backend aceptó la sincronización; no lanza excepción de red para poder
 * usarse de forma no bloqueante al entrar.
 */
export async function syncVueRoutes(
  routes: VueRouteDTO[] = extractVueRoutes(),
): Promise<VueRoutesSyncResult> {
  try {
    const respuesta = await http.post<{ capacidadesDesconocidas?: string[] }>('/vue-routes/sync', { routes }, { silent: true })
    return { ok: true, count: routes.length, desconocidas: respuesta?.capacidadesDesconocidas ?? [] }
  } catch (error) {
    return { ok: false, count: 0, desconocidas: [], error: error instanceof Error ? error.message : String(error) }
  }
}
