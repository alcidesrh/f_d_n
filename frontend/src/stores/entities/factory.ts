/**
 * Fábrica de stores de entidades dinámicos (`use{EntityName}Store`).
 *
 * `defineEntityStore(name)` devuelve la definición Pinia del store para la
 * entidad `name` (id `entity:{name}`). Cada nombre se define una sola vez
 * (Pinia rechaza ids duplicados); el uso real se hace por demanda vía
 * `useEntityRegistry().getEntity(name)`.
 */

import { defineStore } from "pinia";
import type { StoreDefinition } from "pinia";
import type { AgnosticOption } from "@/lib/apollo/types";
import { rest } from "@/lib/apollo/rest";
import { entitySlug } from "@/utils/entitySlug";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import type { CollectionFieldConfig, EntityStore, EntityStoreState } from "./types";
import type { FilterFieldKind } from "@/components/crud/listUtils";

const definitions = new Map<string, StoreDefinition>();

export function defineEntityStore(name: string): StoreDefinition {
  let definition = definitions.get(name);
  if (!definition) {
    definition = createEntityStore(name);
    definitions.set(name, definition);
  }
  return definition;
}

function createEntityStore(name: string): StoreDefinition {
  const schemaRepo = useSchemaRepositoryStore();
  const pagination = {},
    entity = schemaRepo.getEntityMetadata(name);
  if (entity.collectionKind == "page-connection") {
    pagination["pagination"] = {
      itemsPerPage: 10,
      currentPage: 1,
      totalCount: 0,
      lastPage: 1,
      hasNextPage: false,
    };
  }
  return defineStore(entity.name, {
    // Todo el estado de la entidad persiste (paginación, filtros, orden,
    // columnas con su orden/visibilidad, fullList) para reencontrar el
    // listado como se dejó al reabrir el navegador.
    persist: true,
    state: (): EntityStoreState => ({
      name: entity.name,
      columns: [],
      items: [],
      filters: {},
      order: [],
      item: null,
      fullList: [],
      ...pagination,
    }),
    getters: {
      metadata: (s: EntityStoreState) => schemaRepo.getEntityMetadata(s.name),
      /** Slug kebab-case del nombre de la entidad para URLs (`BoletoAsiento` → `boleto-asiento`). */
      slug: (s: EntityStoreState): string => entitySlug(s.name),
      visibleColumns: (s) => (s.columns ?? []).filter((col) => col.visible !== false),
      hiddenColumns: (s) => (s.columns ?? []).filter((col) => col.visible !== true).length,
    },
    actions: {
      /**
       * Carga las columnas del listado desde el endpoint REST
       * `/entity_configurations?entityClass={name}`. Si el backend no tiene
       * configuración, usa todas las propiedades (scalars) del schema.
       *
       * El estado persiste (orden/visibilidad), así que si ya hay columnas
       * cargadas se devuelven tal cual salvo con `force: true` (usado por el
       * "restablecer vista" del listado).
       */
      async loadColumns(this: EntityStore, force = false): Promise<CollectionFieldConfig[]> {
        if (!force && this.columns.length > 0) return this.columns;
        try {
          const config = await rest.getEntityConfiguration(this.name);
          const columns = config?.collectionFieldConfig;
          if (columns && columns.length > 0) {
            this.columns = columns.map((v) => ({ ...v, showFilter: false }));
            return columns;
          }
        } catch (error) {
          console.warn(
            `[entity:${this.name}] falló la carga de columnas REST, usando schema:`,
            error,
          );
        }
        const fallback = buildFallbackColumns(this.name);
        this.columns = fallback;
        return fallback;
      },

      async fetchItems<T>(this: EntityStore<T>): Promise<T[]> {
        await useSchemaRepositoryStore().collection(this);
        return this.items;
      },

      async fetchItem<T>(this: EntityStore<T>, id: string | number): Promise<T> {
        return useSchemaRepositoryStore().item(this, id);
      },

      async create<T>(this: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
        return useSchemaRepositoryStore().create(this, data);
      },

      async update<T>(this: EntityStore<T>, data: Record<string, unknown>): Promise<T> {
        return useSchemaRepositoryStore().update(this, data);
      },

      async remove<T>(this: EntityStore<T>, id: string | number): Promise<T> {
        return useSchemaRepositoryStore().delete(this, id);
      },

      /**
       * Carga la lista completa de la entidad (`collectionAgnostic`). Con
       * `force: true` omite la caché en memoria y re-consulta el backend.
       */
      async loadFullList(this: EntityStore, force = false): Promise<AgnosticOption[]> {
        return useSchemaRepositoryStore().fullList(this, { force });
      },
      getColumnByFieldName(field: string): CollectionFieldConfig {
        return this.columns.find((v) => v.field == field);
      },
      getFieldKind(field: string): FilterFieldKind {
        const entry = this.metadata.fields.find((f) => f.name === field);
        if (!entry) return "text";
        if (entry.isRelation) return "relation";
        if (entry.namedType === "Date" || entry.namedType === "DateTime") return "date";
        if (entry.namedType === "Int" || entry.namedType === "Float") return "number";
        if (entry.namedType === "Boolean") return "boolean";
        return "text";
      },
    },
  });
}

/**
 * Columnas por defecto cuando el backend no tiene `entity_configurations`:
 * todas las propiedades escalares de la entidad según el schema.
 */
export function buildFallbackColumns(name: string): CollectionFieldConfig[] {
  const schema = useSchemaRepositoryStore().getEntityMetadata(name);
  const fields = (schema?.scalarFields ?? []).filter((field) => field !== "id" && field !== "_id");
  const orderable = new Set(schema?.orderFields ?? []);
  return fields.map((field, index) => ({
    field,
    label: field,
    position: index + 1,
    visible: true,
    // Solo son ordenables los campos aceptados por el input de orden del backend.
    sortable: Boolean(schema?.orderInput) && orderable.has(field),
    filterable: true,
    showFilter: false,
  }));
}
