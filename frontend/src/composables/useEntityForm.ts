/**
 * `useEntityForm` — formulario dinámico on demand a partir del nombre de una
 * entidad (ej: `'Boleto'`). Lee los metadatos introspectados, precarga las
 * listas de relaciones en paralelo (`registry.getEntity(t).loadFullList()`),
 * serializa el FormKit Schema y expone submit/reset.
 *
 * Los campos del formulario son los `inputFields` de la mutación
 * `create`/`update` (la fuente autoritativa de lo que acepta la API), con los
 * labels de `entity_configurations` cuando existen.
 */

import { computed, ref, toRef, watch } from "vue";
import type { MaybeRefOrGetter } from "vue";
import type { FormKitSchemaNode } from "@formkit/core";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { useEntityRegistry } from "./useEntityRegistry";
<<<<<<< Updated upstream
import {
  hydrateInitialValues,
  serializeEntityForm,
  serializeSubmitValue,
  type FormFieldSource,
} from "@/utils/formkit/schemaSerializer";
=======
import { FormSchemaSerializer, type FormFieldSource } from "@/utils/formkit/schemaSerializer";
>>>>>>> Stashed changes
import type { AgnosticOption } from "@/lib/apollo/types";
import type { EntityStore } from "@/stores/entities/types";

export type EntityFormMode = "create" | "update";

export interface UseEntityFormOptions {
  mode?: EntityFormMode;
  /** Item a editar (relaciones `{ id, label }` incluidas) o `{}` para alta. */
  initialData?: Record<string, unknown>;
  /** Labels por campo; por defecto los de `entity_configurations` y humanizados. */
  labels?: Record<string, string>;
}

export function useEntityForm(
  entityName: MaybeRefOrGetter<string>,
  options: UseEntityFormOptions = {},
) {
  const schemaRepo = useSchemaRepositoryStore();
  const registry = useEntityRegistry();

  const name = toRef(entityName);
  const mode = ref<EntityFormMode>(options.mode ?? "create");
  const initialData = ref<Record<string, unknown>>(options.initialData ?? {});
  const labels = ref<Record<string, string>>(options.labels ?? {});

  const schema = ref<FormKitSchemaNode[]>([]);
  const loading = ref(false);
  const submitting = ref(false);
  const error = ref("");

  let fields: FormFieldSource[] = [];
  let resetKey = 0;

  const entity = computed(() => schemaRepo.getEntityMetadata(name.value));
  const store = computed<EntityStore<Record<string, unknown>> | null>(() => {
    try {
      return registry.getEntity<Record<string, unknown>>(name.value);
    } catch {
      return null;
    }
  });
  const mutation = computed(() => {
    const ent = entity.value;
    if (!ent) return null;
    return mode.value === "update" ? ent.update : ent.create;
  });

  async function build() {
    loading.value = true;
    error.value = "";
    try {
      const ent = entity.value;
      const mut = mutation.value;
      const target = store.value;
      if (!ent || !mut || !target) {
        throw new Error(`"${name.value}" no expone ${mode.value}`);
      }
      await target.loadColumns();
      const selected = mut.inputFields.filter(
        (field) =>
          field.name !== "clientMutationId" && !(field.name === "id" && mode.value === "create"),
      );

      // Precarga en paralelo las listas de relaciones; falla blando si una
      // entidad destino no expone collectionAgnostic.
      const targets = [...new Set(selected.filter((f) => f.isRelation).map((f) => f.namedType))];
      const lists = await Promise.all(
        targets.map(async (targetName) => {
          try {
            return [targetName, await registry.getEntity(targetName).loadFullList()] as const;
          } catch (cause) {
            console.warn(`[useEntityForm] sin lista para "${targetName}":`, cause);
            return [targetName, [] as AgnosticOption[]] as const;
          }
        }),
      );
      const relationOptions: Record<string, AgnosticOption[]> = {};
      for (const [targetName, list] of lists) {
        for (const field of selected) {
          if (field.isRelation && field.namedType === targetName) {
            relationOptions[field.name] = list;
          }
        }
      }

      const labelMap: Record<string, string> = { ...labels.value };
      for (const col of target.columns) {
        if (col.label && !labelMap[col.field]) labelMap[col.field] = col.label;
      }

      fields = selected;
      resetKey += 1;
<<<<<<< Updated upstream
      schema.value = serializeEntityForm(ent.name, fields, {
=======
      schema.value = FormSchemaSerializer.serializeEntityForm(ent.name, fields, {
>>>>>>> Stashed changes
        mode: mode.value,
        labels: labelMap,
        relationOptions,
        values: FormSchemaSerializer.hydrateInitialValues(fields, initialData.value, mode.value),
        resetKey,
      });
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : String(cause);
      schema.value = [];
    } finally {
      loading.value = false;
    }
  }

  async function submit(data: Record<string, unknown>): Promise<Record<string, unknown>> {
    const target = store.value;
    if (!target) throw new Error(`No hay store para "${name.value}"`);
    submitting.value = true;
    error.value = "";
    try {
<<<<<<< Updated upstream
      const payload = serializeSubmitValue(fields, data);
=======
      const payload = FormSchemaSerializer.serializeSubmitValue(fields, data);
>>>>>>> Stashed changes
      return mode.value === "update" ? await target.update(payload) : await target.create(payload);
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : String(cause);
      throw cause;
    } finally {
      submitting.value = false;
    }
  }

  function setMode(next: EntityFormMode) {
    mode.value = next;
  }

  function setInitialData(data: Record<string, unknown>) {
    initialData.value = data;
  }

  function setLabels(next: Record<string, string>) {
    labels.value = next;
  }

  /** Reconstruye el schema desde cero (remonta los inputs con `resetKey`). */
  function reset() {
    void build();
  }

  watch([name, mode, initialData, labels], () => {
    void build();
  });

  void build();

  return {
    schema,
    loading,
    submitting,
    error,
    mode,
    submit,
    reset,
    setMode,
    setInitialData,
    setLabels,
  };
}
