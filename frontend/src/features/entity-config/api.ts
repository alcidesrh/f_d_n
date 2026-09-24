/**
 * Transporte GraphQL de la configuración dinámica de entidades (ADR-010).
 *
 * `EntityConfiguration` guarda, por entidad del dominio (`entityClass`), cómo
 * se presentan sus campos en el listado (`collectionFieldConfig`) y en el
 * formulario (`formFields`). Este módulo solo habla con la API: la pantalla de
 * edición vive en `stores/entityConfig.ts` + `pages/config/EntityConfigEditor.vue`.
 *
 * Lecturas con `network-only`: la configuración se edita en esta misma pantalla,
 * así que servir la caché de Apollo mostraría datos ya pisados por un guardado.
 */

import { gql } from "@apollo/client";
import { graphql } from "@/core/graphql/client";

/** Campos comunes a `CollectionFieldConfig` y `FormFieldConfig` (`FieldConfig`). */
export interface FieldConfigDto {
  /** IRI del registro (`/api/collection_field_configs/45`); es el id que espera la mutación. */
  id: string;
  field: string;
  label: string | null;
  position: number;
  visible: boolean;
  kind: string | null;
  attrs: Record<string, unknown> | null;
}

export interface CollectionFieldConfigDto extends FieldConfigDto {
  sortable: boolean | null;
  filterable: boolean | null;
}

export interface FormFieldConfigDto extends FieldConfigDto {
  groupName: string | null;
}

export interface EntityConfigurationDetailDto {
  id: string;
  entityClass: string;
  collectionFieldConfig: CollectionFieldConfigDto[];
  formFields: FormFieldConfigDto[];
}

/** Entrada de `updateWithRelationsEntityConfiguration`: la entidad y sus dos listas. */
export interface UpdateEntityConfigurationInput {
  entityClass: string;
  collectionFieldConfig: CollectionFieldConfigDto[];
  formFields: FormFieldConfigDto[];
}

const ENTITY_CLASSES_QUERY = gql`
  query EntityConfigurationClasses {
    entityConfigurations {
      id
      entityClass
    }
  }
`;

const ENTITY_CONFIGURATION_QUERY = gql`
  query EntityConfigurationByClass($entityClass: String!) {
    entityConfigurations(entityClass: $entityClass) {
      id
      entityClass
      collectionFieldConfig {
        id
        field
        label
        position
        visible
        kind
        attrs
        sortable
        filterable
      }
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
`;

const UPDATE_ENTITY_CONFIGURATION = gql`
  mutation UpdateEntityConfigurationFields($input: updateWithRelationsEntityConfigurationInput!) {
    updateWithRelationsEntityConfiguration(input: $input) {
      entityConfiguration {
        id
        entityClass
        collectionFieldConfig {
          id
          field
          label
          position
          visible
          kind
          attrs
          sortable
          filterable
        }
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
`;

/** Nombres de entidad con configuración persistida, en orden alfabético. */
export async function fetchEntityClasses(): Promise<string[]> {
  const result = await graphql.client.query<{
    entityConfigurations: Array<{ entityClass: string }>;
  }>({ query: ENTITY_CLASSES_QUERY, fetchPolicy: "network-only" });

  return (result.data?.entityConfigurations ?? [])
    .map((config) => config.entityClass)
    .filter(Boolean)
    .sort((a, b) => a.localeCompare(b));
}

/**
 * Configuración de una entidad. Devuelve `null` si no hay registro para
 * `entityClass` (entidad sin sincronizar: ver `make entity-setup`).
 */
export async function fetchEntityConfiguration(
  entityClass: string,
): Promise<EntityConfigurationDetailDto | null> {
  const result = await graphql.client.query<{
    entityConfigurations: EntityConfigurationDetailDto[];
  }>({
    query: ENTITY_CONFIGURATION_QUERY,
    variables: { entityClass },
    fetchPolicy: "network-only",
  });

  return result.data?.entityConfigurations?.[0] ?? null;
}

/**
 * Guarda campos de listado y de formulario en una sola mutación
 * (`UpdateEntityConfigurationFieldsResolver`, que resuelve cada `id` como IRI).
 */
export async function saveEntityConfiguration(
  input: UpdateEntityConfigurationInput,
): Promise<EntityConfigurationDetailDto> {
  const result = await graphql.client.mutate<{
    updateWithRelationsEntityConfiguration: {
      entityConfiguration: EntityConfigurationDetailDto;
    };
  }>({ mutation: UPDATE_ENTITY_CONFIGURATION, variables: { input } });

  const saved = result.data?.updateWithRelationsEntityConfiguration?.entityConfiguration;
  if (!saved) {
    throw new Error(`[entityConfig] la mutación no devolvió la configuración de ${input.entityClass}`);
  }
  return saved;
}
