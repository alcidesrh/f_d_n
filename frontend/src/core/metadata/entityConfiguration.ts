/**
 * Configuración de presentación de una entidad (`/api/entity_configurations`):
 * columnas del listado y campos del formulario. La editan en
 * `features/entity-config`; la consumen listados y formularios.
 */
import { http } from "../http";
import type { CollectionFieldConfig } from "@/stores/entities/types";
import type { FormFieldConfig } from "@/types/entities";

export interface EntityConfigurationDto {
  "@id"?: string;
  entityClass?: string;
  collectionFieldConfig?: CollectionFieldConfig[];
  formFields?: FormFieldConfig[];
}

/** `null` si el backend no tiene configuración guardada para la entidad. */
export async function fetchEntityConfiguration(entityClass: string): Promise<EntityConfigurationDto | null> {
  const data = await http.get<{ member?: EntityConfigurationDto[]; "hydra:member"?: EntityConfigurationDto[] }>(
    `/entity_configurations?entityClass=${encodeURIComponent(entityClass)}`,
    { loadingKey: `config:${entityClass}` },
  );
  return (data?.member ?? data?.["hydra:member"] ?? [])[0] ?? null;
}
