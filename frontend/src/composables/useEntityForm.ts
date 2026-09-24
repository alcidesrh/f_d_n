/**
 * `useEntityForm` — formulario dinámico on demand a partir del nombre de una
 * entidad (ej: `'Boleto'`). Lee los metadatos introspectados, precarga las
 * listas de relaciones en paralelo (`registry.getEntity(t).loadFullList()`),
 * serializa el FormKit Schema y expone submit/reset.
 *
 * Los campos del formulario (y los que pide la query al editar) son los
 * `formFields` visibles del store de la entidad o, si no hay, los `fields` del
 * schema (ver `entityFormFields.ts`); de ellos se usan los que acepta la
 * mutación `create`/`update`.
 *
 * Con `id` el formulario es de edición: carga el registro con esos campos y
 * al guardar envía `update` con el IRI del registro.
 */

import { computed, ref, toRef, watch } from "vue";
import type { MaybeRefOrGetter } from "vue";
import type { FormKitSchemaNode } from "@formkit/core";
import { useEntityRegistry } from "./useEntityRegistry";

import { FormSchemaSerializer, type FormFieldSource } from "@/utils/formkit/schemaSerializer";
import type { AgnosticOption } from "@/core/graphql/types";
import { itemIri } from "@/core/graphql/documents";
import { notify } from "@/core/notify";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { formFieldEntries, pickInputFields } from "@/utils/formkit/entityFormFields";
import type { EntityStore } from "@/stores/entities/types";
import { createIconRelationResolver, isIconRelation } from "@/lib/icons/iconRelation";
import { apiIconGateway } from "@/lib/icons/iconGateway";

export type EntityFormMode = "create" | "update";

export interface UseEntityFormOptions {
  mode?: EntityFormMode;
  /** Id (número o IRI) del registro a editar: fuerza modo `update` y lo carga. */
  id?: MaybeRefOrGetter<string | number | null | undefined>;
  /** Item a editar (relaciones `{ id, label }` incluidas) o `{}` para alta. */
  initialData?: Record<string, unknown>;
  /** Labels por campo; por defecto los de `entity_configurations` y humanizados. */
  labels?: Record<string, string>;
}

export function useEntityForm(entityName: MaybeRefOrGetter<string>, options: UseEntityFormOptions = {}) {
  const registry = useEntityRegistry();

  const name = toRef(entityName);
  const mode = ref<EntityFormMode>(options.mode ?? "create");
  const recordId = toRef(options.id ?? null);
  const hasRecordId = computed(() => recordId.value !== null && recordId.value !== undefined && recordId.value !== "");
  /** Con `id` siempre se edita, sea cual sea `mode`. */
  const effectiveMode = computed<EntityFormMode>(() => (hasRecordId.value ? "update" : mode.value));
  const initialData = ref<Record<string, unknown>>(options.initialData ?? {});
  const labels = ref<Record<string, string>>(options.labels ?? {});

  const schema = ref<FormKitSchemaNode[]>([]);
  const loading = ref(false);
  const submitting = ref(false);
  const error = ref("");

  let fields: FormFieldSource[] = [];
  let resetKey = 0;
  /** IRI del registro cargado por `id` (lo lleva el payload de `update`). */
  let recordIri: string | null = null;
  /** Descarta builds viejos si cambian entidad/id mientras uno sigue en vuelo. */
  let buildSeq = 0;
  // Relaciones con `Icon`: el form edita el nombre del ícono (IconPicker) y
  // al guardar se traduce a IRI buscando/creando el registro `Icon`.
  const iconRelations = createIconRelationResolver(apiIconGateway());

  const entity = computed(() => useSchemaRepositoryStore().getEntityMetadata(name.value));
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
    return effectiveMode.value === "update" ? ent.update : ent.create;
  });

  async function build() {
    const seq = ++buildSeq;
    loading.value = true;
    error.value = "";
    try {
      const ent = entity.value;
      const mut = mutation.value;
      const target = store.value;
      if (!ent || !mut || !target) {
        throw new Error(`"${name.value}" no expone ${effectiveMode.value}`);
      }
      // `getEntity` dispara `init()` sin esperarlo: sin esto el primer build
      // puede correr antes de que lleguen los `formFields` del REST.
      await target.init();
      const entries = formFieldEntries(target.formFields, ent);
      const selected = pickInputFields(entries, mut, effectiveMode.value);

      let source = initialData.value;
      recordIri = null;
      if (hasRecordId.value) {
        const item = await target.fetchItem(
          recordId.value as string | number,
          entries.map((entry) => entry.name),
        );
        if (!item) throw new Error(`No existe ${ent.name} con id ${String(recordId.value)}`);
        source = item;
        recordIri = typeof item.id === "string" ? item.id : itemIri(ent, recordId.value as string | number);
      }

      // Precarga en paralelo las listas de relaciones; falla blando si una
      // entidad destino no expone collectionAgnostic.
      const targets = [...new Set(selected.filter((f) => f.isRelation && !isIconRelation(f)).map((f) => f.namedType))];
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

      let values = FormSchemaSerializer.hydrateInitialValues(selected, source, effectiveMode.value);
      if (selected.some(isIconRelation)) values = await iconRelations.hydrate(selected, values);

      if (seq !== buildSeq) return;
      fields = selected;
      resetKey += 1;

      schema.value = FormSchemaSerializer.serializeEntityForm(ent.name, fields, {
        mode: effectiveMode.value,
        labels: labelMap,
        relationOptions,
        values,
        resetKey,
      });
    } catch (cause) {
      if (seq !== buildSeq) return;
      error.value = cause instanceof Error ? cause.message : String(cause);
      schema.value = [];
      notify.error(error.value);
    } finally {
      if (seq === buildSeq) loading.value = false;
    }
  }

  async function submit(data: Record<string, unknown>): Promise<Record<string, unknown>> {
    const target = store.value;
    if (!target) throw new Error(`No hay store para "${name.value}"`);
    submitting.value = true;
    error.value = "";
    try {
      const resolved = fields.some(isIconRelation) ? await iconRelations.resolve(fields, data) : data;
      const payload = FormSchemaSerializer.serializeSubmitValue(fields, resolved);
      if (effectiveMode.value === "update") {
        // El `id` puede no estar entre los formFields; la mutación lo exige.
        if (recordIri) payload.id = recordIri;
        return await target.update(payload);
      }
      return await target.create(payload);
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : String(cause);
      throw cause;
    } finally {
      submitting.value = false;
    }
  }

  /** Borra el registro cargado por `id`. */
  async function remove(): Promise<void> {
    const target = store.value;
    if (!target || !recordIri) throw new Error("No hay registro cargado para eliminar");
    submitting.value = true;
    try {
      await target.remove(recordIri);
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

  watch([name, effectiveMode, recordId, initialData, labels], () => {
    void build();
  });

  void build();

  return {
    schema,
    loading,
    submitting,
    error,
    mode: effectiveMode,
    submit,
    remove,
    reset,
    setMode,
    setInitialData,
    setLabels,
  };
}
