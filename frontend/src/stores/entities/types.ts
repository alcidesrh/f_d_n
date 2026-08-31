/**
 * Contratos de los stores de entidades dinámicos (`use{EntityName}Store`).
 *
 * Un store de entidad es creado por demanda vía `useEntityRegistry` /
 * `defineEntityStore`, y refleja el estado de un CRUD agnóstico: columnas
 * del listado, items, paginación, filtros, orden e item seleccionado. Las
 * operaciones de datos (fetch/mutaciones) delegan en
 * `useSchemaRepositoryStore`, que es el punto de entrada a la API GraphQL.
 */

import type { AgnosticOption, EntitySchema, OrderCondition } from "@/lib/apollo/types";

export type OrderDirection = "ASC" | "DESC";

export { type OrderCondition };

/** Configuración de una columna del listado (DTO REST de entity_configurations). */
export interface CollectionFieldConfig {
  "@id"?: string;
  id?: number | string;
  field: string;
  name?: string;
  label?: string | null;
  position?: number;
  visible?: boolean;
  sortable?: boolean | null;
  filterable?: boolean | null;
  showFilter?: boolean | null;
  attrs?: Record<string, unknown> | null;
  [key: string]: unknown;
}

export interface PaginationState {
  itemsPerPage: number;
  currentPage: number;
  totalCount: number;
  lastPage: number;
  hasNextPage: boolean;
}

export interface EntityStoreState<T = unknown> {
  /** Nombre de la entidad tal cual (ej: `Boleto`). */
  name: string;
  /** Columnas del listado (de `/entity_configurations` o fallback a todas las propiedades). */
  columns: CollectionFieldConfig[];
  /** Elementos del listado actual. */
  items: T[];
  pagination: PaginationState;
  filters: Record<string, unknown>;
  order: OrderCondition[];
  /** Elemento obtenido por `get` (ver o editar). */
  item: T | null;
  /** Lista completa de la entidad (`collectionAgnostic`), cacheada en LocalStorage. */
  fullList: AgnosticOption[];
}

export interface EntityStore<T = unknown> extends EntityStoreState<T> {
  $id: string;
  $state: EntityStoreState<T>;
  $patch: (partial: Partial<EntityStoreState<T>>) => void;
  $reset: () => void;
  $dispose: () => void;

  /** Schema GraphQL de la entidad, de `useSchemaRepositoryStore` (getter). */
  metadata: EntitySchema | null;

  /** Slug kebab-case del nombre de la entidad para URLs (`BoletoAsiento` → `boleto-asiento`). */
  slug: string;

  loadColumns(force?: boolean): Promise<CollectionFieldConfig[]>;
  fetchItems(): Promise<T[]>;
  fetchItem(id: string | number): Promise<T>;
  create(data: Record<string, unknown>): Promise<T>;
  update(data: Record<string, unknown>): Promise<T>;
  remove(id: string | number): Promise<T>;
  /** Carga (o reusa la cacheada) la lista completa de la entidad. */
  loadFullList(force?: boolean): Promise<AgnosticOption[]>;
}
