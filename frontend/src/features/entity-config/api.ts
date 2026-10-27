/**
 * Transporte de la configuración dinámica de entidades (ADR-010, ADR-043).
 *
 * `EntityConfiguration` guarda, por entidad del dominio (`entityClass`), los
 * campos del formulario (`formFields`, GraphQL) y una o más configuraciones
 * del listado, cada una con sus roles, columnas y opciones (REST
 * `/configuracion-listados/{entidad}`). Este módulo solo habla con la API:
 * la pantalla de edición vive en `store.ts` + `EntityConfigPage.vue`.
 *
 * Lecturas con `network-only`: la configuración se edita en esta misma pantalla,
 * así que servir la caché de Apollo mostraría datos ya pisados por un guardado.
 */

import { gql } from '@apollo/client'
import { graphql } from '@/core/graphql/client'
import { http, request } from '@/core/http'
import type { ListOptions } from '@/core/entities/types'

/** Campos comunes a `CollectionFieldConfig` y `FormFieldConfig` (`FieldConfig`). */
export interface FieldConfigDto {
  /** IRI del campo del formulario (`/api/form_field_configs/45`, lo espera la mutación) o id de la columna. */
  id: string | number
  field: string
  label: string | null
  position: number
  visible: boolean
  kind: string | null
  attrs: Record<string, unknown> | null
}

export interface CollectionFieldConfigDto extends FieldConfigDto {
  id: number
  sortable: boolean | null
  filterable: boolean | null
  /** Relación con muchos registros: su filtro autocompleta en el servidor. */
  autocomplete: boolean | null
  /** Se edita en línea en el listado. */
  editable: boolean | null
  /** Ancho en el listado: preset (`xs`…`xl`), longitud (`12rem`) o fracción (`2fr`). */
  width: string | null
}

export interface FormFieldConfigDto extends FieldConfigDto {
  id: string
  groupName: string | null
}

export interface EntityConfigurationDetailDto {
  id: string
  entityClass: string
  formFields: FormFieldConfigDto[]
}

/** Entrada de `updateWithRelationsEntityConfiguration`: la entidad y los campos del formulario. */
export interface UpdateEntityConfigurationInput {
  entityClass: string
  formFields: FormFieldConfigDto[]
}

/** Una configuración del listado de la entidad (`ListConfiguration`). */
export interface ListConfigDto {
  id: number
  nombre: string
  /** Prioridad: con roles en varias, al usuario le toca la de menor posición. */
  position: number
  /** Sin roles: la ven los usuarios sin un rol asociado a otra. */
  porDefecto: boolean
  roles: number[]
  listOptions: ListOptions | null
  collectionFieldConfig: CollectionFieldConfigDto[]
}

export interface RolOpcion {
  id: number
  nombre: string
  descripcion: string | null
}

export interface ListConfigsDto {
  configuraciones: ListConfigDto[]
  /** Roles que se pueden asociar (todos menos el base). */
  roles: RolOpcion[]
}

export interface ListConfigInput {
  nombre?: string
  roles?: number[]
  listOptions?: ListOptions | null
  columnas?: CollectionFieldConfigDto[]
}

const ENTITY_CLASSES_QUERY = gql`
  query EntityConfigurationClasses {
    entityConfigurations {
      id
      entityClass
    }
  }
`

const ENTITY_CONFIGURATION_QUERY = gql`
  query EntityConfigurationByClass($entityClass: String!) {
    entityConfigurations(entityClass: $entityClass) {
      id
      entityClass
      formFields {
        id
        field
        label
        position
        visible
        kind
        attrs
        groupName
      }
    }
  }
`

const UPDATE_ENTITY_CONFIGURATION = gql`
  mutation UpdateEntityConfigurationFields($input: updateWithRelationsEntityConfigurationInput!) {
    updateWithRelationsEntityConfiguration(input: $input) {
      entityConfiguration {
        id
        entityClass
        formFields {
          id
          field
          label
          position
          visible
          kind
          attrs
          groupName
        }
      }
    }
  }
`

/** Nombres de entidad con configuración persistida, en orden alfabético. */
export async function fetchEntityClasses(): Promise<string[]> {
  const result = await graphql.client.query<{
    entityConfigurations: Array<{ entityClass: string }>
  }>({ query: ENTITY_CLASSES_QUERY, fetchPolicy: 'network-only' })

  return (result.data?.entityConfigurations ?? [])
    .map((config) => config.entityClass)
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b))
}

/**
 * Configuración de una entidad. Devuelve `null` si no hay registro para
 * `entityClass` (entidad sin sincronizar: ver `make entity-setup`).
 */
export async function fetchEntityConfiguration(
  entityClass: string,
): Promise<EntityConfigurationDetailDto | null> {
  const result = await graphql.client.query<{
    entityConfigurations: EntityConfigurationDetailDto[]
  }>({
    query: ENTITY_CONFIGURATION_QUERY,
    variables: { entityClass },
    fetchPolicy: 'network-only',
  })

  return result.data?.entityConfigurations?.[0] ?? null
}

/**
 * Guarda los campos del formulario
 * (`UpdateEntityConfigurationFieldsResolver`, que resuelve cada `id` como IRI).
 */
export async function saveEntityConfiguration(
  input: UpdateEntityConfigurationInput,
): Promise<EntityConfigurationDetailDto> {
  const result = await graphql.client.mutate<{
    updateWithRelationsEntityConfiguration: {
      entityConfiguration: EntityConfigurationDetailDto
    }
  }>({ mutation: UPDATE_ENTITY_CONFIGURATION, variables: { input } })

  const saved = result.data?.updateWithRelationsEntityConfiguration?.entityConfiguration
  if (!saved) {
    throw new Error(
      `[entityConfig] la mutación no devolvió la configuración de ${input.entityClass}`,
    )
  }
  return saved
}

const listados = (entityClass: string) => `/configuracion-listados/${encodeURIComponent(entityClass)}`

/** Las configuraciones del listado de la entidad, por prioridad. */
export function fetchListConfigs(entityClass: string): Promise<ListConfigsDto> {
  return http.get<ListConfigsDto>(listados(entityClass))
}

/** Una nueva, copia de `desde` (o de la por defecto) en columnas y opciones. */
export function createListConfig(entityClass: string, input: { nombre: string; roles: number[]; desde?: number }): Promise<ListConfigsDto & { creada: number }> {
  return request<ListConfigsDto & { creada: number }>(listados(entityClass), { method: 'POST', body: input })
}

/** Lo ausente no cambia. */
export function saveListConfig(entityClass: string, id: number, input: ListConfigInput): Promise<ListConfigsDto> {
  return request<ListConfigsDto>(`${listados(entityClass)}/${id}`, { method: 'PUT', body: input })
}

export function deleteListConfig(entityClass: string, id: number): Promise<ListConfigsDto> {
  return request<ListConfigsDto>(`${listados(entityClass)}/${id}`, { method: 'DELETE' })
}
