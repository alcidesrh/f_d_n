/**
 * Cliente REST singleton para endpoints de metadatos de API Platform
 * (ej: `/entity_configurations?entityClass=X`). Requiere el header
 * `Accept: application/ld+json` (sin él el backend responde 406).
 */

import type { CollectionFieldConfig } from '@/stores/entities/types'

const REST_URI = import.meta.env.VITE_REST_ENDPOINT ?? 'http://localhost/api'

export interface EntityConfigurationDto {
  '@id'?: string
  entityClass?: string
  collectionFieldConfig?: CollectionFieldConfig[]
  formFields?: unknown[]
}

interface HydraCollection {
  'hydra:member'?: EntityConfigurationDto[]
  member?: EntityConfigurationDto[]
}

export class RestClient {
  readonly baseUrl: string

  constructor(baseUrl: string = REST_URI) {
    this.baseUrl = baseUrl.replace(/\/$/, '')
  }

  async getJson<T = unknown>(path: string): Promise<T> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      headers: { Accept: 'application/ld+json' },
    })
    if (!response.ok) {
      throw new Error(`REST ${response.status} ${response.statusText} en ${path}`)
    }
    return (await response.json()) as T
  }

  /**
   * Configuración de columnas de una entidad. Devuelve `null` cuando el
   * backend no tiene configuración persistida para la entidad (fallback del
   * store a "todas las propiedades" del schema).
   */
  async getEntityConfiguration(entityClass: string): Promise<EntityConfigurationDto | null> {
    const path = `/entity_configurations?entityClass=${encodeURIComponent(entityClass)}`
    const data = await this.getJson<HydraCollection>(path)
    const member = data?.['hydra:member'] ?? data?.member ?? []
    return member[0] ?? null
  }
}

export function createRestClient(baseUrl?: string): RestClient {
  return new RestClient(baseUrl)
}

/** Cliente REST singleton para metadatos de entidades. */
export const rest = createRestClient()
