/**
 * Registro de formularios dedicados por entidad.
 *
 * El formulario agnóstico (`FkEntityForm`) cubre la mayoría de entidades:
 * genera el FormKit Schema a partir de `inputFields` de la mutación create/update.
 * Pero algunas entidades requieren reglas de negocio que el formulario genérico
 * no expresa (ej. `Menu` con relaciones ManyToMany `parents`/`children` mutuamente
 * excluyentes). Para esas entidades se registra aquí un componente dedicado que
 * recibe exactamente la misma interfaz que `FkEntityForm` (vía `EntityFormProps`),
 * de modo que la ruta `/form/:entity` no sabe si está renderizando el genérico
 * o una implementación a medida.
 *
 * Para añadir una entidad personalizada basta:
 *   1. Crear `src/components/<area>/<Entidad>Form.vue` implementando `EntityFormProps`.
 *   2. Registrarlo en `entityFormOverrides` con la clave = nombre PascalCase de la entidad.
 */

import type { Component } from "vue";
import type { EntityFormMode } from "@/composables/useEntityForm";

/** Interfaz compartida: la misma que expone `FkEntityForm`, tanto para el
 * formulario genérico como para los dedicados (override). */
export interface EntityFormProps {
  entity: string;
  mode?: EntityFormMode;
  initialData?: Record<string, unknown>;
  labels?: Record<string, string>;
  submitLabel?: string;
}

/** Loader lazy de un componente de formulario dedicado (`() => import(...)`). */
export type EntityFormOverride = () => Promise<{ default: Component } | Component>;

/**
 * Mapa nomenclatura { [entityName]: loader del componente dedicado }.
 * La clave DEBE ser el nombre PascalCase de la entidad (ej. `"Menu"`),
 * el mismo que recibe `FkEntityForm` como prop `entity`.
 */
export const entityFormOverrides: Record<string, EntityFormOverride> = {
  Menu: () => import("@/components/menu/MenuForm.vue"),
};

/** Devuelve el loader del componente dedicado de una entidad, o `undefined` si usa el genérico. */
export function getFormOverride(entityName: string): EntityFormOverride | undefined {
  return entityFormOverrides[entityName];
}