/**
 * Relaciones con la entidad `Icon` en el CRUD dinámico.
 *
 * El formulario trabaja con el nombre del ícono (`bus`) vía `IconPicker`; la
 * API espera el IRI de un registro `Icon`. Este resolver traduce en ambos
 * sentidos:
 *  - `hydrate`: IRI de la relación → nombre del ícono (al editar).
 *  - `resolve`: nombre elegido → IRI, reutilizando el `Icon` existente con ese
 *    `icon` o creándolo si no hay ninguno (al guardar).
 *
 * El acceso a la API va por un `IconGateway` inyectable (ver `iconGateway.ts`);
 * este módulo no depende de `@/init` y se puede importar desde utils puros.
 */
import type { FormFieldSource } from "@/utils/formkit/schemaSerializer";

export const ICON_ENTITY = "Icon";

/** Relaciones que se editan con `IconPicker` (solo a-uno; a-muchos sigue con MultiSelect). */
export function isIconRelation(field: Pick<FormFieldSource, "isRelation" | "isList" | "namedType">): boolean {
  return field.isRelation && !field.isList && field.namedType === ICON_ENTITY;
}

/** IRI de API Platform (`/api/icons/3`) vs nombre de ícono (`bus`). */
function isIri(value: string): boolean {
  return value.startsWith("/");
}

export interface IconGateway {
  /** Nombre del ícono (`Icon.icon`) de un registro; `null` si no existe. */
  iconName(iri: string): Promise<string | null>;
  /** IRI de un `Icon` cuyo `icon` sea exactamente `name`; `null` si no hay. */
  findIri(name: string): Promise<string | null>;
  /** Crea un `Icon` para `name` y devuelve su IRI. */
  create(name: string): Promise<string>;
}

export function createIconRelationResolver(gateway: IconGateway) {
  /** nombre → IRI ya conocido en este formulario (hidratado, encontrado o creado). */
  const known = new Map<string, string>();

  async function toIri(name: string): Promise<string> {
    const cached = known.get(name);
    if (cached) return cached;
    const iri = (await gateway.findIri(name)) ?? (await gateway.create(name));
    known.set(name, iri);
    return iri;
  }

  return {
    /** Reemplaza los IRIs de relaciones `Icon` por el nombre del ícono. */
    async hydrate(fields: FormFieldSource[], values: Record<string, unknown>): Promise<Record<string, unknown>> {
      const out = { ...values };
      await Promise.all(
        fields.filter(isIconRelation).map(async (field) => {
          const iri = out[field.name];
          if (typeof iri !== "string" || !isIri(iri)) return;
          try {
            const name = await gateway.iconName(iri);
            if (!name) return;
            known.set(name, iri);
            out[field.name] = name;
          } catch (cause) {
            console.warn(`[iconRelation] no se pudo leer ${iri}:`, cause);
          }
        }),
      );
      return out;
    },

    /** Reemplaza los nombres de ícono de relaciones `Icon` por IRIs (buscando o creando). */
    async resolve(fields: FormFieldSource[], data: Record<string, unknown>): Promise<Record<string, unknown>> {
      const out = { ...data };
      for (const field of fields.filter(isIconRelation)) {
        const value = out[field.name];
        if (value === "" || value === undefined) {
          if (field.name in out) out[field.name] = null;
          continue;
        }
        if (typeof value !== "string" || isIri(value)) continue;
        out[field.name] = await toIri(value);
      }
      return out;
    },
  };
}

export type IconRelationResolver = ReturnType<typeof createIconRelationResolver>;
