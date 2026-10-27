/**
 * Configuración de presentación de una entidad para el usuario con sesión
 * (`GET /api/me/configuracion/{entidad}`): campos del formulario y la
 * configuración del listado que le toca por sus roles (ADR-043). La editan
 * en `features/entity-config`; la consumen listados y formularios.
 */
import { http } from '@/core/http'
import type { CollectionFieldConfig, ListOptions } from '@/core/entities/types'

/** Campo del formulario de una entidad (`FormFieldConfig` del backend). */
export interface FormFieldConfig {
  '@id'?: string
  field?: string
  label?: string | null
  position?: number
  visible?: boolean
  attrs?: Record<string, unknown> | null
}

export interface EntityConfigurationDto {
  entityClass?: string
  /** La configuración del listado que le tocó (la por defecto si ninguno de sus roles tiene una). */
  listConfig?: { id: number; nombre: string } | null
  collectionFieldConfig?: CollectionFieldConfig[]
  formFields?: FormFieldConfig[]
  listOptions?: ListOptions | null
}

/** `null` si la entidad no se configura. */
export async function fetchEntityConfiguration(
  entityClass: string,
): Promise<EntityConfigurationDto | null> {
  return http.get<EntityConfigurationDto>(`/me/configuracion/${encodeURIComponent(entityClass)}`, {
    loadingKey: `config:${entityClass}`,
  })
}
