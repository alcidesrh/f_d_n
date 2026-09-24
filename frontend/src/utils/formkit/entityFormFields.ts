/**
 * Qué campos lleva el formulario dinámico de una entidad (y qué pide la query
 * item al editar).
 *
 * La referencia son los `formFields` de la entidad (`entity_configurations`):
 * los visibles, ordenados por `position`, con su label. Si la entidad no
 * tiene `formFields` configurados, la referencia son todos los `fields` del
 * schema (sin subcolecciones).
 */
import type { EntitySchema, MutationSchema, SchemaInputField } from "@/lib/apollo/types";
import type { FormFieldConfig } from "@/types/entities";

export interface FormFieldEntry {
  name: string;
  label?: string;
}

export function formFieldEntries(formFields: FormFieldConfig[], entity: EntitySchema): FormFieldEntry[] {
  if (formFields.length === 0) {
    return entity.fields.filter((field) => !field.isSubcollection).map((field) => ({ name: field.name }));
  }
  return formFields
    .filter((config) => config.visible && typeof config.field === "string" && config.field)
    .slice()
    .sort((a, b) => (Number(a.position) || 0) - (Number(b.position) || 0))
    .map((config) => ({
      name: config.field as string,
      ...(typeof config.label === "string" && config.label ? { label: config.label } : {}),
    }));
}

/**
 * Inputs del formulario: las entradas que la mutación acepta, en el orden de
 * `entries`. Se descartan `clientMutationId`, el `id` al crear y los campos que
 * la mutación no recibe (ej. `createdAt`). Los inputs se clonan: el metadata
 * del schema (persistido) no se muta al aplicarle el label.
 */
export function pickInputFields(
  entries: FormFieldEntry[],
  mutation: MutationSchema,
  mode: "create" | "update",
): SchemaInputField[] {
  const inputs = new Map(mutation.inputFields.map((field) => [field.name, field]));
  const out: SchemaInputField[] = [];
  for (const entry of entries) {
    if (entry.name === "clientMutationId") continue;
    if (entry.name === "id" && mode === "create") continue;
    const input = inputs.get(entry.name);
    if (!input) continue;
    out.push(entry.label ? { ...input, label: entry.label } : { ...input });
  }
  return out;
}
