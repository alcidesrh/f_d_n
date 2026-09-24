/**
 * Formulario dinámico de una entidad: arma el FormKit Schema a partir de la
 * metadata y la configuración, y expone guardar/eliminar/restablecer.
 *
 * - Campos: los `formFields` visibles de la entidad o, si no hay, sus `fields`
 *   del schema (`formFields.ts`); solo los que acepta la mutación.
 * - Con `id` es de edición: carga el registro pidiendo esos campos y guarda con
 *   `update` + el IRI del registro. Sin `id`, `create`.
 * - Relaciones: se precargan sus opciones; las de `Icon` se editan por nombre
 *   (`iconRelation.ts`).
 */
import { computed, ref, toRef, watch, type MaybeRefOrGetter } from "vue";
import type { FormKitSchemaNode } from "@formkit/core";
import { getEntity } from "@/core/entities/registry";
import { useSchemaStore } from "@/core/entities/schema";
import type { EntityStore } from "@/core/entities/types";
import { itemIri } from "@/core/graphql/documents";
import type { AgnosticOption } from "@/core/graphql/types";
import { notify } from "@/core/notify";
import { formFieldEntries, pickInputFields } from "./formFields";
import { hydrateInitialValues, serializeEntityForm, serializeSubmitValue, type FormFieldSource } from "./formSchema";
import { apiIconGateway } from "./iconGateway";
import { createIconRelationResolver, isIconRelation } from "./iconRelation";

export type EntityFormMode = "create" | "update";

type Row = Record<string, unknown>;

/** Opciones de cada campo de relación (`campo → options`), sin fallar si una lista no carga. */
async function loadRelationOptions(fields: FormFieldSource[]): Promise<Record<string, AgnosticOption[]>> {
  const relations = fields.filter((field) => field.isRelation && !isIconRelation(field));
  const lists = await Promise.all(
    [...new Set(relations.map((field) => field.namedType))].map(async (target) => {
      try {
        return [target, await getEntity(target).loadFullList()] as const;
      } catch (cause) {
        console.warn(`[useEntityForm] sin opciones para "${target}":`, cause);
        return [target, [] as AgnosticOption[]] as const;
      }
    }),
  );
  const byTarget = Object.fromEntries(lists);
  return Object.fromEntries(relations.map((field) => [field.name, byTarget[field.namedType] ?? []]));
}

/** Labels de las columnas del listado como respaldo de los del formulario. */
function columnLabels(store: Pick<EntityStore, "columns">): Record<string, string> {
  return Object.fromEntries(store.columns.filter((col) => col.label).map((col) => [col.field, col.label as string]));
}

export function useEntityForm(
  entityName: MaybeRefOrGetter<string>,
  options: { id?: MaybeRefOrGetter<string | number | null | undefined> } = {},
) {
  const name = toRef(entityName);
  const recordId = toRef(options.id ?? null);
  const mode = computed<EntityFormMode>(() => (recordId.value != null && recordId.value !== "" ? "update" : "create"));

  const schema = ref<FormKitSchemaNode[]>([]);
  const loading = ref(false);
  const submitting = ref(false);
  const error = ref("");

  /** Inputs del formulario vigente (los que se envían al guardar). */
  let fields: FormFieldSource[] = [];
  /** IRI del registro en edición. */
  let recordIri: string | null = null;
  /** Remonta los inputs en cada build (`key` de los nodos). */
  let generation = 0;
  /** Descarta builds viejos si cambian entidad o id con uno en vuelo. */
  let buildSeq = 0;
  const icons = createIconRelationResolver(apiIconGateway());

  const store = () => getEntity<Row>(name.value);

  async function build() {
    const seq = ++buildSeq;
    loading.value = true;
    error.value = "";
    try {
      const entity = useSchemaStore().require(name.value);
      const mutation = entity[mode.value];
      if (!mutation) throw new Error(`"${name.value}" no expone ${mode.value}`);
      const target = store();
      await target.init();

      const entries = formFieldEntries(target.formFields, entity);
      const selected = pickInputFields(entries, mutation, mode.value);

      let source: Row = {};
      recordIri = null;
      if (mode.value === "update") {
        const id = recordId.value as string | number;
        const item = await target.fetchItem(id, entries.map((entry) => entry.name));
        if (!item) throw new Error(`No existe ${entity.name} con id ${String(id)}`);
        source = item;
        recordIri = typeof item.id === "string" ? item.id : itemIri(entity, id);
      }

      const relationOptions = await loadRelationOptions(selected);
      let values = hydrateInitialValues(selected, source, mode.value);
      if (selected.some(isIconRelation)) values = await icons.hydrate(selected, values);

      if (seq !== buildSeq) return;
      fields = selected;
      schema.value = serializeEntityForm(entity.name, fields, {
        mode: mode.value,
        labels: columnLabels(target),
        relationOptions,
        values,
        resetKey: ++generation,
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

  async function run<T>(action: () => Promise<T>): Promise<T> {
    submitting.value = true;
    error.value = "";
    try {
      return await action();
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : String(cause);
      throw cause;
    } finally {
      submitting.value = false;
    }
  }

  /** Guarda (`create` o `update`) y devuelve el registro resultante. */
  function submit(data: Row): Promise<Row> {
    return run(async () => {
      const resolved = fields.some(isIconRelation) ? await icons.resolve(fields, data) : data;
      const payload = serializeSubmitValue(fields, resolved);
      if (mode.value === "create") return store().create(payload);
      // `id` puede no estar entre los campos visibles; la mutación lo exige.
      if (recordIri) payload.id = recordIri;
      return store().update(payload);
    });
  }

  /** Elimina el registro en edición. */
  function remove(): Promise<void> {
    return run(async () => {
      if (!recordIri) throw new Error("No hay registro cargado para eliminar");
      await store().remove(recordIri);
    });
  }

  watch([name, recordId], () => void build(), { immediate: true });

  return { schema, loading, submitting, error, mode, submit, remove, reset: build };
}
