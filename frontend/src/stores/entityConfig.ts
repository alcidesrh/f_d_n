/**
 * `useEntityConfigStore` — estado del editor de configuración de entidades
 * (ADR-010). Mantiene el listado de `entityClass`, la configuración de la
 * entidad seleccionada (campos de listado y de formulario) y el snapshot para
 * detectar cambios sin guardar.
 *
 * `position` no se edita a mano: es la posición del panel en la lista, así que
 * se deriva del índice al construir el payload de guardado.
 */

import { defineStore } from "pinia";
import {
  fetchEntityClasses,
  fetchEntityConfiguration,
  saveEntityConfiguration,
  type CollectionFieldConfigDto,
  type EntityConfigurationDetailDto,
  type FormFieldConfigDto,
} from "@/features/entity-config/api";
import { stores as entityStores } from "@/composables/useEntityRegistry";

/** Fila arrastrable: el DTO más una `key` estable (el IRI) para el drag & drop. */
export type CollectionFieldRow = CollectionFieldConfigDto & { key: string };
export type FormFieldRow = FormFieldConfigDto & { key: string };

export interface EntityConfigState {
  entityClasses: string[];
  selected: string;
  collectionFields: CollectionFieldRow[];
  formFields: FormFieldRow[];
  /** Snapshot serializado de la última carga/guardado: base de `dirty`. */
  baseline: string;
  /** Keys (IRIs) de paneles con `attrs` que no es JSON válido: bloquean el guardado. */
  attrsErrors: string[];
  status: "idle" | "loading" | "ready" | "error";
  saving: boolean;
  error: string;
}

/** Ordena por `position` sin depender del orden que devuelva la API. */
function byPosition<T extends { position: number }>(fields: T[]): T[] {
  return [...fields].sort((a, b) => a.position - b.position);
}

function toCollectionRows(
  config: EntityConfigurationDetailDto,
): CollectionFieldRow[] {
  return byPosition(config.collectionFieldConfig ?? []).map((field) => ({
    ...field,
    key: field.id,
  }));
}

function toFormRows(config: EntityConfigurationDetailDto): FormFieldRow[] {
  return byPosition(config.formFields ?? []).map((field) => ({
    ...field,
    key: field.id,
  }));
}

/**
 * Cambia `visible` de una fila y la reubica con el mismo criterio que
 * `EntityConfiguration::orderFields()`: las ocultas van al final de la lista,
 * las visibles al final del bloque visible. Devuelve un array nuevo (la
 * lista arrastrable resincroniza por identidad, no en profundidad).
 */
function repositionByVisibility<T extends { key: string; visible: boolean }>(
  rows: T[],
  key: string,
  visible: boolean,
): T[] {
  const index = rows.findIndex((row) => row.key === key);
  const row = rows[index];
  if (!row) return rows;

  row.visible = visible;
  const rest = rows.filter((_, position) => position !== index);
  const firstHidden = rest.findIndex((item) => !item.visible);
  rest.splice(visible && firstHidden >= 0 ? firstHidden : rest.length, 0, row);
  return rest;
}

/** Payload de un campo de listado; `position` es la posición del panel (1-based). */
function collectionPayload(
  row: CollectionFieldRow,
  index: number,
): CollectionFieldConfigDto {
  return {
    id: row.id,
    field: row.field,
    label: row.label ?? null,
    position: index + 1,
    visible: Boolean(row.visible),
    kind: row.kind ?? null,
    attrs: row.attrs ?? null,
    sortable: row.sortable ?? null,
    filterable: row.filterable ?? null,
  };
}

function formPayload(row: FormFieldRow, index: number): FormFieldConfigDto {
  return {
    id: row.id,
    field: row.field,
    label: row.label ?? null,
    position: index + 1,
    visible: Boolean(row.visible),
    kind: row.kind ?? null,
    attrs: row.attrs ?? null,
    groupName: row.groupName ?? null,
  };
}

function snapshot(
  collection: CollectionFieldRow[],
  form: FormFieldRow[],
): string {
  return JSON.stringify({
    collection: collection.map(collectionPayload),
    form: form.map(formPayload),
  });
}

export const useEntityConfigStore = defineStore("entityConfig", {
  state: (): EntityConfigState => ({
    entityClasses: [],
    selected: "",
    collectionFields: [],
    formFields: [],
    baseline: "",
    attrsErrors: [],
    status: "idle",
    saving: false,
    error: "",
  }),

  getters: {
    /** Hay cambios sin guardar (incluye el reordenamiento por drag & drop). */
    dirty: (st): boolean =>
      st.baseline !== "" &&
      st.baseline !== snapshot(st.collectionFields, st.formFields),
    loading: (st): boolean => st.status === "loading",
    canSave(): boolean {
      return this.dirty && this.attrsErrors.length === 0 && !this.saving;
    },
  },

  actions: {
    /** Carga los nombres de entidad con configuración persistida. */
    async loadEntityClasses(force = false): Promise<string[]> {
      if (!force && this.entityClasses.length > 0) return this.entityClasses;
      try {
        this.entityClasses = await fetchEntityClasses();
        this.error = "";
      } catch (error) {
        this.error = error instanceof Error ? error.message : String(error);
        console.error("[entityConfig] no se pudo listar las entidades:", error);
      }
      return this.entityClasses;
    },

    /** Selecciona una entidad y carga su configuración (descarta cambios en curso). */
    async select(entityClass: string): Promise<void> {
      this.selected = entityClass;
      this.status = "loading";
      this.error = "";
      try {
        const config = await fetchEntityConfiguration(entityClass);
        if (!config) {
          this.applyConfig(null);
          this.status = "error";
          this.error = `No hay configuración persistida para ${entityClass}`;
          return;
        }
        this.applyConfig(config);
        this.status = "ready";
      } catch (error) {
        this.applyConfig(null);
        this.status = "error";
        this.error = error instanceof Error ? error.message : String(error);
        console.error(
          `[entityConfig] no se pudo cargar ${entityClass}:`,
          error,
        );
      }
    },

    /** Recarga la entidad seleccionada desde el backend (descarta cambios locales). */
    async reload(): Promise<void> {
      if (this.selected) await this.select(this.selected);
    },

    /** Vuelca la configuración en el estado y fija el snapshot de referencia. */
    applyConfig(config: EntityConfigurationDetailDto | null): void {
      this.collectionFields = config ? toCollectionRows(config) : [];
      this.formFields = config ? toFormRows(config) : [];
      this.baseline = config
        ? snapshot(this.collectionFields, this.formFields)
        : "";
      this.attrsErrors = [];
    },

    /**
     * Alterna `visible` de un campo y lo reubica: al ocultarlo baja al final
     * de la lista, al mostrarlo sube al final de los visibles. Las posiciones
     * de todos los paneles se derivan del nuevo orden.
     */
    setVisible(
      variant: "collection" | "form",
      key: string,
      visible: boolean,
    ): void {
      if (variant === "collection") {
        this.collectionFields = repositionByVisibility(
          this.collectionFields,
          key,
          visible,
        );
      } else {
        this.formFields = repositionByVisibility(this.formFields, key, visible);
      }
    },

    /** Marca/limpia un panel cuyo `attrs` no es JSON válido. */
    setAttrsError(key: string, invalid: boolean): void {
      const errors = new Set(this.attrsErrors);
      if (invalid) errors.add(key);
      else errors.delete(key);
      if (errors.size !== this.attrsErrors.length)
        this.attrsErrors = [...errors];
    },

    /**
     * Guarda ambas listas en una sola mutación. Tras el guardado refresca las
     * columnas del store de la entidad si ya está instanciado, para que el
     * listado tome los cambios sin recargar la página.
     */
    async save(): Promise<void> {
      if (!this.selected || this.saving) return;
      this.saving = true;
      try {
        const saved = await saveEntityConfiguration({
          entityClass: this.selected,
          collectionFieldConfig: this.collectionFields.map(collectionPayload),
          formFields: this.formFields.map(formPayload),
        });
        this.applyConfig(saved);
        this.error = "";
        await entityStores.get(this.selected)?.init(true);
      } finally {
        this.saving = false;
      }
    },
  },
});
