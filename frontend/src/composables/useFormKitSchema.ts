/**
 * `useFormKitSchema` — serializa los metadatos de una entidad GraphQL a un
 * array de nodos FormKit Schema (sintaxis corta `$formkit`) listos para
 * renderizar con `<FormKitSchema :schema="schema" />`.
 *
 * Fuente de verdad: `entity.create.inputFields` / `entity.update.inputFields`
 * (los campos reales del GraphQL input, no los de la query).
 *
 * Mapeo de tipos:
 * - String/scalars de texto → InputText
 * - Int/Float              → InputNumber
 * - Boolean                → ToggleSwitch
 * - Date/DateTime          → DatePicker
 * - ENUM (single)          → Select  (opciones fijas del schema)
 * - ENUM (isList)          → MultiSelect (opciones fijas)
 * - Relación (single)      → AutoComplete (con entityName → loadFullList)
 * - Relación (isList)      → MultiSelect (options pre-cargadas via loadFullList)
 */

import { ref, type Ref } from "vue";
import type { FormKitSchemaNode } from "@formkit/core";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { getEntity } from "@/composables/useEntityRegistry";
import type { SchemaInputField } from "@/lib/apollo/types";
import type { AgnosticOption } from "@/lib/apollo/types";

// ---------------------------------------------------------------------------
// Tipos
// ---------------------------------------------------------------------------

/** Nodo de schema con la sintaxis corta de FormKit (attrs expandidos). */
type FkSchemaNode = Record<string, unknown> & { $formkit: string };

/** Resultado del composable. */
export interface UseFormKitSchemaReturn {
  /** Nodos FormKit Schema listos para <FormKitSchema :schema="schema" />. */
  schema: Ref<FormKitSchemaNode[]>;
  /** true mientras se cargan las listas de relaciones. */
  loading: Ref<boolean>;
}

// ---------------------------------------------------------------------------
// Scalars conocidos
// ---------------------------------------------------------------------------

const NUMBER_SCALARS = new Set(["Int", "Float"]);

const DATE_SCALARS = new Set(["Date", "DateTime", "DateTimeImmutable", "DateImmutable"]);

// ---------------------------------------------------------------------------
// Helpers de etiqueta
// ---------------------------------------------------------------------------

/**
 * Convierte camelCase / snake_case a "Título Legible".
 * Ej: "fechaNacimiento" → "Fecha Nacimiento"
 */
function toLabel(name: string): string {
  return name
    .replace(/_/g, " ")
    .replace(/([A-Z])/g, " $1")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/^\w/, (c) => c.toUpperCase());
}

// ---------------------------------------------------------------------------
// Mapper principal
// ---------------------------------------------------------------------------

/**
 * Mapea un `SchemaInputField` (campo del input de mutación GraphQL) a un nodo
 * FormKit Schema en sintaxis corta. Devuelve `null` para campos omitidos.
 *
 * @param field     Metadatos del campo del input GraphQL.
 * @param options   Map de opciones pre-cargadas para relaciones múltiples.
 */
function mapField(
  field: SchemaInputField,
  relOptions: Map<string, AgnosticOption[]>,
): FkSchemaNode | null {
  // Omitir siempre el campo id (lo gestiona el CRUD)
  if (field.name === "id") return null;
  // Omitir campos clientMutationId (relay)
  if (field.name === "clientMutationId") return null;

  const label = toLabel(field.name);
  const validation = field.required ? "required" : "";

  // ── Boolean ──────────────────────────────────────────────────────────────
  if (field.namedType === "Boolean") {
    return {
      $formkit: "ToggleSwitch",
      name: field.name,
      label,
      ...(validation && { validation }),
    };
  }

  // ── Numérico ─────────────────────────────────────────────────────────────
  if (NUMBER_SCALARS.has(field.namedType)) {
    return {
      $formkit: "InputNumber",
      name: field.name,
      label,
      ...(validation && { validation }),
    };
  }

  // ── Fecha / DateTime ─────────────────────────────────────────────────────
  if (DATE_SCALARS.has(field.namedType)) {
    return {
      $formkit: "DatePicker",
      name: field.name,
      label,
      showIcon: true,
      ...(validation && { validation }),
    };
  }

  // ── ENUM ─────────────────────────────────────────────────────────────────
  if (field.kind === "ENUM") {
    const enumOptions = (field.enumValues ?? []).map((v) => ({
      label: toLabel(v),
      value: v,
    }));

    if (field.isList) {
      // ENUM múltiple → MultiSelect con opciones fijas
      return {
        $formkit: "MultiSelect",
        name: field.name,
        label,
        options: enumOptions,
        optionLabel: "label",
        optionValue: "value",
        filter: true,
        ...(validation && { validation }),
      };
    }

    // ENUM single → Select con opciones fijas
    return {
      $formkit: "Select",
      name: field.name,
      label,
      options: enumOptions,
      optionLabel: "label",
      optionValue: "value",
      showClear: !field.required,
      ...(validation && { validation }),
    };
  }

  // ── Relación ─────────────────────────────────────────────────────────────
  if (field.isRelation) {
    if (field.isList) {
      // Relación múltiple → MultiSelect con options pre-cargadas
      const options = relOptions.get(field.namedType) ?? [];
      return {
        $formkit: "MultiSelect",
        name: field.name,
        label,
        options,
        optionLabel: "label",
        optionValue: "id",
        filter: true,
        ...(validation && { validation }),
      };
    }

    // Relación single → AutoComplete gestionado internamente con entityName
    return {
      $formkit: "AutoComplete",
      name: field.name,
      label,
      entityName: field.namedType,
      optionLabel: "label",
      forceSelection: true,
      ...(validation && { validation }),
    };
  }

  // ── Texto (fallback para todos los scalars no reconocidos) ────────────────
  if (field.namedType === "Password" || field.name.toLowerCase().includes("password")) {
    return {
      $formkit: "Password",
      name: field.name,
      label,
      toggleMask: true,
      ...(validation && { validation }),
    };
  }

  if (
    field.name.toLowerCase().includes("description") ||
    field.name.toLowerCase().includes("descripcion") ||
    field.name.toLowerCase().includes("observacion") ||
    field.name.toLowerCase().includes("nota")
  ) {
    return {
      $formkit: "TextArea",
      name: field.name,
      label,
      rows: 3,
      autoResize: true,
      ...(validation && { validation }),
    };
  }

  // InputText genérico para String y demás scalars
  return {
    $formkit: "InputText",
    name: field.name,
    label,
    ...(validation && { validation }),
  };
}

// ---------------------------------------------------------------------------
// Composable
// ---------------------------------------------------------------------------

/**
 * Genera el FormKit Schema para el formulario de una entidad dada.
 *
 * @param entityName  Nombre de la entidad tal cual está en Symfony (ej: "Boleto").
 * @param mode        "create" (default) | "update" — selecciona la mutación fuente.
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * const { schema, loading } = useFormKitSchema("Boleto")
 * </script>
 * <template>
 *   <FormKitSchema :schema="schema" />
 * </template>
 * ```
 */
export function useFormKitSchema(
  entityName: string,
  mode: "create" | "update" = "create",
): UseFormKitSchemaReturn {
  const schema = ref<FormKitSchemaNode[]>([]);
  const loading = ref(false);

  const schemaRepo = useSchemaRepositoryStore();

  async function build() {
    loading.value = true;
    schema.value = [];

    try {
      const entity = schemaRepo.getEntityMetadata(entityName);
      if (!entity) {
        console.warn(`[useFormKitSchema] Entidad no encontrada: ${entityName}`);
        return;
      }

      const mutation = mode === "create" ? entity.create : entity.update;
      if (!mutation) {
        console.warn(
          `[useFormKitSchema] La entidad "${entityName}" no expone mutación "${mode}".`,
        );
        return;
      }

      const inputFields = mutation.inputFields;

      // Pre-cargar listas de relaciones múltiples en paralelo
      const relMultipleFields = inputFields.filter(
        (f) => f.isRelation && f.isList,
      );

      const relOptions = new Map<string, AgnosticOption[]>();

      if (relMultipleFields.length > 0) {
        await Promise.allSettled(
          relMultipleFields.map(async (f) => {
            try {
              const store = getEntity(f.namedType);
              const list = await store.loadFullList();
              relOptions.set(f.namedType, list);
            } catch (e) {
              console.warn(
                `[useFormKitSchema] No se pudo cargar lista de "${f.namedType}":`,
                e,
              );
              relOptions.set(f.namedType, []);
            }
          }),
        );
      }

      // Construir nodos
      const nodes: FormKitSchemaNode[] = [];

      for (const field of inputFields) {
        const node = mapField(field, relOptions);
        if (node) nodes.push(node as FormKitSchemaNode);
      }

      schema.value = nodes;
    } finally {
      loading.value = false;
    }
  }

  // Construir de inmediato
  void build();

  return { schema, loading };
}
