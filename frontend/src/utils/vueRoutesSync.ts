/**
 * Sincronización de rutas del frontend con la entidad `VueRoute` del backend.
 *
 * Extrae el árbol de rutas declarado en el router (vue-router) y lo publica en
 * el endpoint `/api/vue-routes/sync` del backend. Así la entidad `VueRoute`
 * siempre refleja las rutas realmente declaradas en la aplicación.
 *
 * Se puede disparar "a voluntad" llamando a `syncVueRoutes()`, y también se
 * ejecuta automáticamente durante el bootstrap (ver `stores/global.ts`).
 */

import type { RouteRecordRaw } from 'vue-router'
import router from '@/router'

const API_BASE = import.meta.env.VITE_REST_ENDPOINT ?? 'http://localhost/api'

export interface VueRouteDTO {
  name: string
  path?: string | null
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
  error?: string
}

/**
 * Publica el árbol de rutas actual en el backend. Devuelve `ok: true` cuando
 * el backend aceptó la sincronización; no lanza excepción de red para poder
 * usarse de forma no bloqueante durante el bootstrap.
 */
export async function syncVueRoutes(routes: VueRouteDTO[] = extractVueRoutes()): Promise<VueRoutesSyncResult> {
  try {
    const response = await fetch(`${API_BASE}/vue-routes/sync`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ routes }),
    })

    if (!response.ok) {
      return {
        ok: false,
        count: 0,
        error: `HTTP ${response.status} ${response.statusText}`,
      }
    }

    return { ok: true, count: routes.length }
  } catch (error) {
    return {
      ok: false,
      count: 0,
      error: error instanceof Error ? error.message : String(error),
    }
  }
}
